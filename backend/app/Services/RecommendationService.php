<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RecommendationService
{
    public function __construct(private readonly ContentPostView $view) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user, int $limit = 12): Collection
    {
        $profile = $user->creatorProfile;
        $dismissedIds = $user->dismissedContent()->pluck('content_post_id');

        return ContentPost::query()
            ->with('creator')
            ->whereNotIn('id', $dismissedIds)
            ->get()
            ->map(function (ContentPost $post) use ($profile, $user): array {
                $profileText = Str::lower(implode(' ', array_filter([
                    $profile?->niche,
                    $profile?->positioning,
                    ...($profile?->topics ?? []),
                ])));
                $postText = Str::lower($post->creator->niche.' '.implode(' ', $post->tags ?? []));
                $terms = collect(preg_split('/\W+/', $profileText))->filter(fn ($term) => strlen($term) > 3);
                $matches = $terms->filter(fn ($term) => str_contains($postText, $term))->count();
                $nicheSimilarity = $terms->isEmpty() ? 0.65 : min(1, $matches / max(1, min(4, $terms->count())));
                $creatorSimilarity = str_contains($profileText, Str::lower($post->creator->niche)) ? 1 : 0.65;
                $freshness = max(0, 1 - ($post->published_at->diffInHours(now()) / (24 * 14)));
                $performance = min(1, $post->performance_ratio / 8);
                $formatAffinity = 0.8;
                $score = round(100 * (
                    0.35 * $performance
                    + 0.25 * $nicheSimilarity
                    + 0.15 * $creatorSimilarity
                    + 0.15 * $freshness
                    + 0.10 * $formatAffinity
                ), 1);

                return $this->view->make($post, $user, $score) + [
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
