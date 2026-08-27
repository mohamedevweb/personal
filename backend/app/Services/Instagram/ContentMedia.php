<?php

namespace App\Services\Instagram;

use App\Models\ContentPost;
use Illuminate\Support\Collection;

/**
 * The frames of a post, in the order the app shows them.
 *
 * The signed media route addresses a frame by its position in this list, so the
 * list the API hands out and the list the route reads back have to be built the
 * same way. Building them separately let a blank or repeated stored url shift
 * every position after it, and the carousel then served the wrong picture.
 */
class ContentMedia
{
    /** @return list<string> */
    public static function frames(ContentPost $post): array
    {
        $frames = (new Collection($post->media_urls ?? []))
            ->filter(fn (mixed $url): bool => is_string($url) && $url !== '')
            ->unique()
            ->values()
            ->all();

        if ($frames === [] && $post->thumbnail_url) {
            $frames[] = (string) $post->thumbnail_url;
        }

        return $frames;
    }

    public static function frame(ContentPost $post, int $position): ?string
    {
        return self::frames($post)[$position] ?? null;
    }

    /**
     * The first frame is also the post thumbnail, and both are served from the
     * same route, so they share one cached copy rather than downloading the
     * same file twice.
     */
    public static function cacheKey(ContentPost $post, int $position, string $sourceUrl): string
    {
        return $position === 0 && $sourceUrl === $post->thumbnail_url
            ? "content:{$post->id}"
            : "content:{$post->id}:{$position}";
    }
}
