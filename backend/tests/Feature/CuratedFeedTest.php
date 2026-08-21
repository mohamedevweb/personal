<?php

namespace Tests\Feature;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuratedFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['creator_catalog.curated_only' => true]);
        $this->user = User::factory()->create();
        CreatorProfile::query()->create(['user_id' => $this->user->id, 'market' => 'FR']);
    }

    public function test_feed_respects_eight_two_two_market_quota_and_excludes_non_approved_creators(): void
    {
        foreach (['FR' => 10, 'GB' => 5, 'US' => 5] as $market => $count) {
            $this->posts($market, $count);
        }
        $this->posts('FR', 1, 'discovered');

        $feed = app(RecommendationService::class)->forUser($this->user);
        $markets = $feed->map(fn (array $item): string => explode(':', $item['hook'])[0])->countBy()->all();

        $this->assertSame(['FR' => 8, 'GB' => 2, 'US' => 2], $markets);
        $this->assertFalse($feed->pluck('hook')->contains(fn (string $hook): bool => str_contains($hook, 'discovered')));
    }

    public function test_missing_market_quota_is_backfilled_by_best_other_approved_posts(): void
    {
        $this->posts('FR', 12);
        $this->posts('GB', 5);
        $this->posts('US', 1);

        $markets = app(RecommendationService::class)->forUser($this->user)
            ->map(fn (array $item): string => explode(':', $item['hook'])[0])->countBy();

        $this->assertSame(12, $markets->sum());
        $this->assertSame(1, $markets->get('US'));
        $this->assertGreaterThanOrEqual(8, $markets->get('FR'));
    }

    public function test_unknown_market_receives_four_posts_per_market(): void
    {
        $this->user->creatorProfile()->update(['market' => null]);
        foreach (['FR', 'GB', 'US'] as $market) {
            $this->posts($market, 6);
        }

        $markets = app(RecommendationService::class)->forUser($this->user)
            ->map(fn (array $item): string => explode(':', $item['hook'])[0])->countBy()->all();

        $this->assertSame(['FR' => 4, 'GB' => 4, 'US' => 4], $markets);
    }

    public function test_primary_vertical_is_prioritized_without_breaking_the_feed_fallback(): void
    {
        $this->user->creatorProfile()->update(['primary_vertical' => 'tech-ai']);
        $this->posts('FR', 12, niche: 'sport-fitness', baseOutlier: 5);
        $this->posts('FR', 8, niche: 'tech-ai', baseOutlier: 1.5);

        $feed = app(RecommendationService::class)->forUser($this->user);

        $this->assertSame(12, $feed->count());
        $this->assertSame(8, $feed->take(8)->where('creator.niche', 'tech-ai')->count());
        $this->assertSame(4, $feed->where('creator.niche', 'sport-fitness')->count());
    }

    public function test_private_inspirations_lead_the_feed_and_keep_the_approved_catalog_as_fallback(): void
    {
        $this->posts('FR', 12, niche: 'sport-fitness', baseOutlier: 5);
        $this->posts('FR', 4, 'discovered', 'tech-ai', 2);
        $inspiration = Creator::query()->where('curation_status', 'discovered')->firstOrFail();
        $inspiration->update(['safety_status' => 'allowed']);
        $this->user->inspirationCreators()->attach($inspiration->id, ['priority' => 0]);

        $feed = app(RecommendationService::class)->forUser($this->user);

        $this->assertSame(12, $feed->count());
        $this->assertSame(2, $feed->take(2)->where('creator.username', $inspiration->username)->count());
        $this->assertSame(2, $feed->where('creator.username', $inspiration->username)->count());
        $this->assertSame(10, $feed->where('creator.username', '!=', $inspiration->username)->count());
    }

    public function test_global_feed_ignores_personal_niche_and_market_quotas_but_keeps_catalog_guards(): void
    {
        $this->user->creatorProfile()->update(['primary_vertical' => 'tech-ai']);
        $this->posts('US', 12, niche: 'sport-fitness', baseOutlier: 5);
        $this->posts('FR', 12, niche: 'tech-ai', baseOutlier: 2);
        $this->posts('GB', 3, 'discovered', 'food-cooking', 8);

        $feed = app(RecommendationService::class)->globalForUser($this->user);

        $this->assertSame(12, $feed->count());
        $this->assertTrue($feed->every(fn (array $post): bool => $post['creator']['niche'] === 'sport-fitness'));
        $this->assertFalse($feed->pluck('hook')->contains(
            fn (string $hook): bool => str_contains($hook, 'discovered'),
        ));

        $this->actingAs($this->user)->getJson('/api/feed/global')
            ->assertOk()
            ->assertJsonCount(12, 'items');
    }

    private function posts(
        string $market,
        int $count,
        string $status = 'approved',
        string $niche = 'sport-fitness',
        float $baseOutlier = 3,
    ): void {
        $creator = Creator::query()->create([
            'username' => strtolower($market).'-'.$status.'-'.Creator::query()->count(),
            'display_name' => $market,
            'niche' => $niche,
            'market' => $market,
            'curation_status' => $status,
            'followers' => 100000,
            'average_views' => 10000,
            'average_likes' => 1000,
            'baseline_engagement' => 700,
        ]);

        foreach (range(1, $count) as $index) {
            $outlier = $baseOutlier - ($index / 100);

            ContentPost::query()->create([
                'creator_id' => $creator->id,
                'source_url' => "https://instagram.test/{$creator->username}/{$index}",
                'platform' => 'instagram',
                'format' => 'reel',
                'hook' => "{$market}:{$status}:{$index}",
                'caption' => 'Measured catalog post',
                'views' => 100000 - ($index * 100),
                'likes' => 1000,
                'comments' => 100,
                'published_at' => now()->subHours($index),
                'outlier_score' => $outlier,
                'performance_ratio' => $outlier,
                'engagement_rate' => 1.1,
                'measured_at' => now(),
            ]);
        }
    }
}
