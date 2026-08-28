<?php

namespace App\Services\Discovery;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deterministic sample profiles so account-level engagement is testable without
 * a paid provider request. Metrics are derived from a hash of the username, so the same
 * account always yields the same, stable engagement rate.
 */
class MockProfileScraperService implements ProfileDiscoveryService
{
    private const FORMATS = ['reel', 'carousel', 'image'];

    public function profiles(array $usernames, int $postsPerProfile): Collection
    {
        $usernames = array_values(array_unique(array_filter($usernames)));

        return collect($usernames)
            ->map(fn (string $username): DiscoveredProfile => $this->fabricate($username, $postsPerProfile))
            ->values();
    }

    private function fabricate(string $username, int $postsPerProfile): DiscoveredProfile
    {
        $seed = crc32($username);
        $followers = 8_000 + ($seed % 400_000);

        $posts = collect(range(0, max(1, $postsPerProfile) - 1))
            ->map(fn (int $index): DiscoveredPost => $this->fabricatePost($username, $followers, $seed, $index))
            ->values();

        return new DiscoveredProfile(
            username: $username,
            displayName: Str::headline(Str::before($username, '.')).' Creator',
            avatarUrl: "https://i.pravatar.cc/150?u={$username}",
            followers: $followers,
            posts: $posts,
            bio: 'Sample bio for '.$username,
            externalId: $username,
            metadata: ['country_code' => 'US'],
        );
    }

    private function fabricatePost(string $username, int $followers, int $seed, int $index): DiscoveredPost
    {
        $postSeed = crc32($username.':'.$index);
        $likes = 1_200 + ($postSeed % 90_000);
        $comments = (int) round($likes * (0.01 + (($postSeed % 40) / 1000)));
        $views = $likes * (5 + ($postSeed % 12));

        $format = self::FORMATS[$postSeed % count(self::FORMATS)];
        $thumbnailUrl = "https://picsum.photos/seed/{$postSeed}/640/800";

        return new DiscoveredPost(
            sourceUrl: "https://www.instagram.com/p/mock-{$username}-{$index}/",
            username: $username,
            displayName: Str::headline(Str::before($username, '.')).' Creator',
            avatarUrl: "https://i.pravatar.cc/150?u={$username}",
            followers: $followers,
            caption: "Recent post {$index} from {$username}",
            thumbnailUrl: $thumbnailUrl,
            likes: $likes,
            comments: $comments,
            views: $views,
            publishedAt: CarbonImmutable::now()->subHours(($postSeed % 240) + 2),
            format: $format,
            hashtags: [],
            externalId: "mock-{$username}-{$index}",
            metadata: $format === 'reel' ? ['video_duration' => 30 + ($postSeed % 60)] : [],
            mediaUrls: $format === 'carousel'
                ? collect(range(0, 4))->map(fn (int $slide): string => "https://picsum.photos/seed/{$postSeed}-{$slide}/640/800")->all()
                : [],
            // A host on the real allowlist, so the local pipeline behaves like
            // production: the download is attempted and fails, never skipped.
            videoUrl: $format === 'reel' ? "https://mock.cdninstagram.com/{$postSeed}.mp4" : null,
        );
    }
}
