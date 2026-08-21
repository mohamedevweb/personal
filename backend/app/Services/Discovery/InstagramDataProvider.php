<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

interface InstagramDataProvider
{
    public function getProfile(string $username): ?DiscoveredProfile;

    /** @return Collection<int, DiscoveredPost> */
    public function getPosts(string $username, int $limit, ?string $externalId = null): Collection;

    /** @return Collection<int, DiscoveredProfile> */
    public function searchAccounts(string $query, int $limit): Collection;

    /** @return Collection<int, DiscoveredProfile> */
    public function getRelatedAccounts(string $externalId, int $limit, ?string $username = null): Collection;
}
