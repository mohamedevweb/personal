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
 * Stage one of discovery: expand the creator's niche into hashtags, scrape those
 * pages, and record the accounts and posts behind them.
 *
 * A hashtag page tells you a post exists, not whether it did well — the page has
 * no follower count and no sense of what that account normally gets. So nothing
 * here is scored. Rows land unmeasured, MeasureAccountEngagement scrapes the
 * accounts themselves, and the score is written there against a real baseline.
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

        $niche = $user->creatorProfile?->niche ?: Str::headline($hashtags[0]);

        foreach ($posts as $post) {
            $this->store($post, $niche);
        }

        // Measuring the accounts is what makes these posts rankable at all, so it
        // is dispatched with the exact handles just found rather than a niche
        // label — which the profile scrape is about to overwrite anyway.
        MeasureAccountEngagement::dispatch(
            $posts->map(fn (DiscoveredPost $post): string => $post->username)->unique()->values()->all(),
        );
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

    private function store(DiscoveredPost $post, string $niche): void
    {
        // firstOrCreate, not updateOrCreate: an account already measured has a niche
        // read from its own bio and captions, and a follower count from its profile.
        // A hashtag result knows neither and must not overwrite either.
        $creator = Creator::query()->firstOrCreate(
            ['username' => $post->username],
            [
                'display_name' => $post->displayName ?: $post->username,
                'avatar_url' => $post->avatarUrl,
                // A placeholder borrowed from the user who found the account. It
                // holds only until the profile scrape classifies the account itself.
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
                'tags' => $post->hashtags,
                // performance_ratio, outlier_score and engagement_rate are absent on
                // purpose. Nothing here can say whether this beat the creator's own
                // average, and a guess is exactly what put flat posts in the feed.
                // why_it_works and the analysis fields are left to their defaults.
                // Writing them here would blank the breakdown of a post already
                // measured or already opened by a creator.
            ],
        );
    }

    private function hook(DiscoveredPost $post, string $niche): string
    {
        $firstLine = trim((string) Str::of($post->caption)->before("\n"));

        return Str::limit($firstLine !== '' ? $firstLine : "New {$niche} post", 120);
    }
}
