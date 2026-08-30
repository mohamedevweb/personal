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

    /**
     * The text read off the slides of a carousel, in reading order. Empty until
     * the visual analysis has run, which is the normal state of a fresh post.
     */
    public static function slideText(ContentPost $post): string
    {
        return (new Collection(data_get($post->carousel_analysis, 'slides') ?? []))
            ->filter(fn (mixed $slide): bool => is_array($slide) && filled($slide['text'] ?? null))
            ->map(fn (array $slide): string => 'Slide '.($slide['position'] ?? '?').': '.trim((string) $slide['text']))
            ->implode("\n");
    }

    /**
     * The same slides, read as a plan rather than as prose: what each one does
     * in the story and what it looks like doing it. A carousel remix follows it
     * position by position, so the order and the numbering are the point.
     */
    public static function slidePlan(ContentPost $post): string
    {
        return (new Collection(data_get($post->carousel_analysis, 'slides') ?? []))
            ->filter(fn (mixed $slide): bool => is_array($slide))
            ->map(function (array $slide, int $index): string {
                $parts = ['Slide '.($slide['position'] ?? $index + 1)];

                foreach (['role' => 'Role', 'text' => 'Text on the slide', 'visual_description' => 'Visual'] as $key => $label) {
                    if (filled($slide[$key] ?? null)) {
                        $parts[] = $label.': '.trim((string) $slide[$key]);
                    }
                }

                return implode("\n  ", $parts);
            })
            ->implode("\n");
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
