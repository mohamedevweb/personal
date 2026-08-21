<?php

namespace Tests\Feature;

use App\Jobs\MeasureAccountEngagement;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorNicheCatalog;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasureAccountEngagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force the deterministic mock profile scraper regardless of local env.
        config(['services.discovery.driver' => 'mock']);
    }

    private function measure(string ...$usernames): void
    {
        (new MeasureAccountEngagement($usernames))->handle(
            app(InstagramDataProvider::class),
            app(CreatorNicheService::class),
            app(CreatorNicheCatalog::class),
            app(OutlierScore::class),
            app(ContentSafetyPolicy::class),
        );
    }

    public function test_it_measures_an_account_and_classifies_its_own_niche(): void
    {
        $this->measure('pasta.daily');

        $creator = Creator::query()->where('username', 'pasta.daily')->firstOrFail();

        // The profile scrape backfills the real follower count, the baseline every
        // post is scored against, and an engagement rate, then stamps the
        // measurement time so the cooldown can kick in.
        $this->assertGreaterThan(0, $creator->followers);
        $this->assertGreaterThan(0, $creator->avg_engagement_rate);
        $this->assertGreaterThan(0, $creator->baseline_engagement);
        $this->assertNotNull($creator->last_measured_at);

        // The niche is read from the account itself, not inherited from whoever
        // happened to discover it.
        $this->assertNotEmpty($creator->niche_topics);
        $this->assertGreaterThan(0, $creator->niches()->count());

        $this->assertGreaterThan(0, $creator->posts()->count());
    }

    public function test_posts_are_scored_against_their_own_creator(): void
    {
        $this->measure('pasta.daily');

        $creator = Creator::query()->where('username', 'pasta.daily')->firstOrFail();
        $posts = $creator->posts;

        // Every post is measured, and the median post of an account sits at 1.0 by
        // definition — so a scrape produces both winners and losers, not a batch
        // where everything looks like an outlier.
        $this->assertTrue($posts->every(fn (ContentPost $post): bool => $post->measured_at !== null));
        $this->assertGreaterThanOrEqual(1.0, $posts->max('outlier_score'));
        $this->assertLessThanOrEqual(1.0, $posts->min('outlier_score'));

        // performance_ratio stays in step for the clients still reading it.
        $this->assertSame(
            $posts->pluck('outlier_score')->all(),
            $posts->pluck('performance_ratio')->all(),
        );

        $this->assertNotNull($creator->performance_baselines['views']);
    }

    public function test_it_scores_posts_discovered_before_the_baseline_was_known(): void
    {
        $creator = Creator::query()->create([
            'username' => 'pasta.daily',
            'display_name' => 'Pasta Daily',
            'niche' => 'cuisine',
            'followers' => 0,
            'average_views' => 0,
            'average_likes' => 0,
        ]);

        // A hashtag scrape stores posts unscored, because a hashtag page cannot say
        // what this account normally gets.
        $orphan = ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/from-a-hashtag/',
            'platform' => 'instagram',
            'format' => 'reel',
            'hook' => 'Found through a hashtag',
            'caption' => 'Found through a hashtag',
            'views' => 0,
            'likes' => 10,
            'comments' => 2,
            'published_at' => now()->subDay(),
        ]);

        $this->assertNull($orphan->measured_at);

        $this->measure('pasta.daily');

        $orphan->refresh();

        // Measuring the account scores everything it already had in the feed, so a
        // flat post cannot keep an inflated score from the batch it arrived in.
        $this->assertNotNull($orphan->measured_at);
        $this->assertLessThan(1.0, $orphan->outlier_score);
    }

    public function test_it_rebuilds_content_for_a_recently_measured_account_when_posts_are_missing(): void
    {
        $fresh = Creator::query()->create([
            'username' => 'sushi.co',
            'display_name' => 'Sushi Co',
            'niche' => 'cuisine',
            'followers' => 123,
            'average_views' => 0,
            'average_likes' => 0,
            'avg_engagement_rate' => 4.2,
            'last_measured_at' => now(),
        ]);

        $this->measure('sushi.co');

        $fresh->refresh();

        $this->assertNotSame(123, $fresh->followers);
        $this->assertGreaterThan(0, $fresh->posts()->count());
    }

    public function test_top_accounts_rank_by_engagement_rate(): void
    {
        $this->measure('pasta.daily', 'wok.hq', 'grill.lab');

        $rates = Creator::query()
            ->orderByDesc('avg_engagement_rate')
            ->limit(30)
            ->pluck('avg_engagement_rate')
            ->all();

        $this->assertCount(3, $rates);
        $this->assertSame($rates, collect($rates)->sortDesc()->values()->all());
    }

    public function test_an_account_below_the_follower_floor_is_measured_but_never_scored(): void
    {
        config(['services.discovery.min_followers' => 10_000_000]);

        $this->measure('pasta.daily');

        $creator = Creator::query()->where('username', 'pasta.daily')->firstOrFail();

        // Measured, so the cooldown stops us paying to re-scrape it every day.
        $this->assertNotNull($creator->last_measured_at);
        $this->assertGreaterThan(0, $creator->posts()->count());

        // But never scored, so nothing it publishes can reach a feed. An outlier
        // ratio over a handful of likes is arithmetic, not evidence.
        $this->assertSame(0, $creator->posts()->whereNotNull('measured_at')->count());
    }

    public function test_an_account_that_falls_below_the_floor_loses_its_scores(): void
    {
        $this->measure('pasta.daily');

        $creator = Creator::query()->where('username', 'pasta.daily')->firstOrFail();
        $this->assertGreaterThan(0, $creator->posts()->whereNotNull('measured_at')->count());

        // Re-measured later against a floor it no longer clears.
        config(['services.discovery.min_followers' => 10_000_000]);
        $creator->update(['last_measured_at' => now()->subYear()]);

        $this->measure('pasta.daily');

        // Its old scores are stripped rather than left to linger in a feed.
        $this->assertSame(0, $creator->posts()->whereNotNull('measured_at')->count());
        $this->assertSame(0.0, (float) $creator->posts()->max('outlier_score'));
    }
}
