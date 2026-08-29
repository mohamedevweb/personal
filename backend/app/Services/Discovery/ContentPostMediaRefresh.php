<?php

namespace App\Services\Discovery;

use App\Models\ContentPost;
use App\Services\Instagram\ContentMedia;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Puts usable media back on a post right before something needs to read it.
 *
 * Discovery lists posts from an endpoint that returns neither the children of a
 * carousel nor the video file of a reel, and the urls it does return are signed
 * and expire within days. Both are refetched here, one post at a time, and only
 * for a post a member actually opened: the refresh costs a provider credit, so
 * it follows real usage rather than the size of the catalogue.
 */
class ContentPostMediaRefresh
{
    public function __construct(private readonly InstagramDataProvider $provider) {}

    /**
     * Returns whether the post now carries media the analysis can read. The post
     * instance is updated in place, so callers can keep using the one they hold.
     */
    public function ensure(ContentPost $post): bool
    {
        if (! $this->needs($post)) {
            return $this->has($post);
        }

        if (! config('services.discovery.media_refresh.enabled') || $this->attemptedRecently($post)) {
            return $this->has($post);
        }

        try {
            $media = $this->provider->getPostMedia($post->source_url);
        } catch (Throwable $exception) {
            // A provider outage must never turn into a failed analysis: the post
            // simply stays on the media it already had.
            Log::warning('Post media refresh failed.', [
                'content_post_id' => $post->id,
                'exception' => $exception,
            ]);
            $media = null;
        }

        // The timestamp is written even when the refetch came back empty. It is
        // what stops a post with no readable media from being paid for again on
        // every open.
        $attributes = ['media_refreshed_at' => now()];

        if ($media && $media->mediaUrls !== []) {
            $attributes['media_urls'] = $media->mediaUrls;
        }

        if ($media?->videoUrl) {
            $attributes['video_url'] = $media->videoUrl;
        }

        if ($media?->videoDurationSeconds) {
            $attributes['metadata'] = [
                ...($post->metadata ?? []),
                'video_duration' => $media->videoDurationSeconds,
            ];
        }

        $post->forceFill($attributes)->save();

        return $this->has($post);
    }

    /**
     * A reel is always refetched before it is read: its stored file url is
     * signed and long dead by the time a member opens the post. A carousel is
     * only refetched while it has nothing but its cover, because the listing
     * endpoint drops the other slides.
     */
    private function needs(ContentPost $post): bool
    {
        return match ($this->format($post)) {
            'reel' => blank($post->video_url) || $this->expired($post),
            'carousel' => count(ContentMedia::frames($post)) < 2,
            default => false,
        };
    }

    private function has(ContentPost $post): bool
    {
        return match ($this->format($post)) {
            'reel' => filled($post->video_url),
            'carousel' => ContentMedia::frames($post) !== [],
            default => false,
        };
    }

    private function expired(ContentPost $post): bool
    {
        $hours = max(1, (int) config('services.discovery.media_refresh.url_ttl_hours'));

        return $post->media_refreshed_at === null
            || $post->media_refreshed_at->lt(now()->subHours($hours));
    }

    private function attemptedRecently(ContentPost $post): bool
    {
        $hours = max(1, (int) config('services.discovery.media_refresh.cooldown_hours'));

        return $post->media_refreshed_at !== null
            && $post->media_refreshed_at->gt(now()->subHours($hours));
    }

    /** Legacy rows carry a capitalized format, and the guards below compare exactly. */
    private function format(ContentPost $post): string
    {
        return mb_strtolower((string) $post->format);
    }
}
