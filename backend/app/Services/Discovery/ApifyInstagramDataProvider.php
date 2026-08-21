<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApifyInstagramDataProvider implements InstagramDataProvider
{
    public function __construct(
        private readonly ApifyInstagramDiscoveryService $discovery,
        private readonly ApifyProfileScraperService $profiles,
    ) {}

    public function getProfile(string $username): ?DiscoveredProfile
    {
        return $this->profiles->profiles([$username], (int) config('services.discovery.profile_posts'))->first();
    }

    public function getPosts(string $username, int $limit, ?string $externalId = null): Collection
    {
        return $this->profiles->profiles([$username], $limit)->first()?->posts ?? collect();
    }

    public function searchAccounts(string $query, int $limit): Collection
    {
        $tag = Str::of($query)->lower()->replaceMatches('/[^a-z0-9]/', '')->value();

        return $this->discovery->discover([$tag], $limit)
            ->unique(fn (DiscoveredPost $post): string => $post->username)
            ->map(fn (DiscoveredPost $post): DiscoveredProfile => new DiscoveredProfile(
                username: $post->username,
                displayName: $post->displayName,
                avatarUrl: $post->avatarUrl,
                followers: $post->followers,
                posts: collect(),
            ))
            ->values();
    }

    public function getRelatedAccounts(string $externalId, int $limit, ?string $username = null): Collection
    {
        return collect();
    }
}
