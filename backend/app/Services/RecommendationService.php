<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\User;
use App\Services\Discovery\CanonicalCreatorVerticals;
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
        private readonly MarketFeedAllocator $markets,
        private readonly CanonicalCreatorVerticals $verticals,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user, int $limit = 12): Collection
    {
        $savedIds = $user->savedContent()->pluck('content_post_id')->flip();
        $primaryVertical = $this->primaryVertical($user);
        $inspirationIds = $user->inspirationCreators()->pluck('creators.id');

        $ranked = $this->candidates($user, $limit, $primaryVertical, $inspirationIds)
            ->map(fn (ContentPost $post): array => ['post' => $post, 'ranking' => $this->ranker->rank($post)])
            ->sortByDesc('ranking.score')
            ->values();

        $inspirationLookup = $inspirationIds->flip();
        $inspired = $ranked
            ->filter(fn (array $item): bool => $inspirationLookup->has($item['post']->creator_id))
            ->groupBy('post.creator_id')
            ->flatMap(fn (Collection $posts): Collection => $posts->take(2))
            ->sortByDesc('ranking.score')
            ->take($limit)
            ->values();
        $fallback = $this->markets->allocate(
            $ranked->reject(fn (array $item): bool => $inspirationLookup->has($item['post']->creator_id)),
            $user->creatorProfile?->market,
            max(0, $limit - $inspired->count()),
            $primaryVertical,
        );

        return $this->render($inspired->concat($fallback), $user, $savedIds);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function globalForUser(User $user, int $limit = 12): Collection
    {
        $savedIds = $user->savedContent()->pluck('content_post_id')->flip();
        $ranked = $this->pool($user, collect())
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
     * @param  Collection<int, array{post: ContentPost, ranking: array<string, float>}>  $ranked
     * @param  Collection<int, mixed>  $savedIds
     * @return Collection<int, array<string, mixed>>
     */
    private function render(Collection $ranked, User $user, Collection $savedIds): Collection
    {
        return $ranked->map(function (array $item) use ($user, $savedIds): array {
            $post = $item['post'];
            $ranking = $item['ranking'];

            return $this->view->make($post, $user, $ranking['score'], $savedIds->has($post->id)) + [
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
    ): Collection {
        $query = $this->pool($user, $inspirationIds)
            ->whereNotNull('measured_at')
            ->where('outlier_score', '>=', (float) config('services.discovery.min_outlier_score'));

        $inspired = $inspirationIds->isEmpty()
            ? collect()
            : (clone $query)
                ->whereIn('creator_id', $inspirationIds)
                ->orderByDesc('outlier_score')
                ->limit($limit * self::CANDIDATE_MULTIPLIER)
                ->get();

        if (config('creator_catalog.curated_only')) {
            return $inspired->concat(collect(['FR', 'GB', 'US'])
                ->flatMap(function (string $market) use ($query, $limit, $primaryVertical): Collection {
                    $marketQuery = (clone $query)
                        ->whereHas('creator', fn (Builder $creator): Builder => $creator->where('market', $market));
                    $matching = $primaryVertical
                        ? (clone $marketQuery)
                            ->whereHas('creator', fn (Builder $creator): Builder => $creator->where('niche', $primaryVertical))
                            ->orderByDesc('outlier_score')
                            ->limit($limit * self::CANDIDATE_MULTIPLIER)
                            ->get()
                        : collect();

                    return $matching->concat(
                        $marketQuery
                            ->orderByDesc('outlier_score')
                            ->limit($limit * self::CANDIDATE_MULTIPLIER)
                            ->get(),
                    );
                }))
                ->unique('id')
                ->values();
        }

        $matching = $primaryVertical
            ? (clone $query)
                ->whereHas('creator', fn (Builder $creator): Builder => $creator->where('niche', $primaryVertical))
                ->orderByDesc('outlier_score')
                ->limit($limit * self::CANDIDATE_MULTIPLIER)
                ->get()
            : collect();

        return $inspired->concat($matching)->concat(
            $query->orderByDesc('outlier_score')
                ->limit($limit * self::CANDIDATE_MULTIPLIER)
                ->get(),
        )->unique('id')->values();
    }

    private function pool(User $user, Collection $inspirationIds): Builder
    {
        $window = now()->subDays((int) config('services.discovery.feed_window_days'));

        return ContentPost::query()
            ->with('creator')
            ->where('safety_status', 'allowed')
            ->whereNotIn('id', $user->dismissedContent()->select('content_post_id'))
            ->where('published_at', '>=', $window)
            // The absolute floors. outlier_score is a ratio, so on its own it rates a
            // post going from two likes to three as a 1.5x breakout.
            ->whereRaw('likes + comments >= ?', [(int) config('services.discovery.min_post_engagement')])
            ->whereHas('creator', function (Builder $creator) use ($inspirationIds): void {
                $creator->where('followers', '>=', (int) config('services.discovery.min_followers'))
                    ->where('safety_status', 'allowed');

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

        if ($post->outlier_score >= 2) {
            return "This beat {$post->creator->username}'s own average by {$lift}×, so the idea did the work rather than the audience size.";
        }

        return "Above average for {$post->creator->username} ({$lift}×), with enough engagement to make it a useful global benchmark.";
    }

    private function primaryVertical(User $user): ?string
    {
        $profile = $user->creatorProfile;

        return $this->verticals->canonical($profile?->primary_vertical)
            ?? $this->verticals->fromSignals([
                $profile?->niche,
                ...($profile?->topics ?? []),
            ]);
    }
}
