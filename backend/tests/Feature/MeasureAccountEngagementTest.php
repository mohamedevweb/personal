<?php

namespace Tests\Feature;

use App\Jobs\MeasureAccountEngagement;
use App\Models\Creator;
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

    public function test_it_measures_engagement_rate_for_seeded_creators(): void
    {
        $creator = Creator::query()->create([
            'username' => 'pasta.daily',
            'display_name' => 'Pasta Daily',
            'niche' => 'cuisine',
            'followers' => 0,
            'average_views' => 0,
            'average_likes' => 0,
        ]);

        (new MeasureAccountEngagement('cuisine'))->handle(app(\App\Services\Discovery\ProfileDiscoveryService::class));

        $creator->refresh();

        // The profile scrape backfills the real follower count and an engagement
        // rate, and stamps the measurement time so the cooldown can kick in.
        $this->assertGreaterThan(0, $creator->followers);
        $this->assertGreaterThan(0, $creator->avg_engagement_rate);
        $this->assertNotNull($creator->last_measured_at);

        // Recent posts are ingested and ranked against the account's own median.
        $this->assertGreaterThan(0, $creator->posts()->count());
    }

    public function test_top_accounts_query_ranks_by_engagement_rate(): void
    {
        foreach (['pasta.daily', 'wok.hq', 'grill.lab'] as $username) {
            Creator::query()->create([
                'username' => $username,
                'display_name' => $username,
                'niche' => 'cuisine',
                'followers' => 0,
                'average_views' => 0,
                'average_likes' => 0,
            ]);
        }

        (new MeasureAccountEngagement('cuisine'))->handle(app(\App\Services\Discovery\ProfileDiscoveryService::class));

        $top = Creator::query()
            ->where('niche', 'cuisine')
            ->orderByDesc('avg_engagement_rate')
            ->limit(30)
            ->get();

        $this->assertCount(3, $top);
        // Sorted descending: each rate is >= the next.
        $rates = $top->pluck('avg_engagement_rate')->all();
        $this->assertSame($rates, collect($rates)->sortDesc()->values()->all());
    }

    public function test_it_skips_recently_measured_creators(): void
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

        (new MeasureAccountEngagement('cuisine'))->handle(app(\App\Services\Discovery\ProfileDiscoveryService::class));

        $fresh->refresh();

        // Untouched: still the seeded values, no posts ingested.
        $this->assertSame(123, $fresh->followers);
        $this->assertSame(0, $fresh->posts()->count());
    }
}
