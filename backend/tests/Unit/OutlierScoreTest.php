<?php

namespace Tests\Unit;

use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\OutlierScore;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class OutlierScoreTest extends TestCase
{
    public function test_views_can_measure_an_outlier_when_engagement_is_unavailable(): void
    {
        $normal = $this->discovered('normal', 1_000);
        $outlier = $this->discovered('outlier', 8_000);
        $service = new OutlierScore;
        $baselines = $service->baselines(collect([$normal, $normal, $normal]));

        $this->assertNull($baselines['engagement']);
        $this->assertSame(8.0, $service->score($outlier, $baselines));
    }

    public function test_a_post_is_measured_against_its_own_format(): void
    {
        $service = new OutlierScore;
        // An account whose Reels collect ten times the views of its carousels —
        // the ordinary shape of an Instagram profile, and the one that used to
        // make every Reel look like a breakout.
        $baselines = $service->baselines(collect([
            $this->discovered('r1', 100_000),
            $this->discovered('r2', 100_000),
            $this->discovered('r3', 100_000),
            $this->discovered('c1', 10_000, 'carousel'),
            $this->discovered('c2', 10_000, 'carousel'),
            $this->discovered('c3', 10_000, 'carousel'),
        ]));

        $this->assertSame(10_000.0, $baselines['formats']['carousel']['views']);
        $this->assertSame(100_000.0, $baselines['formats']['reel']['views']);

        // A typical carousel is typical, not a failure, and a typical Reel is not
        // a hit. Both sat far from 1.0 under a single account-wide median.
        $this->assertSame(1.0, $service->score($this->discovered('c4', 10_000, 'carousel'), $baselines));
        $this->assertSame(1.0, $service->score($this->discovered('r4', 100_000), $baselines));
        $this->assertSame(3.0, $service->score($this->discovered('c5', 30_000, 'carousel'), $baselines));
    }

    public function test_a_format_the_account_rarely_posts_falls_back_to_its_whole_history(): void
    {
        $service = new OutlierScore;
        $baselines = $service->baselines(collect([
            $this->discovered('r1', 10_000),
            $this->discovered('r2', 10_000),
            $this->discovered('r3', 10_000),
            $this->discovered('c1', 40_000, 'carousel'),
        ]));

        // One carousel is not a normal, so there is nothing to compare it to but
        // the account itself.
        $this->assertArrayNotHasKey('carousel', $baselines['formats']);
        $this->assertNull($service->against($baselines, 'carousel')['format']);
        $this->assertSame('reel', $service->against($baselines, 'reel')['format']);
        $this->assertSame(4.0, $service->score($this->discovered('c2', 40_000, 'carousel'), $baselines));
    }

    public function test_baselines_written_before_formats_were_separated_still_score(): void
    {
        $service = new OutlierScore;

        $this->assertSame(
            2.0,
            $service->score($this->discovered('r1', 2_000), ['views' => 1_000.0, 'engagement' => null]),
        );
    }

    private function discovered(string $id, int $views, string $format = 'reel'): DiscoveredPost
    {
        return new DiscoveredPost(
            sourceUrl: "https://instagram.com/p/{$id}/",
            username: 'creator',
            displayName: null,
            avatarUrl: null,
            followers: 10_000,
            caption: '',
            thumbnailUrl: null,
            likes: 0,
            comments: 0,
            views: $views,
            publishedAt: CarbonImmutable::now(),
            format: $format,
            hashtags: [],
            externalId: $id,
        );
    }
}
