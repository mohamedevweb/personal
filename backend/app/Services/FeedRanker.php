<?php

namespace App\Services;

use App\Models\ContentPost;

/** The single, configurable formula used to order the global feed. */
class FeedRanker
{
    /**
     * @return array{
     *   score: float,
     *   outlier: float,
     *   reach: float,
     *   recency: float
     * }
     */
    public function rank(ContentPost $post): array
    {
        $outlier = min(1, $post->outlier_score / max(0.01, (float) config('services.discovery.ranking.outlier_ceiling')));
        $reach = min(1, $post->engagement_rate / max(0.01, (float) config('services.discovery.ranking.reach_ceiling')));
        $recency = $this->freshness($post);
        $signals = [
            'outlier' => $outlier,
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
            'outlier' => $outlier,
            'reach' => $reach,
            'recency' => $recency,
        ];
    }

    private function freshness(ContentPost $post): float
    {
        $window = max(1, (int) config('services.discovery.feed_window_days')) * 24;

        return max(0, 1 - ($post->published_at->diffInHours(now()) / $window));
    }
}
