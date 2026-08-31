<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\User;
use App\Services\View\ContentPostView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ranks the shared content pool globally, while preserving per-user saves and
 * dismissals.
 *
 * The ordering question is "did this post beat the account that published it",
 * not "did this post get a lot of likes". A 2M-follower account posting its usual
 * numbers is not an opportunity; a 20k account tripling its own average is. That
 * is what outlier_score carries, and why it remains a major ranking signal.
 */
class RecommendationService
{
    /**
     * Candidates pulled per requested item. Outlier score and recency are both
     * indexed, so the database narrows the pool before the text-similarity half of
     * the ranking runs in PHP.
     */
    private const CANDIDATE_MULTIPLIER = 8;

    /** Keep a single creator from becoming the whole shelf. */
    private const MAX_POSTS_PER_CREATOR = 2;

    public function __construct(
        private readonly ContentPostView $view,
        private readonly FeedRanker $ranker,
        private readonly CreatorAffinity $affinity,
        private readonly PostRelevance $relevance,
        private readonly FeedInteractionSignals $interactions,
        private readonly ContentTopicClassifier $classifier,
        private readonly MarketFeedAllocator $markets,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user, ?int $limit = null, array $excludeIds = []): Collection
    {
        return $this->sectionsForUser($user, $limit, $excludeIds)['items'];
    }

