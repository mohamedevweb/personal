<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

interface InstagramDataProvider
{
    public function getProfile(string $username, bool $fresh = false): ?DiscoveredProfile;

    /** @return Collection<int, DiscoveredPost> */
    public function getPosts(string $username, int $limit, ?string $externalId = null): Collection;

    /** @return Collection<int, DiscoveredProfile> */
    public function searchAccounts(string $query, int $limit): Collection;

    /** @return Collection<int, DiscoveredProfile> */
    public function getRelatedAccounts(string $externalId, int $limit, ?string $username = null): Collection;

    /**
     * The current media of a single post. Listing endpoints omit carousel
     * children and video files, and every Instagram url expires, so anything
     * that has to read the media itself refetches it here first.
     */
    public function getPostMedia(string $sourceUrl): ?DiscoveredPostMedia;
}
