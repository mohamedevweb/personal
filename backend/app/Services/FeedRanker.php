<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\CreatorProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** The single, configurable formula used to order every For You candidate. */
class FeedRanker
{
    /**
     * @return array{
     *   score: float,
     *   niche_similarity: float,
     *   creator_relevance: float,
     *   outlier: float,
     *   reach: float,
     *   recency: float
     * }
     */
    public function rank(ContentPost $post, ?CreatorProfile $profile): array
    {
        $terms = $this->profileTerms($profile);
        $creatorRelevance = $this->overlap($terms, [
            $post->creator->niche,
            ...($post->creator->niche_topics ?? []),
        ]);
        $nicheSimilarity = $this->overlap($terms, [
            ...($post->tags ?? []),
            $post->caption,
        ]);
        $outlier = min(1, $post->outlier_score / max(0.01, (float) config('services.discovery.ranking.outlier_ceiling')));
        $reach = min(1, $post->engagement_rate / max(0.01, (float) config('services.discovery.ranking.reach_ceiling')));
        $recency = $this->freshness($post);
        $signals = [
            'outlier' => $outlier,
            'creator_relevance' => $creatorRelevance,
            'niche_similarity' => $nicheSimilarity,
            'reach' => $reach,
            'recency' => $recency,
        ];
        $weights = (array) config('services.discovery.ranking.weights');
        $weightTotal = collect(array_keys($signals))
            ->sum(fn (string $key): float => max(0, (float) ($weights[$key] ?? 0)));
        $weighted = collect(array_keys($signals))->sum(
            fn (string $key): float => $signals[$key] * max(0, (float) ($weights[$key] ?? 0)),
        );

        return [
            'score' => round(100 * ($weightTotal > 0 ? $weighted / $weightTotal : 0), 1),
            'niche_similarity' => $nicheSimilarity,
            'creator_relevance' => $creatorRelevance,
            'outlier' => $outlier,
            'reach' => $reach,
            'recency' => $recency,
        ];
    }

    /** @param Collection<int, string> $terms @param list<string|null> $against */
    private function overlap(Collection $terms, array $against): float
    {
        $haystack = Str::lower(implode(' ', array_filter($against)));

        if ($terms->isEmpty() || trim($haystack) === '') {
            return 0.4;
        }

        $matches = $terms->filter(fn (string $term): bool => str_contains($haystack, $term))->count();

        return min(1, $matches / min(3, $terms->count()));
    }

    /** @return Collection<int, string> */
    private function profileTerms(?CreatorProfile $profile): Collection
    {
        $dna = $profile?->creator_dna ?? [];
        $text = Str::lower(implode(' ', array_filter([
            $dna['primary_niche'] ?? $profile?->niche,
            ...($dna['sub_niches'] ?? []),
            ...($dna['topics'] ?? $profile?->topics ?? []),
            ...($dna['audience'] ?? []),
            ...($dna['content_pillars'] ?? []),
            $profile?->positioning,
        ])));

        return collect(preg_split('/[^\pL\pN]+/u', $text) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) > 3)
            ->unique()
            ->values();
    }

    private function freshness(ContentPost $post): float
    {
        $window = max(1, (int) config('services.discovery.feed_window_days')) * 24;

        return max(0, 1 - ($post->published_at->diffInHours(now()) / $window));
    }
}
