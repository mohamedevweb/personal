<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class ContentPostView
{
    public function __construct(private readonly InstagramMediaProxy $media) {}

    /**
     * @param  bool|null  $isSaved  Pass a known value when rendering a batch; leaving
     *                              it null costs one query per post.
     * @return array<string, mixed>
     */
    public function make(
        ContentPost $post,
        User $user,
        ?float $recommendationScore = null,
        ?bool $isSaved = null,
    ): array {
        $post->loadMissing('creator');

        return [
            'id' => $post->id,
            'format' => $post->format,
            'hook' => $post->hook,
            'caption' => $post->caption,
            'thumbnail_url' => $this->mediaUrl('media.content', 'content', $post->id, $post->thumbnail_url),
            'media_urls' => $this->contentMediaUrls($post),
            'views' => $post->views,
            'likes' => $post->likes,
            'comments' => $post->comments,
            'shares' => $post->shares,
            'published_at' => $post->published_at,
            'performance_ratio' => $post->performance_ratio,
            'outlier_score' => $post->outlier_score,
            'engagement_rate' => $post->engagement_rate,
            'tags' => $post->tags ?? [],
            'why_it_works' => $post->why_it_works,
            'hook_analysis' => $post->hook_analysis,
            'structure_analysis' => $post->structure_analysis,
            'recommendation_score' => $recommendationScore,
            'is_saved' => $isSaved ?? $user->savedContent()->where('content_post_id', $post->id)->exists(),
            'creator' => [
                'username' => $post->creator->username,
                'display_name' => $post->creator->display_name,
                'avatar_url' => $this->mediaUrl('media.creator', 'creator', $post->creator->id, $post->creator->avatar_url),
                'niche' => $post->creator->niche,
                'niche_topics' => $post->creator->niche_topics ?? [],
                'followers' => $post->creator->followers,
                'average_views' => $post->creator->average_views,
            ],
        ];
    }

    private function mediaUrl(string $route, string $parameter, int $id, ?string $sourceUrl): ?string
    {
        if (! $sourceUrl || ! $this->media->supports($sourceUrl)) {
            return $sourceUrl;
        }

        $path = URL::temporarySignedRoute(
            $route,
            now()->addHours((int) config('services.instagram_media_proxy.signature_hours')),
            [$parameter => $id],
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$path;
    }

    /** @return list<string> */
    private function contentMediaUrls(ContentPost $post): array
    {
        $sourceUrls = collect($post->media_urls ?? [])
            ->filter(fn (mixed $url): bool => is_string($url) && $url !== '')
            ->unique()
            ->values();

        if ($sourceUrls->isEmpty() && $post->thumbnail_url) {
            $sourceUrls->push($post->thumbnail_url);
        }

        return $sourceUrls
            ->map(function (string $sourceUrl, int $position) use ($post): string {
                if (! $this->media->supports($sourceUrl)) {
                    return $sourceUrl;
                }

                // The first carousel frame is also the post thumbnail. Reusing
                // its stable route preserves the cache created before carousel
                // navigation existed and avoids downloading the same file twice.
                if ($position === 0 && $sourceUrl === $post->thumbnail_url) {
                    return (string) $this->mediaUrl('media.content', 'content', $post->id, $sourceUrl);
                }

                $path = URL::temporarySignedRoute(
                    'media.content.item',
                    now()->addHours((int) config('services.instagram_media_proxy.signature_hours')),
                    ['content' => $post->id, 'position' => $position],
                    absolute: false,
                );

                return rtrim((string) config('app.url'), '/').$path;
            })
            ->all();
    }
}
