<?php

namespace App\Services\Discovery;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deterministic sample posts so the feed is testable end to end without a paid
 * Apify run. Metrics are derived from a hash of the hashtag, so the same niche
 * always produces the same, stable feed.
 */
class MockInstagramDiscoveryService implements ContentDiscoveryService
{
    private const FORMATS = ['reel', 'carousel', 'image'];

    private const HOOKS = [
        'The one %s mistake that quietly kills your reach',
        'I studied 100 %s posts — here is the pattern',
        'Why your %s content stopped growing (and the fix)',
        'The %s hook I steal for every viral post',
        '3 %s ideas you can film in under 10 minutes',
    ];

    public function discover(array $hashtags, int $limit): Collection
    {
        $hashtags = array_values(array_filter($hashtags)) ?: ['creators'];
        $posts = collect();
        $perTag = (int) max(1, ceil($limit / count($hashtags)));

        foreach ($hashtags as $tag) {
            for ($i = 0; $i < $perTag && $posts->count() < $limit; $i++) {
                $posts->push($this->fabricate($tag, $i, $hashtags));
            }
        }

        return $posts->take($limit)->values();
    }

    /** @param list<string> $hashtags */
    private function fabricate(string $tag, int $index, array $hashtags): DiscoveredPost
    {
        // A stable pseudo-random seed keeps the mock feed identical between runs.
        $seed = crc32($tag.':'.$index);
        $followers = 8_000 + ($seed % 400_000);
        $likes = 1_200 + ($seed % 90_000);
        $comments = (int) round($likes * (0.01 + (($seed % 40) / 1000)));
        $views = $likes * (5 + ($seed % 12));
        $format = self::FORMATS[$seed % count(self::FORMATS)];
        $hook = sprintf(self::HOOKS[$seed % count(self::HOOKS)], $tag);
        $handle = Str::slug($tag, '').'.'.['studio', 'daily', 'lab', 'hq', 'co'][$seed % 5];

        return new DiscoveredPost(
            sourceUrl: "https://www.instagram.com/p/mock-{$tag}-{$index}/",
            username: $handle,
            displayName: Str::headline($tag).' Creator',
            avatarUrl: "https://i.pravatar.cc/150?u={$handle}",
            followers: $followers,
            caption: $hook."\n\nSave this for your next #{$tag} post.",
            thumbnailUrl: "https://picsum.photos/seed/{$seed}/640/800",
            likes: $likes,
            comments: $comments,
            views: $views,
            publishedAt: CarbonImmutable::now()->subHours(($seed % 240) + 2),
            format: $format,
            hashtags: array_values(array_unique([$tag, ...array_slice($hashtags, 0, 3)])),
            externalId: "mock-{$tag}-{$index}",
        );
    }
}
