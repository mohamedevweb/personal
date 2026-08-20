<?php

namespace App\Jobs;

use App\Exceptions\ContentDiscoveryException;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\PostPerformance;
use App\Services\Discovery\ProfileDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns seeded accounts into ranked, scored ones. Re-scraping a whole profile is
 * the only way to learn three things a hashtag page never exposes: the real
 * follower count, what the account is actually about, and the median engagement
 * its posts normally get.
 *
 * That median is the point. Every post of the account — including ones found
 * earlier through a hashtag — is (re)scored against it, so a post in the feed
 * means "this beat its own creator", not "this came from a big account".
 */
class MeasureAccountEngagement implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Room for every chunk to burn its full HTTP timeout without the job being killed. */
    public int $timeout = 900;

    /** @param list<string> $usernames Accounts to measure, bare handles. */
    public function __construct(public readonly array $usernames) {}

    public function handle(ProfileDiscoveryService $profiles, CreatorNicheService $niches, PostPerformance $performance): void
    {
        $due = $this->dueUsernames();

        if ($due === []) {
            return;
        }

        // Scraped in chunks rather than one call. A synchronous Apify run is capped
        // at five minutes, and a full batch of accounts with their recent posts does
        // not reliably finish inside it — so one oversized request loses the whole
        // batch. Chunking also contains the damage: a chunk that fails costs only
        // its own accounts, and the rest are still measured.
        foreach (array_chunk($due, max(1, (int) config('services.discovery.measure_chunk'))) as $chunk) {
            try {
                $scraped = $profiles->profiles($chunk, (int) config('services.discovery.profile_posts'));
            } catch (ContentDiscoveryException $exception) {
                // Measurement is best-effort: log and leave existing rankings intact
                // rather than failing the queue.
                Log::warning('Account engagement measurement skipped.', ['accounts' => $chunk, 'exception' => $exception]);

                continue;
            }

            foreach ($scraped as $profile) {
                $this->measure($profile, $niches, $performance);
            }
        }
    }

    /**
     * The requested accounts whose measurement cooldown has lapsed. An account
     * never measured is always due; the batch cap keeps a large niche from
     * spending the whole Apify budget in one pass.
     *
     * @return list<string>
     */
    private function dueUsernames(): array
    {
        $usernames = array_values(array_unique(array_filter($this->usernames)));

        if ($usernames === []) {
            return [];
        }

        $fresh = Creator::query()
            ->whereIn('username', $usernames)
            ->where('last_measured_at', '>', now()->subDays((int) config('services.discovery.measure_cooldown_days')))
            ->pluck('username')
            ->all();

        return array_slice(
            array_values(array_diff($usernames, $fresh)),
            0,
            (int) config('services.discovery.measure_batch'),
        );
    }

    private function measure(DiscoveredProfile $profile, CreatorNicheService $niches, PostPerformance $performance): void
    {
        if ($profile->posts->isEmpty()) {
            return;
        }

        $baseline = $performance->baseline($profile->posts);
        $existing = Creator::query()->where('username', $profile->username)->first();
        $qualified = $profile->followers >= (int) config('services.discovery.min_followers');

        $attributes = [
            'display_name' => $profile->displayName ?: $profile->username,
            'avatar_url' => $profile->avatarUrl,
            'followers' => $profile->followers,
            'average_views' => (int) $profile->posts->map(fn (DiscoveredPost $p): int => $p->views)->avg(),
            'average_likes' => (int) $profile->posts->map(fn (DiscoveredPost $p): int => $p->likes)->avg(),
            'baseline_engagement' => $baseline,
            'avg_engagement_rate' => $profile->engagementRate(),
            'last_measured_at' => now(),
        ];

        if ($qualified) {
            $attributes += $this->niche($profile, $niches, $existing);
        } elseif (! $existing) {
            // Classifying costs a model call, and an account that cannot reach a feed
            // is not worth one. Its handle stands in until it clears the floor.
            $attributes['niche'] = $profile->username;
        }

        $creator = Creator::query()->updateOrCreate(['username' => $profile->username], $attributes);

        foreach ($profile->posts as $post) {
            $this->storePost($creator, $post);
        }

        // An account under the follower floor is measured — so the cooldown stops us
        // re-scraping it daily — but never scored. Its posts stay unmeasured, which
        // is what keeps them out of every feed. A ratio over a two-like median is
        // arithmetic, not evidence, and that is what was reaching creators.
        $qualified
            ? $this->score($creator, $baseline, $performance)
            : $this->disqualify($creator);
    }

    /**
     * Strip any score the account may carry from an earlier measurement, so an
     * account that has since fallen under the floor cannot linger in a feed.
     */
    private function disqualify(Creator $creator): void
    {
        $creator->posts()->whereNotNull('measured_at')->update([
            'measured_at' => null,
            'outlier_score' => 0,
            'performance_ratio' => 0,
            'engagement_rate' => 0,
        ]);
    }

    /**
     * Classify the account once, then leave it alone. The bio and hashtags an
     * account publishes under barely move, so re-running the model on every
     * measurement would pay for the same answer.
     *
     * @return array{niche: string, niche_topics: list<string>}
     */
    private function niche(DiscoveredProfile $profile, CreatorNicheService $niches, ?Creator $existing): array
    {
        if ($existing && is_array($existing->niche_topics) && $existing->niche_topics !== []) {
            return ['niche' => $existing->niche, 'niche_topics' => $existing->niche_topics];
        }

        $detected = $niches->detect($profile);

        return ['niche' => $detected['niche'], 'niche_topics' => $detected['topics']];
    }

    private function storePost(Creator $creator, DiscoveredPost $post): void
    {
        ContentPost::query()->updateOrCreate(
            ['source_url' => $post->sourceUrl],
            [
                'creator_id' => $creator->id,
                'platform' => 'instagram',
                'format' => $post->format,
                'hook' => $this->hook($post, $creator->niche),
                'caption' => $post->caption,
                'thumbnail_url' => $post->thumbnailUrl,
                'views' => $post->views,
                'likes' => $post->likes,
                'comments' => $post->comments,
                'published_at' => $post->publishedAt,
                'tags' => $post->hashtags,
                // why_it_works is written by score() once the baseline is known;
                // the hook and structure breakdown is generated lazily the first
                // time a creator opens the post.
            ],
        );
    }

    /**
     * Score every post this account has in the feed against the baseline just
     * measured — not only the ones in this scrape. Posts picked up earlier through
     * a hashtag were stored unscored, and posts scored against an older baseline
     * would no longer be comparable to the ones written a moment ago.
     */
    private function score(Creator $creator, int $baseline, PostPerformance $performance): void
    {
        $creator->posts()->chunkById(200, function ($posts) use ($creator, $baseline, $performance): void {
            foreach ($posts as $post) {
                $engagement = $post->likes + $post->comments;
                $outlier = $performance->outlierScore($engagement, $baseline);

                $post->forceFill([
                    'outlier_score' => $outlier,
                    // Kept in step for the clients still reading it. Its own column
                    // is narrower, so a runaway outlier is clamped here only.
                    'performance_ratio' => min(9999.99, $outlier),
                    'engagement_rate' => $performance->engagementRate($engagement, $creator->followers),
                    'why_it_works' => $this->whyItWorks($post, $outlier),
                    'measured_at' => now(),
                ])->save();
            }
        });
    }

    private function hook(DiscoveredPost $post, ?string $niche): string
    {
        $firstLine = trim((string) Str::of($post->caption)->before("\n"));

        return Str::limit($firstLine !== '' ? $firstLine : 'New '.($niche ?: 'creator').' post', 120);
    }

    private function whyItWorks(ContentPost $post, float $outlier): string
    {
        if ($outlier < 1) {
            return 'A steady post for this account, below the engagement its audience usually gives it.';
        }

        return 'This one reached '.round($outlier, 1).'× the engagement this account normally gets, on '
            .number_format($post->likes).' likes and '.number_format($post->comments).' comments.';
    }
}
