<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

interface ContentDiscoveryService
{
    /**
     * Find recent, high-signal posts for the given hashtags.
     *
     * @param  list<string>  $hashtags  Bare tags, without the leading '#'.
     * @return Collection<int, DiscoveredPost>
     */
    public function discover(array $hashtags, int $limit): Collection;
}
