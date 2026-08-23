<?php

namespace App\Services\Discovery;

use App\Models\ContentPost;
use Illuminate\Support\Collection;

/**
 * Measures a post against the normal performance of its own creator. Views and
 * engagement are combined when both exist; either signal can stand alone when a
 * provider does not expose the other one.
 *
 * The comparison is per format. A Reel collects several times the views of a
 * carousel on the same account, so a single median across every format turns a
 * change of format into a breakout: an ordinary Reel from someone who mostly
 * posts carousels used to score 3-5x, and a genuinely strong carousel from a
 * Reel-heavy account scored below the feed floor and was never seen. A format
 * only earns its own baseline once it has enough posts to have a normal.
 */
class OutlierScore
{
    /**
     * @param  Collection<int, DiscoveredPost>  $posts
     * @return array{views: ?float, engagement: ?float, posts: int, formats: array<string, array{views: ?float, engagement: ?float, posts: int}>}
     */
    public function baselines(Collection $posts): array
    {
        $minimum = max(1, (int) config('services.discovery.format_baseline_min_posts'));

        return $this->medians($posts) + [
            'posts' => $posts->count(),
            'formats' => $posts
                ->groupBy(fn (DiscoveredPost $post): string => $this->format($post->format))
                ->filter(fn (Collection $group): bool => $group->count() >= $minimum)
                ->map(fn (Collection $group): array => $this->medians($group) + ['posts' => $group->count()])
                ->all(),
        ];
    }

    /** @param Collection<int, DiscoveredPost> $posts */
    public function baseline(Collection $posts): int
    {
        return max(1, (int) round($this->baselines($posts)['engagement'] ?? 0));
    }

    /** @param array{views?: ?float, engagement?: ?float, formats?: array<string, array<string, mixed>>} $baselines */
    public function score(DiscoveredPost|ContentPost $post, array $baselines): float
    {
        $against = $this->against($baselines, $post->format);
        $signals = [];

        if (($against['views'] ?? 0) > 0 && $post->views > 0) {
            $signals[] = ['ratio' => $post->views / $against['views'], 'weight' => 0.6];
        }

        $engagement = $post instanceof DiscoveredPost
            ? $post->engagement()
            : $post->likes + $post->comments + $post->shares;

        if (($against['engagement'] ?? 0) > 0 && $engagement > 0) {
            $signals[] = ['ratio' => $engagement / $against['engagement'], 'weight' => 0.4];
        }

        if ($signals === []) {
            return 0.0;
        }

        $weight = array_sum(array_column($signals, 'weight'));
        $score = collect($signals)->sum(fn (array $signal): float => $signal['ratio'] * $signal['weight']) / $weight;

        return min(999999.99, round($score, 2));
    }

    /**
     * The baseline a post is actually measured against. Its own format when that
     * format has a normal of its own, the whole account otherwise — including for
     * baselines written before formats were separated, which carry no bucket.
     *
     * @param  array{views?: ?float, engagement?: ?float, posts?: int, formats?: array<string, array<string, mixed>>}  $baselines
     * @return array{views: ?float, engagement: ?float, format: ?string, posts: int}
     */
    public function against(array $baselines, ?string $format): array
    {
        $bucket = $format ? ($baselines['formats'][$this->format($format)] ?? null) : null;
        $source = $bucket ?: $baselines;

        return [
            'views' => $source['views'] ?? null,
            'engagement' => $source['engagement'] ?? null,
            'format' => $bucket ? $this->format((string) $format) : null,
            'posts' => (int) ($source['posts'] ?? 0),
        ];
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

    /**
     * @param  Collection<int, DiscoveredPost>  $posts
     * @return array{views: ?float, engagement: ?float}
     */
    private function medians(Collection $posts): array
    {
        return [
            'views' => $this->median($posts->pluck('views')),
            'engagement' => $this->median($posts->map(fn (DiscoveredPost $post): int => $post->engagement())),
        ];
    }

    private function format(string $format): string
    {
        return strtolower(trim($format)) ?: 'image';
    }

    /** @param Collection<int, int|float|null> $values */
    private function median(Collection $values): ?float
    {
        $available = $values->filter(fn (mixed $value): bool => is_numeric($value) && $value > 0);

        return $available->isEmpty() ? null : (float) $available->median();
    }
}
