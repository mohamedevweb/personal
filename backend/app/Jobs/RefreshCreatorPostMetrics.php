<?php

namespace App\Jobs;

use App\Exceptions\ContentDiscoveryException;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use App\Services\Discovery\PostMetricsLifecycle;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RefreshCreatorPostMetrics implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $creatorId) {}

    public function uniqueId(): string
    {
        return (string) $this->creatorId;
    }

    public function handle(
        InstagramDataProvider $provider,
        OutlierScore $performance,
        PostMetricsLifecycle $lifecycle,
    ): void {
        $creator = Creator::query()->find($this->creatorId);

        if (! $creator || $creator->safety_status !== ContentSafetyDecision::ALLOWED) {
            return;
        }

        // A full creator refresh supplies the same recent-post metrics and also
        // discovers new publications, so it always wins over this narrower call.
        if (! $creator->next_scrape_at || $creator->next_scrape_at->isPast()) {
            return;
        }

        $duePosts = $creator->posts()
            ->where('tracking_status', '!=', 'stopped')
            ->whereNotNull('next_metrics_scrape_at')
            ->where('next_metrics_scrape_at', '<=', now())
            ->orderBy('next_metrics_scrape_at')
            ->limit((int) config('instagram_scraping.metrics_posts_per_creator'))
            ->get();

        if ($duePosts->isEmpty()) {
            return;
        }

        try {
            $posts = $provider->getPosts(
                $creator->username,
                max((int) config('services.discovery.profile_posts'), $duePosts->count()),
                $creator->instagram_user_id,
            );
        } catch (ContentDiscoveryException $exception) {
            $duePosts->each(fn (ContentPost $post) => $lifecycle->postponeAfterFailure($post, now()));
            Log::warning('Post metrics refresh skipped.', ['creator' => $creator->username, 'exception' => $exception]);

            return;
        }

        $received = $this->index($posts);
        $capturedAt = now();

        foreach ($duePosts as $post) {
            $discovered = $this->match($received, $post);

            if (! $discovered) {
                $lifecycle->markUnavailable($post, $capturedAt);

                continue;
            }

            $post->forceFill([
                'views' => $discovered->views,
                'likes' => $discovered->likes,
                'comments' => $discovered->comments,
                'shares' => $discovered->shares,
                'metadata' => array_replace_recursive($post->metadata ?? [], $discovered->metadata),
                'last_fetched_at' => $capturedAt,
                'metrics_updated_at' => $capturedAt,
            ])->save();

            $lifecycle->recordRefresh($post, $capturedAt);
            $engagement = $post->likes + $post->comments + $post->shares;
            $outlier = $performance->score($post, $creator->performance_baselines ?? []);
            $post->forceFill([
                'outlier_score' => $outlier,
                'performance_ratio' => min(9999.99, $outlier),
                'engagement_rate' => $performance->engagementRate($engagement, $creator->followers),
                'measured_at' => $capturedAt,
            ])->save();
            $lifecycle->reschedule($post->loadMissing('creator'), $capturedAt);
        }
    }

    /** @param Collection<int, DiscoveredPost> $posts @return array<string, DiscoveredPost> */
    private function index(Collection $posts): array
    {
        $indexed = [];

        foreach ($posts as $post) {
            if ($post->externalId) {
                $indexed['id:'.$post->externalId] = $post;
            }

            $indexed['url:'.$post->sourceUrl] = $post;
        }

        return $indexed;
    }

    /** @param array<string, DiscoveredPost> $posts */
    private function match(array $posts, ContentPost $post): ?DiscoveredPost
    {
        if ($post->instagram_media_id && isset($posts['id:'.$post->instagram_media_id])) {
            return $posts['id:'.$post->instagram_media_id];
        }

        return $post->source_url ? ($posts['url:'.$post->source_url] ?? null) : null;
    }
}
