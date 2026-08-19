<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

interface ProfileDiscoveryService
{
    /**
     * Scrape the given accounts and their recent posts so their real follower
     * count and engagement can be measured.
     *
     * @param  list<string>  $usernames  Bare handles, without the leading '@'.
     * @param  int  $postsPerProfile  How many recent posts to pull per account.
     * @return Collection<int, DiscoveredProfile>
     */
    public function profiles(array $usernames, int $postsPerProfile): Collection;
}
