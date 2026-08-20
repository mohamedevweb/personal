<?php

namespace App\Services\Discovery;

use App\Models\ContentPost;
use Illuminate\Support\Collection;

/**
 * Measures a post against the normal performance of its own creator. Views and
 * engagement are combined when both exist; either signal can stand alone when a
 * provider does not expose the other one.
 */
class OutlierScore
{
    /**
     * @param  Collection<int, DiscoveredPost>  $posts
     * @return array{views: ?float, engagement: ?float}
     */
    public function baselines(Collection $posts): array
    {
        return [
            'views' => $this->median($posts->pluck('views')),
            'engagement' => $this->median($posts->map(fn (DiscoveredPost $post): int => $post->engagement())),
        ];
    }

    /** @param Collection<int, DiscoveredPost> $posts */
    public function baseline(Collection $posts): int
    {
        return max(1, (int) round($this->baselines($posts)['engagement'] ?? 0));
    }

    /** @param array{views: ?float, engagement: ?float} $baselines */
    public function score(DiscoveredPost|ContentPost $post, array $baselines): float
    {
        $signals = [];

        if (($baselines['views'] ?? 0) > 0 && $post->views > 0) {
            $signals[] = ['ratio' => $post->views / $baselines['views'], 'weight' => 0.6];
        }

        $engagement = $post instanceof DiscoveredPost
            ? $post->engagement()
            : $post->likes + $post->comments + $post->shares;

        if (($baselines['engagement'] ?? 0) > 0 && $engagement > 0) {
            $signals[] = ['ratio' => $engagement / $baselines['engagement'], 'weight' => 0.4];
        }

        if ($signals === []) {
            return 0.0;
        }

        $weight = array_sum(array_column($signals, 'weight'));
        $score = collect($signals)->sum(fn (array $signal): float => $signal['ratio'] * $signal['weight']) / $weight;

        return min(999999.99, round($score, 2));
    }

    public function outlierScore(int $engagement, int $baseline): float
    {
        if ($baseline < 1) {
            return 0.0;
        }

        return min(999999.99, round($engagement / $baseline, 2));
    }

    public function engagementRate(int $engagement, int $followers): float
    {
        if ($followers < 1) {
            return 0.0;
        }

        return min(99999.999, round($engagement / $followers * 100, 3));
    }

    /** @param Collection<int, int|float|null> $values */
    private function median(Collection $values): ?float
    {
        $available = $values->filter(fn (mixed $value): bool => is_numeric($value) && $value > 0);

        return $available->isEmpty() ? null : (float) $available->median();
    }
}
