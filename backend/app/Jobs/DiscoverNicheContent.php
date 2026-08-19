<?php

namespace App\Jobs;

use App\Exceptions\ContentDiscoveryException;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\DiscoveredHashtag;
use App\Models\User;
use App\Services\Discovery\ContentDiscoveryService;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\NicheExpansionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fills the feed with real niche content: expand the creator's niche into
 * hashtags, scrape recent posts, then upsert creators and posts. Posts are stored
 * with lightweight, heuristic analysis; the full LLM breakdown is generated on
 * demand when the creator opens a post.
 */
class DiscoverNicheContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public readonly int $userId) {}

    public function handle(NicheExpansionService $expansion, ContentDiscoveryService $discovery): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $hashtags = $expansion->hashtagsFor($user);

        if ($hashtags === []) {
            return;
        }

        // Only scrape hashtags whose cooldown has lapsed. The feed reads a shared
        // pool, so a niche another user already refreshed needs no second scrape.
        $due = $this->dueHashtags($hashtags);

        if ($due === []) {
            return;
        }

        try {
            $posts = $discovery->discover($due, (int) config('services.discovery.apify.results_limit'));
        } catch (ContentDiscoveryException $exception) {
            // Discovery is best-effort: log and leave the existing feed untouched
            // rather than failing the queue and blocking the sync pipeline.
            Log::warning('Niche discovery skipped.', ['user' => $user->id, 'exception' => $exception]);

            return;
        }

        // The scrape ran (even if it returned nothing), so start the cooldown to
        // avoid paying to re-scrape the same hashtags on the next sync.
        $this->markScraped($due);

        if ($posts->isEmpty()) {
            return;
        }

        // Performance is relative: a post is "outperforming" versus the median
        // engagement of the batch it was discovered in.
        $median = max(1, (int) $posts->map(fn (DiscoveredPost $p): int => $p->engagement())->median());
        $niche = $user->creatorProfile?->niche ?: Str::headline($hashtags[0]);

        foreach ($posts as $post) {
            $this->store($post, $niche, $median);
        }

        // Hashtag discovery only seeds the creator list. Measuring their real
        // follower counts and engagement rate — what ranks "the hottest accounts"
        // — happens per account in a follow-up job.
        MeasureAccountEngagement::dispatch($niche);
    }

    /**
     * @param  list<string>  $hashtags
     * @return list<string>
     */
    private function dueHashtags(array $hashtags): array
    {
        $recent = DiscoveredHashtag::query()
            ->whereIn('tag', $hashtags)
            ->where('last_scraped_at', '>', now()->subDays((int) config('services.discovery.cooldown_days')))
            ->pluck('tag')
            ->all();

        return array_values(array_diff($hashtags, $recent));
    }

    /** @param list<string> $hashtags */
    private function markScraped(array $hashtags): void
    {
        foreach ($hashtags as $tag) {
            DiscoveredHashtag::query()->updateOrCreate(['tag' => $tag], ['last_scraped_at' => now()]);
        }
    }

    private function store(DiscoveredPost $post, string $niche, int $median): void
    {
        $creator = Creator::query()->updateOrCreate(
            ['username' => $post->username],
            [
                'display_name' => $post->displayName ?: $post->username,
                'avatar_url' => $post->avatarUrl,
                'niche' => $niche,
                'followers' => $post->followers,
                'average_views' => $post->views,
                'average_likes' => $post->likes,
            ],
        );

        ContentPost::query()->updateOrCreate(
            ['source_url' => $post->sourceUrl],
            [
                'creator_id' => $creator->id,
                'platform' => 'instagram',
                'format' => $post->format,
                'hook' => $this->hook($post, $niche),
                'caption' => $post->caption,
                'thumbnail_url' => $post->thumbnailUrl,
                'views' => $post->views,
                'likes' => $post->likes,
                'comments' => $post->comments,
                'published_at' => $post->publishedAt,
                'performance_ratio' => round($post->engagement() / $median, 2),
                'tags' => $post->hashtags,
                'why_it_works' => $this->whyItWorks($post),
                // Left empty on purpose: the full breakdown is generated lazily the
                // first time a creator opens the post.
                'hook_analysis' => '',
                'structure_analysis' => '',
            ],
        );
    }

    private function hook(DiscoveredPost $post, string $niche): string
    {
        $firstLine = trim((string) Str::of($post->caption)->before("\n"));

        return Str::limit($firstLine !== '' ? $firstLine : "New {$niche} post", 120);
    }

    private function whyItWorks(DiscoveredPost $post): string
    {
        return 'Outperforming its niche with '.number_format($post->likes).' likes and '
            .number_format($post->comments).' comments.';
    }
}
