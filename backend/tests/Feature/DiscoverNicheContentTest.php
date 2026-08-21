<?php

namespace Tests\Feature;

use App\Jobs\DiscoverNicheContent;
use App\Jobs\MeasureAccountEngagement;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\CreatorRelationship;
use App\Models\DiscoveryQuery;
use App\Models\User;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\NicheExpansionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DiscoverNicheContentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'creator_catalog.curated_only' => false,
            'services.discovery.driver' => 'mock',
            'services.discovery.search_query_limit' => 3,
            'services.discovery.search_results_per_query' => 3,
            'services.discovery.seed_limit' => 3,
            'services.discovery.related_per_seed' => 2,
        ]);

        $this->user = User::factory()->create();

        CreatorProfile::query()->create([
            'user_id' => $this->user->id,
            'niche' => 'vegan cooking',
            'topics' => ['vegan', 'meal prep'],
            'creator_dna' => [
                'primary_niche' => 'vegan cooking',
                'sub_niches' => ['plant-based meal prep'],
                'topics' => ['vegan recipes', 'batch cooking'],
                'audience' => ['busy vegans'],
                'language' => 'en',
                'content_pillars' => ['quick recipes'],
                'tone' => ['educational'],
            ],
        ]);
    }

    private function discover(): void
    {
        (new DiscoverNicheContent($this->user->id))->handle(
            app(NicheExpansionService::class),
            app(InstagramDataProvider::class),
        );
    }

    public function test_it_finds_seed_and_related_creators_then_queues_measurement(): void
    {
        Bus::fake();

        $this->discover();

        $this->assertGreaterThan(0, Creator::query()->count());
        $this->assertGreaterThan(0, CreatorRelationship::query()->count());
        $this->assertGreaterThan(0, DiscoveryQuery::query()->count());

        Bus::assertDispatched(
            MeasureAccountEngagement::class,
            fn (MeasureAccountEngagement $job): bool => $job->usernames !== [],
        );
    }

    public function test_rediscovery_updates_existing_creators_instead_of_duplicating_them(): void
    {
        Bus::fake();
        $this->discover();
        $creatorCount = Creator::query()->count();
        $relationshipCount = CreatorRelationship::query()->count();

        DiscoveryQuery::query()->delete();
        $this->user->creatorProfile->update(['discovery_refreshed_at' => now()->subMonth()]);

        $this->discover();

        $this->assertSame($creatorCount, Creator::query()->count());
        $this->assertSame($relationshipCount, CreatorRelationship::query()->count());
    }

    public function test_refresh_resumes_unmeasured_creators_while_search_queries_are_on_cooldown(): void
    {
        Bus::fake();

        Creator::query()->create([
            'username' => 'waiting.creator',
            'display_name' => 'Waiting Creator',
            'niche' => 'vegan cooking',
            'followers' => 0,
            'average_views' => 0,
            'average_likes' => 0,
        ]);

        foreach (app(NicheExpansionService::class)->queriesFor($this->user->fresh()) as $query) {
            DiscoveryQuery::query()->create([
                'query' => $query,
                'last_searched_at' => now(),
            ]);
        }

        $this->discover();

        Bus::assertDispatched(
            MeasureAccountEngagement::class,
            fn (MeasureAccountEngagement $job): bool => in_array('waiting.creator', $job->usernames, true),
        );
    }

    public function test_curated_refresh_measures_approved_creators_without_content_in_chunks(): void
    {
        Bus::fake();
        config([
            'creator_catalog.curated_only' => true,
            'services.discovery.measure_batch' => 12,
            'services.discovery.measure_chunk' => 10,
        ]);

        foreach (range(1, 12) as $index) {
            Creator::query()->create([
                'username' => "approved.{$index}",
                'display_name' => "Approved {$index}",
                'niche' => 'tech-ai',
                'followers' => 25_000,
                'average_views' => 0,
                'average_likes' => 0,
                'curation_status' => 'approved',
                'last_measured_at' => now(),
            ]);
        }

        Creator::query()->create([
            'username' => 'not.approved',
            'display_name' => 'Not Approved',
            'niche' => 'tech-ai',
            'followers' => 25_000,
            'average_views' => 0,
            'average_likes' => 0,
            'curation_status' => 'discovered',
        ]);

        $this->discover();

        Bus::assertDispatchedTimes(MeasureAccountEngagement::class, 2);
        $handles = Bus::dispatched(MeasureAccountEngagement::class)
            ->flatMap(fn (MeasureAccountEngagement $job): array => $job->usernames);

        $this->assertCount(12, $handles);
        $this->assertFalse($handles->contains('not.approved'));
    }

    public function test_reach_bait_terms_are_stripped_from_account_queries(): void
    {
        $this->user->creatorProfile->update([
            'creator_dna' => null,
            'topics' => ['meal prep', 'viralreels', 'explorepage', 'fyp'],
            'discovery_queries' => null,
            'discovery_refreshed_at' => null,
        ]);

        $queries = app(NicheExpansionService::class)->queriesFor($this->user->fresh());

        $this->assertContains('meal prep', $queries);
        $this->assertNotContains('viralreels', $queries);
        $this->assertNotContains('explorepage', $queries);
        $this->assertNotContains('fyp', $queries);
    }
}
