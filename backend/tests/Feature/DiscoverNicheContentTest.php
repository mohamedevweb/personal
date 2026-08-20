<?php

namespace Tests\Feature;

use App\Jobs\DiscoverNicheContent;
use App\Jobs\MeasureAccountEngagement;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\DiscoveredHashtag;
use App\Models\User;
use App\Services\Discovery\ContentDiscoveryService;
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

        config(['services.discovery.driver' => 'mock']);

        $this->user = User::factory()->create();

        CreatorProfile::query()->create([
            'user_id' => $this->user->id,
            'niche' => 'vegan cooking',
            'topics' => ['vegan', 'mealprep'],
        ]);
    }

    private function discover(): void
    {
        (new DiscoverNicheContent($this->user->id))->handle(
            app(NicheExpansionService::class),
            app(ContentDiscoveryService::class),
        );
    }

    public function test_hashtag_results_are_stored_without_a_score(): void
    {
        Bus::fake();

        $this->discover();

        $posts = ContentPost::query()->get();

        $this->assertNotEmpty($posts);

        // A hashtag page carries no follower count and no sense of what the account
        // normally gets, so it cannot say whether a post did well. Guessing here is
        // what put flat posts from large accounts at the top of the feed.
        $this->assertTrue($posts->every(fn (ContentPost $post): bool => $post->measured_at === null));
        $this->assertTrue($posts->every(fn (ContentPost $post): bool => $post->outlier_score === 0.0));
    }

    public function test_it_queues_measurement_for_the_accounts_it_found(): void
    {
        Bus::fake();

        $this->discover();

        $expected = Creator::query()->pluck('username')->sort()->values()->all();

        $this->assertNotEmpty($expected);

        Bus::assertDispatched(
            MeasureAccountEngagement::class,
            fn (MeasureAccountEngagement $job): bool => collect($job->usernames)->sort()->values()->all() === $expected,
        );
    }

    public function test_rediscovery_does_not_overwrite_a_measured_account(): void
    {
        Bus::fake();

        $this->discover();

        $creator = Creator::query()->firstOrFail();

        // Stand in for a completed profile scrape.
        $creator->update([
            'niche' => 'plant-based cooking',
            'niche_topics' => ['vegan', 'batchcooking'],
            'followers' => 42_000,
            'last_measured_at' => now(),
        ]);

        // The same hashtags are on cooldown now, so clear it to force a second pass.
        DiscoveredHashtag::query()->delete();

        $this->discover();

        $creator->refresh();

        // The account's own classification and real follower count survive: a
        // hashtag result knows neither and must not clobber them.
        $this->assertSame('plant-based cooking', $creator->niche);
        $this->assertSame(['vegan', 'batchcooking'], $creator->niche_topics);
        $this->assertSame(42_000, $creator->followers);
    }

    public function test_reach_bait_tags_are_stripped_from_the_expansion(): void
    {
        $this->user->creatorProfile->update([
            'topics' => ['mealprep', 'viralreels', 'explorepage', 'fyp'],
            'discovery_hashtags' => null,
            'discovery_refreshed_at' => null,
        ]);

        $hashtags = app(NicheExpansionService::class)->hashtagsFor($this->user->fresh());

        // Reach-bait is not a niche: established accounts do not tag with it, so
        // scraping those pages returns accounts with no audience trying to be seen.
        $this->assertContains('mealprep', $hashtags);
        $this->assertNotContains('viralreels', $hashtags);
        $this->assertNotContains('explorepage', $hashtags);
        $this->assertNotContains('fyp', $hashtags);
    }
}
