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
        $inspirationIds = $user->inspirationCreators()->pluck('creators.id');
        $interactionSignals = $this->interactions->forUser($user);

        $ranked = $this->candidates($user, $limit, $primaryVertical, $inspirationIds, $excludeIds)
            ->reject(fn (ContentPost $post): bool => $this->interactions->excludes($post, $interactionSignals))
            ->map(function (ContentPost $post) use ($user, $interactionSignals): array {
                $relevance = $this->relevance->assess($user->creatorProfile, $post);

                return [
                    'post' => $post,
                    'bucket' => $relevance['bucket'],
                    'ranking' => $this->personalizedRanking(
                        $post,
                        $user,
                        $relevance['affinity'],
                        $this->interactions->adjustment($post, $interactionSignals),
                    ),
                ];
            })
            ->sortByDesc('ranking.score')
            ->values();

        // A small catalogue can have fewer breakout posts than the requested
        // batch. Add a measured, safe second tier only when the first tier cannot
        // fill the configured minimum. Relevance still decides whether a post is
        // For You or Explore, so this never bypasses semantic filtering.
        $minimum = min(
            $limit,
            max(0, (int) config('services.discovery.minimum_feed_size')),
        );
        $relevantCount = $ranked->whereIn('bucket', [PostRelevance::FOR_YOU, PostRelevance::EXPLORE])->count();
        if ($relevantCount < $minimum) {
            $ranked = $ranked->concat(
                $this->fallbackCandidates($user, $limit, $inspirationIds, $excludeIds)
                    ->reject(fn (ContentPost $post): bool => $this->interactions->excludes($post, $interactionSignals))
                    ->map(function (ContentPost $post) use ($user, $interactionSignals): array {
                        $relevance = $this->relevance->assess($user->creatorProfile, $post);

                        return [
                            'post' => $post,
                            'bucket' => $relevance['bucket'],
                            'ranking' => $this->personalizedRanking(
                                $post,
                                $user,
                                $relevance['affinity'],
                                $this->interactions->adjustment($post, $interactionSignals),
                            ),
                        ];
                    }),
            )
                ->unique(fn (array $item): int => $item['post']->id)
                ->sortByDesc('ranking.score')
                ->values();
        }

        $inspirationLookup = $inspirationIds->flip();
        $forYou = $ranked->where('bucket', PostRelevance::FOR_YOU)->values();
        $inspired = $forYou
            ->filter(fn (array $item): bool => $inspirationLookup->has($item['post']->creator_id))
            ->groupBy('post.creator_id')
            ->flatMap(fn (Collection $posts): Collection => $posts->take(2))
            ->sortByDesc('ranking.score')
            ->take($limit)
            ->values();
        $fallback = $this->markets->allocate(
            $forYou->reject(fn (array $item): bool => $inspirationLookup->has($item['post']->creator_id)),
            $user->creatorProfile?->market,
            max(0, $limit - $inspired->count()),
            $primaryVertical,
        );
        $items = $inspired->concat($fallback)->take($limit)->values();
        $exploreLimit = max(0, (int) config('services.discovery.personalization.explore_size'));
        $explore = $exploreLimit === 0
            ? collect()
            : $this->markets->allocate(
                $ranked->where('bucket', PostRelevance::EXPLORE)->values(),
                $user->creatorProfile?->market,
                $exploreLimit,
            );

        // Adjacent, semantically shared posts are useful when the strict shelf
        // is short. Promote only enough Explore cards to reach the floor, then
        // remove them from Explore so a card is never rendered twice.
        $promote = max(0, $minimum - $items->count());
        if ($promote > 0 && $explore->isNotEmpty()) {
            $promoted = $explore->take($promote)->values();
            $items = $items->concat($promoted)->take($limit)->values();
            $promotedIds = $promoted->pluck('post.id')->flip();
            $explore = $explore->reject(fn (array $item): bool => $promotedIds->has($item['post']->id))->values();
        }

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
        $ranked = $this->pool($user, collect(), [])
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
            ];
        })
            ->values();
    }

    /**
     * Posts that cleared every bar: measured against their own creator, beating it,
     * recent enough to still be useful, and carrying enough absolute
     * engagement for any of that to mean something.
     *
     * There is deliberately no fallback. An unmeasured post carries no evidence at
     * all, and serving a feed of them when measurement has not run yet — or has
     * failed — puts spam in front of creators. An honest empty state is worth more
     * than a full page of two-like posts.
     *
     * @return Collection<int, ContentPost>
     */
    private function candidates(
        User $user,
        int $limit,
        ?string $primaryVertical,
        Collection $inspirationIds,
        array $excludeIds,
    ): Collection {
        $query = $this->pool($user, $inspirationIds, $excludeIds)
            ->whereNotNull('measured_at')
            ->where('outlier_score', '>=', (float) config('services.discovery.min_outlier_score'));

        $inspired = $inspirationIds->isEmpty()
            ? collect()
            : (clone $query)
                ->whereIn('creator_id', $inspirationIds)
                ->orderByDesc('outlier_score')
                ->limit($limit * self::CANDIDATE_MULTIPLIER)
                ->get();

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

        return $inspired->concat($matching)->concat(
            $query->orderByDesc('outlier_score')
                // The allocator applies market quotas after semantic filtering.
                // One bounded cross-market query is enough to give each market
                // room without issuing a matching and fallback query per market.
                ->limit($limit * self::CANDIDATE_MULTIPLIER * count(config('creator_catalog.markets')))
                ->get(),
        )->unique('id')->values();
    }

    /** @return Collection<int, ContentPost> */
    private function fallbackCandidates(User $user, int $limit, Collection $inspirationIds, array $excludeIds): Collection
    {
        return $this->pool($user, $inspirationIds, $excludeIds)
            ->whereNotNull('measured_at')
            ->where('outlier_score', '>=', (float) config('services.discovery.fallback_min_outlier_score'))
            ->orderByDesc('outlier_score')
            ->limit($limit * self::CANDIDATE_MULTIPLIER * count(config('creator_catalog.markets')))
            ->get();
    }

    private function pool(User $user, Collection $inspirationIds, array $excludeIds): Builder
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
            ->whereHas('creator', function (Builder $creator) use ($inspirationIds, $user): void {
                $creator->where('followers', '>=', (int) config('services.discovery.min_followers'))
                    ->whereIn('market', config('creator_catalog.markets'))
                    ->where('safety_status', 'allowed')
                    ->where(function (Builder $owner) use ($user): void {
                        $owner->whereNull('user_id')->orWhere('user_id', '!=', $user->id);
                    });

                if (config('creator_catalog.curated_only')) {
                    $creator->where(function (Builder $curation) use ($inspirationIds): void {
                        $curation->where('curation_status', 'approved');

                        if ($inspirationIds->isNotEmpty()) {
                            $curation->orWhereIn('id', $inspirationIds);
                        }
                    });
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
        ?float $affinity = null,
        float $interactionAdjustment = 0.0,
    ): array {
        $ranking = $this->ranker->rank($post);
        $affinity ??= $this->affinity->score($user->creatorProfile, $post->creator, $post);

        if ($affinity === null) {
            return [...$ranking, 'creator_fit' => null];
        }

        $performanceWeight = max(0.0, (float) config('services.discovery.personalization.performance_weight'));
        $affinityWeight = max(0.0, (float) config('services.discovery.personalization.affinity_weight'));
        $total = $performanceWeight + $affinityWeight;

        return [
            ...$ranking,
            'score' => round(min(100, ($total > 0
                ? (($ranking['score'] * $performanceWeight) + ($affinity * 100 * $affinityWeight)) / $total
                : $ranking['score']) + $interactionAdjustment), 1),
            'creator_fit' => $affinity,
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
