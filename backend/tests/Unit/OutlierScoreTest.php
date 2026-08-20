<?php

namespace Tests\Unit;

use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\OutlierScore;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OutlierScoreTest extends TestCase
{
    public function test_views_can_measure_an_outlier_when_engagement_is_unavailable(): void
    {
        $normal = $this->post('normal', 1_000);
        $outlier = $this->post('outlier', 8_000);
        $service = new OutlierScore;
        $baselines = $service->baselines(collect([$normal, $normal, $normal]));

        $this->assertNull($baselines['engagement']);
        $this->assertSame(8.0, $service->score($outlier, $baselines));
    }

    private function post(string $id, int $views): DiscoveredPost
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
            format: 'reel',
            hashtags: [],
            externalId: $id,
        );
    }
}
