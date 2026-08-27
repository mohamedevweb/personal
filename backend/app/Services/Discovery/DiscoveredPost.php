<?php

namespace App\Services\Discovery;

use Carbon\CarbonImmutable;

/**
 * A single Instagram post returned by a discovery driver, normalized so the
 * ingestion job never sees provider-specific response shapes.
 *
 * @phpstan-type Format 'reel'|'carousel'|'image'
 */
class DiscoveredPost
{
    /**
     * @param  list<string>  $hashtags
     * @param  list<string>  $mediaUrls
     */
    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $username,
        public readonly ?string $displayName,
        public readonly ?string $avatarUrl,
        public readonly int $followers,
        public readonly string $caption,
        public readonly ?string $thumbnailUrl,
        public readonly int $likes,
        public readonly int $comments,
        public readonly int $views,
        public readonly CarbonImmutable $publishedAt,
        public readonly string $format,
        public readonly array $hashtags,
        public readonly ?string $externalId = null,
        public readonly int $shares = 0,
        public readonly array $metadata = [],
        public readonly array $mediaUrls = [],
        public readonly ?string $videoUrl = null,
    ) {}

    /**
     * Likes plus comments — the only engagement both Reels and images expose, so
     * it is the common denominator the performance ratio is built on.
     */
    public function engagement(): int
    {
        return $this->likes + $this->comments + $this->shares;
    }
}
