<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\CreatorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ranks the shared content pool for one creator.
 *
 * The ordering question is "did this post beat the account that published it",
 * not "did this post get a lot of likes". A 2M-follower account posting its usual
 * numbers is not an opportunity; a 20k account tripling its own average is. That
 * is what outlier_score carries, and why it leads the weighting.
 */
class RecommendationService
{
    /**
     * Candidates pulled per requested item. Outlier score and recency are both
     * indexed, so the database narrows the pool before the text-similarity half of
     * the ranking runs in PHP.
     */
    private const CANDIDATE_MULTIPLIER = 8;

    /** Beating the creator's own average by this much scores full marks. */
    private const OUTLIER_CEILING = 3.0;

    /** A post reaching this share of its creator's audience counts as top reach. */
    private const REACH_CEILING = 6.0;

    public function __construct(private readonly ContentPostView $view) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user, int $limit = 12): Collection
    {
        $terms = $this->profileTerms($user->creatorProfile);
        $savedIds = $user->savedContent()->pluck('content_post_id')->flip();

        return $this->candidates($user, $limit)
            ->map(function (ContentPost $post) use ($terms, $user, $savedIds): array {
                $creatorSimilarity = $this->overlap($terms, [
                    $post->creator->niche,
                    ...($post->creator->niche_topics ?? []),
                ]);
                $nicheSimilarity = $this->overlap($terms, $post->tags ?? []);
                $outlier = min(1, $post->outlier_score / self::OUTLIER_CEILING);
                $reach = min(1, $post->engagement_rate / self::REACH_CEILING);
                $freshness = $this->freshness($post);

                $score = round(100 * (
                    0.35 * $outlier
                    + 0.20 * $creatorSimilarity
                    + 0.15 * $nicheSimilarity
                    + 0.15 * $reach
                    + 0.15 * $freshness
                ), 1);

                return $this->view->make($post, $user, $score, $savedIds->has($post->id)) + [
                    'why_recommended' => $this->reason($post, $nicheSimilarity),
                    'signals' => array_values(array_filter([
                        // The lift itself is already on the card as a localized badge,
                        // so it is deliberately not repeated here.
                        $post->published_at->isAfter(now()->subDay()) ? 'Trending' : null,
                        $nicheSimilarity >= 0.6 ? 'Great fit for you' : null,
                        $creatorSimilarity >= 0.6 ? 'Similar creator' : null,
                        $post->published_at->diffForHumans(),
                    ])),
                ];
            })
            ->sortByDesc('recommendation_score')
            ->take($limit)
            ->values();
    }

    /**
     * Posts that cleared every bar: measured against their own creator, beating it,
     * recent enough to still describe the niche, and carrying enough absolute
     * engagement for any of that to mean something.
     *
     * There is deliberately no fallback. An unmeasured post carries no evidence at
     * all, and serving a feed of them when measurement has not run yet — or has
     * failed — puts spam in front of creators. An honest empty state is worth more
     * than a full page of two-like posts.
     *
     * @return Collection<int, ContentPost>
     */
    private function candidates(User $user, int $limit): Collection
    {
        return $this->pool($user)
            ->whereNotNull('measured_at')
            ->where('outlier_score', '>=', (float) config('services.discovery.min_outlier_score'))
            ->orderByDesc('outlier_score')
            ->limit($limit * self::CANDIDATE_MULTIPLIER)
            ->get();
    }

    private function pool(User $user): Builder
    {
        $window = now()->subDays((int) config('services.discovery.feed_window_days'));

        return ContentPost::query()
            ->with('creator')
            ->whereNotIn('id', $user->dismissedContent()->select('content_post_id'))
            ->where('published_at', '>=', $window)
            // The absolute floors. outlier_score is a ratio, so on its own it rates a
            // post going from two likes to three as a 1.5x breakout.
            ->whereRaw('likes + comments >= ?', [(int) config('services.discovery.min_post_engagement')])
            ->whereHas('creator', fn (Builder $creator): Builder => $creator->where(
                'followers', '>=', (int) config('services.discovery.min_followers'),
            ));
    }

    /**
     * Share of the creator's own vocabulary found in the given text.
     *
     * Matching is substring-based because hashtags arrive glued together
     * ("veganmealprep"), which a token comparison would never line up with "vegan".
     *
     * @param  Collection<int, string>  $terms
     * @param  list<string|null>  $against
     */
    private function overlap(Collection $terms, array $against): float
    {
        $haystack = Str::lower(implode(' ', array_filter($against)));

        // Neutral rather than zero: an unclassified account should rank below a
        // matched one, not be eliminated on missing data.
        if ($terms->isEmpty() || trim($haystack) === '') {
            return 0.4;
        }

        $matches = $terms->filter(fn (string $term): bool => str_contains($haystack, $term))->count();

        // Three shared terms is already a confident match. Requiring full overlap
        // would punish creators who describe the same niche in wider language.
        return min(1, $matches / min(3, $terms->count()));
    }

    /** @return Collection<int, string> */
    private function profileTerms(?CreatorProfile $profile): Collection
    {
        $text = Str::lower(implode(' ', array_filter([
            $profile?->niche,
            $profile?->positioning,
            ...($profile?->topics ?? []),
        ])));

        return collect(preg_split('/\W+/', $text))
            ->filter(fn (string $term): bool => strlen($term) > 3)
            ->unique()
            ->values();
    }

    private function freshness(ContentPost $post): float
    {
        $window = max(1, (int) config('services.discovery.feed_window_days')) * 24;

        return max(0, 1 - ($post->published_at->diffInHours(now()) / $window));
    }

    private function reason(ContentPost $post, float $nicheSimilarity): string
    {
        $lift = round($post->outlier_score, 1);

        if ($post->outlier_score >= 2 && $nicheSimilarity >= 0.6) {
            return "A genuine breakout: {$lift}× what {$post->creator->username} usually gets, on a topic you already cover.";
        }

        if ($post->outlier_score >= 2) {
            return "This beat {$post->creator->username}'s own average by {$lift}×, so the idea did the work rather than the audience size.";
        }

        return "Above average for {$post->creator->username} ({$lift}×) and close to the topics your audience expects from you.";
    }
}
