<?php

namespace Tests\Feature\Discovery;

use App\Jobs\Discovery\DiscoverNicheContent;
use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\CreatorRelationship;
use App\Models\User;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorNicheCatalog;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\NicheExpansionService;
use App\Services\Discovery\OutlierScore;
use App\Services\Feed\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ContentDiscoveryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cold_start_builds_a_ranked_feed_and_reusable_intelligence(): void
    {
        config([
            'services.discovery.driver' => 'mock',
            'services.discovery.search_query_limit' => 2,
            'services.discovery.search_results_per_query' => 3,
            'services.discovery.seed_limit' => 2,
            'services.discovery.related_per_seed' => 2,
            'services.discovery.measure_batch' => 20,
            'services.discovery.min_followers' => 1,
            'services.discovery.min_post_engagement' => 1,
        ]);

        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'niche' => 'aquascaping',
            'topics' => ['planted aquariums', 'aquatic plants'],
            'creator_dna' => [
                'primary_niche' => 'aquascaping',
                'sub_niches' => ['planted aquariums'],
                'topics' => ['aquatic plants'],
                'audience' => ['aquarium hobbyists'],
                'language' => 'en',
                'content_pillars' => ['tank design'],
                'tone' => ['educational'],
            ],
        ]);

        Bus::fake();
        (new DiscoverNicheContent($user->id))->handle(
            app(NicheExpansionService::class),
            app(InstagramDataProvider::class),
        );

        $handles = Creator::query()->pluck('username')->all();
        $this->assertNotEmpty($handles);
        $this->assertGreaterThan(0, CreatorRelationship::query()->count());

        (new MeasureAccountEngagement($handles))->handle(
            app(InstagramDataProvider::class),
            app(CreatorNicheService::class),
            app(CreatorNicheCatalog::class),
            app(OutlierScore::class),
            app(ContentSafetyPolicy::class),
        );

        $feed = app(RecommendationService::class)->forUser($user);

        $this->assertNotEmpty($feed);
        $this->assertGreaterThan(0, Creator::query()->whereNotNull('instagram_user_id')->count());
        $this->assertGreaterThan(0, Creator::query()->whereNotNull('performance_baselines')->count());
        $this->assertGreaterThan(0, Creator::query()->whereHas('niches')->count());
        $this->assertGreaterThan(0, Creator::query()->whereHas('posts', fn ($query) => $query->whereNotNull('instagram_media_id'))->count());
    }
}
