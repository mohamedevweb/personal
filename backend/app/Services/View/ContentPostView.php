<?php

namespace App\Services\View;

use App\Models\ContentPost;
use App\Models\User;
use App\Services\Discovery\OutlierScore;
use App\Services\Instagram\InstagramMediaProxy;
use Illuminate\Support\Facades\URL;

class ContentPostView
{
    public function __construct(
        private readonly InstagramMediaProxy $media,
        private readonly OutlierScore $performance,
    ) {}

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
            'source_url' => $post->source_url,
            'thumbnail_url' => $this->mediaUrl('media.content', 'content', $post->id, $post->thumbnail_url),
            'video_url' => $this->mediaUrl('media.content.video', 'content', $post->id, $post->video_url),
            'media_urls' => $this->contentMediaUrls($post),
            'views' => $post->views,
            'likes' => $post->likes,
            'comments' => $post->comments,
            'shares' => $post->shares,
            'published_at' => $post->published_at,
            'performance_ratio' => $post->performance_ratio,
            'outlier_score' => $post->outlier_score,
            'benchmark' => $this->benchmark($post),
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

    /**
     * What the ratio was actually measured against, so the app can say it out loud
     * rather than asking the reader to trust a number.
     *
     * @return array{format: ?string, posts: int, views: ?int, engagement: ?int}
     */
    private function benchmark(ContentPost $post): array
    {
        $against = $this->performance->against(
            $post->creator->performance_baselines ?? [],
            $post->format,
        );

        return [
            'format' => $against['format'],
            'posts' => $against['posts'],
            'views' => $against['views'] === null ? null : (int) round($against['views']),
            'engagement' => $against['engagement'] === null ? null : (int) round($against['engagement']),
        ];
    }

    private function mediaUrl(string $route, string $parameter, int $id, ?string $sourceUrl): ?string
    {
        if (! $sourceUrl || ! $this->media->supports($sourceUrl)) {
            return $sourceUrl;
        }

        return $this->signedMediaUrl($route, [$parameter => $id]);
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

                return $this->signedMediaUrl('media.content.item', [
                    'content' => $post->id,
                    'position' => $position,
                ]);
            })
            ->all();
    }

    /** @param array<string, int> $parameters */
    private function signedMediaUrl(string $route, array $parameters): string
    {
        // A fixed expiry within the current hour keeps the URL stable across feed
        // refreshes, allowing the browser to reuse its cached image response.
        $expiresAt = now()
            ->addHours(max(1, (int) config('services.instagram_media_proxy.signature_hours')))
            ->endOfHour();
        $path = URL::temporarySignedRoute($route, $expiresAt, $parameters, absolute: false);

        return rtrim((string) config('app.url'), '/').$path;
    }
}
