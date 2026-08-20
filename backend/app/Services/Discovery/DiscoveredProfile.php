<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

/**
 * An Instagram account plus its recent posts, returned by a profile-scraping
 * driver. Unlike a hashtag scrape, the follower count here is the account's real
 * value, so it can anchor an engagement-rate calculation.
 */
class DiscoveredProfile
{
    /** @param Collection<int, DiscoveredPost> $posts */
    public function __construct(
        public readonly string $username,
        public readonly ?string $displayName,
        public readonly ?string $avatarUrl,
        public readonly int $followers,
        public readonly Collection $posts,
        public readonly ?string $bio = null,
    ) {}

    /**
     * Average engagement rate across the recent posts, in percent:
     * mean of (likes + comments) / followers * 100.
     *
     * Returns 0 when the follower count or post set is missing, so an
     * un-measurable account sorts to the bottom instead of blowing up.
     */
    public function engagementRate(): float
    {
        if ($this->followers < 1 || $this->posts->isEmpty()) {
            return 0.0;
        }

        $mean = $this->posts
            ->map(fn (DiscoveredPost $post): float => $post->engagement() / $this->followers * 100)
            ->avg();

        return round((float) $mean, 2);
    }
}
