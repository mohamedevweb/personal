<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

class MockInstagramDataProvider implements InstagramDataProvider
{
    public function __construct(
        private readonly MockInstagramDiscoveryService $discovery,
        private readonly MockProfileScraperService $profiles,
    ) {}

    public function getProfile(string $username, bool $fresh = false): ?DiscoveredProfile
    {
        return $this->profiles->profiles([$username], (int) config('services.discovery.profile_posts'))->first();
    }

    public function getPosts(string $username, int $limit, ?string $externalId = null): Collection
    {
        return $this->profiles->profiles([$username], $limit)->first()?->posts ?? collect();
    }

    public function searchAccounts(string $query, int $limit): Collection
    {
        return $this->discovery->discover([$query], $limit)
            ->unique(fn (DiscoveredPost $post): string => $post->username)
            ->map(fn (DiscoveredPost $post): DiscoveredProfile => new DiscoveredProfile(
                username: $post->username,
                displayName: $post->displayName,
                avatarUrl: $post->avatarUrl,
                followers: $post->followers,
                posts: collect(),
                externalId: $post->username,
            ))
            ->values();
    }

    /**
     * The mock CDN mirrors the real shape: a /reel/ url answers with a video,
     * anything else with a handful of slides, so the analysis pipeline can be
     * exercised end to end without a provider key.
     */
    public function getPostMedia(string $sourceUrl): ?DiscoveredPostMedia
    {
        $seed = md5($sourceUrl);

        if (str_contains($sourceUrl, '/reel/')) {
            return new DiscoveredPostMedia(
                videoUrl: "https://mock.cdninstagram.com/{$seed}.mp4",
                videoDurationSeconds: 45,
            );
        }

        return new DiscoveredPostMedia(
            mediaUrls: array_map(
                fn (int $position): string => "https://mock.cdninstagram.com/{$seed}-{$position}.jpg",
                range(1, 4),
            ),
        );
    }

    public function getRelatedAccounts(string $externalId, int $limit, ?string $username = null): Collection
    {
        $usernames = collect(range(1, max(1, $limit)))
            ->map(fn (int $index): string => $externalId.'.related'.$index)
            ->all();

        return $this->profiles->profiles($usernames, 0)
            ->map(fn (DiscoveredProfile $profile): DiscoveredProfile => new DiscoveredProfile(
                username: $profile->username,
                displayName: $profile->displayName,
                avatarUrl: $profile->avatarUrl,
                followers: $profile->followers,
                posts: collect(),
                bio: $profile->bio,
                externalId: $profile->username,
            ));
    }
}