    /**
     * @return array{items: Collection<int, array<string, mixed>>, explore_items: Collection<int, array<string, mixed>>}
     */
    public function sectionsForUser(User $user, ?int $limit = null, array $excludeIds = []): array
    {
        $limit = max(1, $limit ?? (int) config('services.discovery.feed_size'));
        $savedIds = $user->savedContent()->pluck('content_post_id')->flip();

        $primaryVertical = $this->primaryVertical($user);
        $interactionSignals = $this->interactions->forUser($user);
        $visibleCreatorCounts = $this->creatorCounts($excludeIds);

        $ranked = $this->rankCandidates(
            $this->candidates($user, $limit, $primaryVertical, $excludeIds),
            $user,
            $interactionSignals,
        );

        // A small catalogue can have fewer breakout posts than the requested
        // batch. Add a measured, safe second tier only when the first tier cannot
        // fill the configured minimum. The second pass allows only same or
        // adjacent verticals with missing subject metadata; explicit unrelated
        // subjects and avoid topics remain blocked by PostRelevance.
        $minimum = min(
            $limit,
            max(0, (int) config('services.discovery.minimum_feed_size')),
        );
        $forYouCount = $ranked->where('bucket', PostRelevance::FOR_YOU)->count();
        if ($forYouCount < $minimum) {
            $fallbackRanked = $this->fallbackCandidates(
                $user,
                $limit,
                $excludeIds,
                $interactionSignals,
            );

            $ranked = $ranked->concat($fallbackRanked)
                ->unique(fn (array $item): int => $item['post']->id)
                ->sortByDesc('ranking.score')
                ->values();
        }

        $forYou = $ranked->where('bucket', PostRelevance::FOR_YOU)->values();
        $items = $this->markets->allocate(
            $this->limitPerCreator($forYou, $visibleCreatorCounts),
            $user->creatorProfile?->market,
            $limit,
            $primaryVertical,
        );
        $itemCreatorIds = collect(array_keys($visibleCreatorCounts))
            ->concat($items->pluck('post.creator_id'))
            ->flip();
        $exploreLimit = max(0, (int) config('services.discovery.personalization.explore_size'));
        $explore = $exploreLimit > 0
            ? $this->markets->allocate(
                $this->limitPerCreator(
                    $ranked->where('bucket', PostRelevance::EXPLORE)
                        ->reject(fn (array $item): bool => $itemCreatorIds->has($item['post']->creator_id))
                        ->values(),
                ),
                $user->creatorProfile?->market,
                $exploreLimit,
            )
            : collect();

        return [
            'items' => $this->render($items, $user, $savedIds),
            'explore_items' => $this->render($explore, $user, $savedIds),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function globalForUser(User $user, ?int $limit = null): Collection
    {
        $limit = max(1, $limit ?? (int) config('services.discovery.feed_size'));
        $savedIds = $user->savedContent()->pluck('content_post_id')->flip();
        $ranked = $this->pool($user, [])
            ->whereNotNull('measured_at')
            ->where('outlier_score', '>=', (float) config('services.discovery.min_outlier_score'))
            ->whereHas('creator', fn (Builder $creator): Builder => $creator->where('curation_status', 'approved'))
            ->orderByDesc('outlier_score')
            ->limit($limit * self::CANDIDATE_MULTIPLIER)
            ->get()
            ->map(fn (ContentPost $post): array => ['post' => $post, 'ranking' => $this->ranker->rank($post)])
            ->sortByDesc('ranking.score')
            ->take($limit)
            ->values();

        return $this->render($ranked, $user, $savedIds);
    }

    /**
     * @param  Collection<int, array{post: ContentPost, ranking: array<string, float|null>}>  $ranked
     * @param  Collection<int, mixed>  $savedIds
     * @return Collection<int, array<string, mixed>>
     */
    private function render(Collection $ranked, User $user, Collection $savedIds): Collection
    {
        return $ranked->map(function (array $item) use ($user, $savedIds): array {
            $post = $item['post'];
            $ranking = $item['ranking'];

            $relevance = $item['relevance'] ?? null;

            return $this->view->make($post, $user, $ranking['score'], $savedIds->has($post->id)) + [
                'creator_fit_score' => ($ranking['creator_fit'] ?? null) === null
                    ? null
                    : round((float) $ranking['creator_fit'] * 100),
                'why_recommended' => $this->reason($post),
                'signals' => array_values(array_filter([
                    // The lift itself is already on the card as a localized badge,
                    // so it is deliberately not repeated here.
                    $post->published_at->isAfter(now()->subDay()) ? 'Trending' : null,
                    $post->published_at->diffForHumans(),
                ])),
                ...($relevance ? ['recommendation_debug' => [
                    'bucket' => $item['bucket'],
                    'profile_vertical' => $relevance['primary_vertical'],
                    'profile_primary_niche' => $relevance['profile_primary_niche'],
                    'profile_sub_niches' => $relevance['profile_sub_niches'],
                    'post_vertical' => $relevance['content_vertical'],
                    'post_primary_niche' => $relevance['post_primary_niche'],
                    'post_sub_niches' => $relevance['post_sub_niches'],
                    'creator_affinity' => $relevance['creator_affinity'],
                    'post_relevance' => $relevance['post_relevance'],
                    'shared_niches' => $relevance['shared_niches'],
                    'shared_topics' => $relevance['shared_topics'],
                    'matched_avoid_topics' => $relevance['matched_avoid_topics'],
                    'outlier_score' => $post->outlier_score,
                    'freshness' => $ranking['recency'],
                    'final_ranking_score' => $ranking['score'],
                ]] : []),
            ];
        })
            ->values();
    }

    /**
     * Keep the strongest posts from each creator and leave the shelf short when
     * the relevant catalogue lacks variety. Repeating one account is not a
     * substitute for discovering more relevant creators.
     *
     * @param  Collection<int, array{post: ContentPost, ranking: array<string, float|null>}>  $ranked
     * @return Collection<int, array{post: ContentPost, ranking: array<string, float|null>}>
     */
    private function limitPerCreator(Collection $ranked, array $existingCounts = []): Collection
    {
        $counts = $existingCounts;

        return $ranked->filter(function (array $item) use (&$counts): bool {
            $creatorId = $item['post']->creator_id;
            $count = $counts[$creatorId] ?? 0;

            if ($count >= self::MAX_POSTS_PER_CREATOR) {
                return false;
            }

            $counts[$creatorId] = $count + 1;

            return true;
        })->values();
    }

    /** @return array<int, int> */
    private function creatorCounts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        return ContentPost::query()
            ->whereIn('id', $postIds)
            ->selectRaw('creator_id, count(*) as aggregate')
            ->groupBy('creator_id')
            ->pluck('aggregate', 'creator_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /** @param Collection<int, ContentPost> $posts @param array<string, mixed> $interactionSignals */
    private function rankCandidates(Collection $posts, User $user, array $interactionSignals): Collection
    {
        return $this->rankCandidatesWithMode($posts, $user, $interactionSignals);
    }

    /** @param Collection<int, ContentPost> $posts @param array<string, mixed> $interactionSignals */
    private function rankCandidatesWithMode(
        Collection $posts,
        User $user,
        array $interactionSignals,
        bool $allowBroaderMatch = false,
    ): Collection {
        return $posts
            ->reject(fn (ContentPost $post): bool => $this->interactions->excludes($post, $interactionSignals))
            ->map(function (ContentPost $post) use ($user, $interactionSignals, $allowBroaderMatch): ?array {
                $relevance = $this->relevance->assess($user->creatorProfile, $post, $allowBroaderMatch);

                // This is the hard gate. A rejected post never gets a ranking
                // score and therefore cannot be rescued by a large outlier.
                if ($relevance['bucket'] === null) {
                    return null;
                }

                return [
                    'post' => $post,
                    'bucket' => $relevance['bucket'],
                    'relevance' => $relevance,
                    'ranking' => $this->personalizedRanking(
                        $post,
                        $user,
                        $relevance['post_relevance'],
                        $relevance['creator_affinity'],
                        $this->interactions->adjustment($post, $interactionSignals),
                    ),
                ];
            })
            ->filter()
            ->sortByDesc('ranking.score')
            ->values();
    }

    /**
     * Posts that cleared every bar: measured against their own creator, beating it,
     * recent enough to still be useful, and carrying enough absolute
     * engagement for any of that to mean something.
     *
     * This is the retrieval pool for the broader relevance pass. Posts still need
     * measurement, safety and engagement evidence before they can be considered.
     *
     * @return Collection<int, ContentPost>
     */
    private function candidates(
        User $user,
        int $limit,
        ?string $primaryVertical,
        array $excludeIds,
    ): Collection {
        $query = $this->pool($user, $excludeIds)
            ->whereNotNull('measured_at')
            ->where('outlier_score', '>=', (float) config('services.discovery.min_outlier_score'));

        $matching = $primaryVertical
            ? (clone $query)
                // The canonical vertical, not the human label: `niche` holds free
                // text written by discovery, so comparing it to a slug matched
                // almost nothing and this query returned an empty set in silence.
                ->whereHas('creator', fn (Builder $creator): Builder => $creator->where('primary_vertical', $primaryVertical))
                ->orderByDesc('outlier_score')
                ->limit($limit * self::CANDIDATE_MULTIPLIER)
                ->get()
            : collect();

        $global = (clone $query)
            ->orderByDesc('outlier_score')
            // This is only a retrieval pool. PostRelevance remains the gate.
            ->limit($limit * self::CANDIDATE_MULTIPLIER * count(config('creator_catalog.markets')))
            ->get();

        return $matching->concat($global)->unique('id')->values();
    }

    /** @return Collection<int, ContentPost> */
    private function fallbackCandidates(
        User $user,
        int $limit,
        array $excludeIds,
        array $interactionSignals,
    ): Collection {
        return $this->rankCandidatesWithMode(
            $this->pool($user, $excludeIds)
                ->whereNotNull('measured_at')
                ->where('outlier_score', '>=', (float) config('services.discovery.fallback_min_outlier_score'))
                ->orderByDesc('outlier_score')
                ->limit($limit * self::CANDIDATE_MULTIPLIER * count(config('creator_catalog.markets')))
                ->get(),
            $user,
            $interactionSignals,
            true,
        );
    }

    private function pool(User $user, array $excludeIds): Builder
    {
        $window = now()->subDays((int) config('services.discovery.feed_window_days'));

        return ContentPost::query()
            ->with('creator')
            ->where('safety_status', 'allowed')
            ->when($excludeIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludeIds))
            ->whereNotIn('id', $user->dismissedContent()->select('content_post_id'))
            ->where('published_at', '>=', $window)
            // The absolute floors. outlier_score is a ratio, so on its own it rates a
            // post going from two likes to three as a 1.5x breakout.
            ->whereRaw('likes + comments >= ?', [(int) config('services.discovery.min_post_engagement')])
            ->whereHas('creator', function (Builder $creator) use ($user): void {
                $creator->where('followers', '>=', (int) config('services.discovery.min_followers'))
                    ->whereIn('market', config('creator_catalog.markets'))
                    ->where('safety_status', 'allowed')
                    ->where(function (Builder $owner) use ($user): void {
                        $owner->whereNull('user_id')->orWhere('user_id', '!=', $user->id);
                    });

                if (config('creator_catalog.curated_only')) {
                    $creator->where('curation_status', 'approved');
                }
            });
    }

    private function reason(ContentPost $post): string
    {
        $lift = round($post->outlier_score, 1);
        $normal = "{$post->creator->username}'s usual ".strtolower($post->format);

        if ($post->outlier_score >= 2) {
            return "This beat {$normal} by {$lift}×, so the idea did the work rather than the audience size.";
        }

        return "Above {$normal} ({$lift}×), with enough engagement to make it a useful global benchmark.";
    }

    /** @return array<string, float|null> */
    private function personalizedRanking(
        ContentPost $post,
        User $user,
        float $postRelevance = 1.0,
        ?float $affinity = null,
        float $interactionAdjustment = 0.0,
    ): array {
        $ranking = $this->ranker->rank($post);
        $creatorContext = $affinity ?? 0.0;
        $postRelevanceWeight = max(0.0, (float) config('services.discovery.personalization.post_relevance_weight', 0.50));
        $performanceWeight = max(0.0, (float) config('services.discovery.personalization.performance_weight', 0.30));
        $freshnessWeight = max(0.0, (float) config('services.discovery.personalization.freshness_weight', 0.15));
        $interactionWeight = max(0.0, (float) config('services.discovery.personalization.interaction_weight', 0.05));
        $creatorContextWeight = max(0.0, (float) config('services.discovery.personalization.creator_context_weight', 0.10));
        $total = $postRelevanceWeight + $performanceWeight + $freshnessWeight + $interactionWeight;
        $interaction = min(1.0, max(0.0, $interactionAdjustment / 15));
        $effectiveRelevance = min(1.0, max(0.0,
            ((1 - $creatorContextWeight) * $postRelevance)
            + ($creatorContextWeight * $creatorContext),
        ));
        $score = $total > 0
            ? 100 * (($effectiveRelevance * $postRelevanceWeight)
                + ($ranking['outlier'] * $performanceWeight)
                + ($ranking['recency'] * $freshnessWeight)
                + ($interaction * $interactionWeight)) / $total
            : 0.0;

        return [
            ...$ranking,
            'score' => round(min(100, max(0, $score)), 1),
            'creator_fit' => $affinity,
            'post_relevance' => $postRelevance,
        ];
    }

    private function primaryVertical(User $user): ?string
    {
        $profile = $user->creatorProfile;

        if (! $profile) {
            return null;
        }

        return $this->classifier->profile($profile)['vertical'];
    }
}
