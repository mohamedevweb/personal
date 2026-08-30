<?php

namespace App\Services\Content;

use App\Models\ContentPost;
use App\Services\Instagram\ContentMedia;

/**
 * The shape a remix takes, read off the post it is borrowed from.
 *
 * A creator never picks it: a carousel is remixed as a carousel, a reel as a
 * reel, and a single image as a one-slide carousel. Borrowing a structure only
 * means anything when the draft has the same shape as the post that proved it.
 */
class RemixFormat
{
    /** Instagram's own ceiling, and the one the editor enforces. */
    public const MAX_SLIDES = 20;

    /** What a carousel gets when its own slides could never be counted. */
    public const UNKNOWN_SLIDE_COUNT = 6;

    public static function for(ContentPost $post): string
    {
        return self::isReel($post) ? 'reel' : 'carousel';
    }

    /**
     * How many slides the draft must have: exactly as many as the post it
     * copies. A single image is a carousel of one, and a carousel we still only
     * know the cover of falls back to a normal deck rather than pretending the
     * original had one slide.
     */
    public static function slideCount(ContentPost $post): int
    {
        $format = self::format($post);

        if ($format === 'image') {
            return 1;
        }

        $frames = count(ContentMedia::frames($post));

        return $frames < 2
            ? self::UNKNOWN_SLIDE_COUNT
            : min($frames, self::MAX_SLIDES);
    }

    private static function isReel(ContentPost $post): bool
    {
        $format = self::format($post);

        return str_contains($format, 'reel') || str_contains($format, 'video');
    }

    private static function format(ContentPost $post): string
    {
        return mb_strtolower(trim((string) $post->format));
    }
}
