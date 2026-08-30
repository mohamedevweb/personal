<?php

namespace App\Services\View;

use App\Models\ContentPost;
use App\Models\User;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\OutlierScore;
use App\Services\Instagram\ContentMedia;
use App\Services\Instagram\InstagramMediaProxy;
use Illuminate\Support\Facades\URL;

class ContentPostView
{
    public function __construct(
        private readonly InstagramMediaProxy $media,
        private readonly OutlierScore $performance,
        private readonly CanonicalCreatorVerticals $verticals,
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
            // What was actually read off the post. The client shows the script
            // and the slide text as the evidence behind the analysis.
            'transcript' => $post->transcript,
            'transcript_status' => $post->transcript_status,
            'carousel_analysis_status' => $post->carousel_analysis_status,
            'carousel_slides' => $this->carouselSlides($post),
            'recommendation_score' => $recommendationScore,
            'is_saved' => $isSaved ?? $user->savedContent()->where('content_post_id', $post->id)->exists(),
            'creator' => [
                'username' => $post->creator->username,
                'display_name' => $post->creator->display_name,
                'avatar_url' => $this->mediaUrl('media.creator', 'creator', $post->creator->id, $post->creator->avatar_url),
                'niche' => $post->creator->niche,
                'niche_topics' => $post->creator->niche_topics ?? [],
                'vertical' => $this->verticals->fromSignals([
                    $post->creator->niche,
                    ...($post->creator->niche_topics ?? []),
                ]),
                'followers' => $post->creator->followers,
                'average_views' => $post->creator->average_views,
            ],
        ];
    }

    /**
     * The slide readings, without the visual notes the model also produces:
     * those are working material for the brief, not something to show.
     *
     * @return list<array{position: int, text: string, role: string}>
     */
    private function carouselSlides(ContentPost $post): array
    {
        return collect(data_get($post->carousel_analysis, 'slides') ?? [])
            ->filter(fn (mixed $slide): bool => is_array($slide) && filled($slide['text'] ?? null))
            ->map(fn (array $slide, int $index): array => [
                'position' => (int) ($slide['position'] ?? $index + 1),
                'text' => (string) $slide['text'],
                'role' => (string) ($slide['role'] ?? ''),
            ])
            ->values()
            ->all();
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
        return collect(ContentMedia::frames($post))
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
