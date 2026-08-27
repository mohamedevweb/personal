<?php

namespace App\Jobs\Content;

use App\Models\ContentPost;
use App\Services\Instagram\ContentMedia;
use App\Services\Instagram\InstagramMediaProxy;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Downloads every frame of a post while its Instagram links still resolve.
 *
 * Those links carry an expiry a few days out. The feed keeps the first frame
 * alive on its own, because rendering a card fetches it, but the rest of a
 * carousel is only ever requested when a reader clicks onto it — usually after
 * the link is dead, which is why older carousels showed a broken picture from
 * the second frame on. Copying them at discovery time is what makes them
 * viewable for as long as the post is in the feed.
 */
class CacheContentMedia implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $contentPostId) {}

    public function uniqueId(): string
    {
        return (string) $this->contentPostId;
    }

    public function handle(InstagramMediaProxy $media): void
    {
        $post = ContentPost::query()->find($this->contentPostId);

        if (! $post) {
            return;
        }

        foreach (ContentMedia::frames($post) as $position => $sourceUrl) {
            $media->warm($sourceUrl, ContentMedia::cacheKey($post, $position, $sourceUrl));
        }
    }
}
