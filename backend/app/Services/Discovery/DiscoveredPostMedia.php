<?php

namespace App\Services\Discovery;

/**
 * The playable and readable media of one post, refetched on demand.
 *
 * Discovery lists posts from an endpoint that omits carousel children and video
 * files, and the urls it does return expire. This is the narrow, per-post shape
 * the analysis pipeline needs, kept separate from DiscoveredPost so a refresh
 * can never overwrite metrics or captions.
 */
class DiscoveredPostMedia
{
    /** @param list<string> $mediaUrls */
    public function __construct(
        public readonly array $mediaUrls = [],
        public readonly ?string $videoUrl = null,
        public readonly ?int $videoDurationSeconds = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->mediaUrls === [] && $this->videoUrl === null;
    }
}
