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
