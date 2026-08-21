<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\User;
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
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user, int $limit = 12): Collection
    {
        $savedIds = $user->savedContent()->pluck('content_post_id')->flip();

        return $this->candidates($user, $limit)
            ->map(function (ContentPost $post) use ($user, $savedIds): array {
                $ranking = $this->ranker->rank($post);

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
            ->sortByDesc('recommendation_score')
            ->take($limit)
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

    private function reason(ContentPost $post): string
    {
        $lift = round($post->outlier_score, 1);

        if ($post->outlier_score >= 2) {
            return "This beat {$post->creator->username}'s own average by {$lift}×, so the idea did the work rather than the audience size.";
        }

        return "Above average for {$post->creator->username} ({$lift}×), with enough engagement to make it a useful global benchmark.";
    }
}
