<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\CreatorProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RecommendationService
{
    /**
     * Candidates pulled per requested item. Performance and freshness carry half
     * the final weight and both are computable in SQL, so the database narrows the
     * field before the text-similarity half runs in PHP.
     */
    private const CANDIDATE_MULTIPLIER = 5;

    public function __construct(private readonly ContentPostView $view) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user, int $limit = 12): Collection
    {
        $profile = $user->creatorProfile;
        $terms = $this->profileTerms($profile);
        $profileText = $this->profileText($profile);
        $savedIds = $user->savedContent()->pluck('content_post_id')->flip();

        return $this->candidates($user, $limit)
            ->map(function (ContentPost $post) use ($terms, $profileText, $user, $savedIds): array {
                $postText = Str::lower($post->creator->niche.' '.implode(' ', $post->tags ?? []));
                $matches = $terms->filter(fn (string $term) => str_contains($postText, $term))->count();
                $nicheSimilarity = $terms->isEmpty() ? 0.65 : min(1, $matches / max(1, min(4, $terms->count())));
                $creatorSimilarity = str_contains($profileText, Str::lower($post->creator->niche)) ? 1 : 0.65;
                $freshness = $this->freshness($post);
                $performance = min(1, $post->performance_ratio / 8);
                $formatAffinity = 0.8;
                $score = round(100 * (
                    0.35 * $performance
                    + 0.25 * $nicheSimilarity
                    + 0.15 * $creatorSimilarity
                    + 0.15 * $freshness
                    + 0.10 * $formatAffinity
                ), 1);

                return $this->view->make($post, $user, $score, $savedIds->has($post->id)) + [
                    'why_recommended' => $this->reason($post, $nicheSimilarity, $performance),
                    'signals' => array_values(array_filter([
                        $post->published_at->isAfter(now()->subDay()) ? 'Trending' : null,
                        $nicheSimilarity >= 0.5 ? 'Great fit for you' : null,
                        $creatorSimilarity >= 0.8 ? 'Similar creator' : null,
                        $post->published_at->diffForHumans(),
                    ])),
                ];
            })
            ->sortByDesc('recommendation_score')
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, ContentPost> */
    private function candidates(User $user, int $limit): Collection
    {
        return ContentPost::query()
            ->with('creator')
            ->whereNotIn('id', $user->dismissedContent()->select('content_post_id'))
            ->orderByDesc('performance_ratio')
            ->orderByDesc('published_at')
            ->limit($limit * self::CANDIDATE_MULTIPLIER)
            ->get();
    }

    /** @return Collection<int, string> */
    private function profileTerms(?CreatorProfile $profile): Collection
    {
        return collect(preg_split('/\W+/', $this->profileText($profile)))
            ->filter(fn (string $term) => strlen($term) > 3)
            ->values();
    }

    private function profileText(?CreatorProfile $profile): string
    {
        return Str::lower(implode(' ', array_filter([
            $profile?->niche,
            $profile?->positioning,
            ...($profile?->topics ?? []),
        ])));
    }

    private function freshness(ContentPost $post): float
    {
        return max(0, 1 - ($post->published_at->diffInHours(now()) / (24 * 14)));
    }

    private function reason(ContentPost $post, float $nicheSimilarity, float $performance): string
    {
        if ($performance > 0.75 && $nicheSimilarity > 0.5) {
            return "Strong {$post->creator->niche} story, currently outperforming among creators similar to you.";
        }

        if ($performance > 0.75) {
            return 'A proven narrative pattern with unusually strong performance for this creator.';
        }

        return 'The topic and structure align closely with what your audience already expects from you.';
    }
}
