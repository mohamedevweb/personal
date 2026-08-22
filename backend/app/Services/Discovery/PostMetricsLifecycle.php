<?php

namespace App\Services\Discovery;

use App\Models\ContentPost;
use App\Models\ContentPostMetricSnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PostMetricsLifecycle
{
    public function recordRefresh(ContentPost $post, CarbonInterface $capturedAt): ContentPostMetricSnapshot
    {
        $previous = $post->metricSnapshots()->latest('captured_at')->latest('id')->first();
        $elapsedHours = $previous
            ? max(0, $previous->captured_at->diffInSeconds($capturedAt) / 3600)
            : null;
        $viewsDelta = $previous ? max(0, $post->views - $previous->views) : 0;
        $velocity = $elapsedHours && $elapsedHours > 0 ? $viewsDelta / $elapsedHours : 0;
        $acceleration = $previous && $elapsedHours && $elapsedHours > 0
            ? ($velocity - $previous->views_velocity) / $elapsedHours
            : 0;
        $growthRate = $previous && $previous->views > 0 ? $viewsDelta / $previous->views : 0;

        $snapshot = $post->metricSnapshots()->create([
            'captured_at' => $capturedAt,
            'views' => max(0, (int) $post->views),
            'likes' => max(0, (int) $post->likes),
            'comments' => max(0, (int) $post->comments),
            'shares' => max(0, (int) $post->shares),
            'views_delta' => $viewsDelta,
            'elapsed_hours' => $elapsedHours,
            'views_velocity' => round($velocity, 2),
            'views_acceleration' => round($acceleration, 4),
        ]);

        $post->forceFill([
            'last_metrics_scraped_at' => $capturedAt,
            'views_velocity' => round($velocity, 2),
            'views_acceleration' => round($acceleration, 4),
            'metrics_growth_rate' => round($growthRate, 4),
        ])->save();

        return $snapshot;
    }

    public function reschedule(ContentPost $post, CarbonInterface $scheduledAt): void
    {
        $ageHours = max(0, $post->published_at->diffInHours($scheduledAt));
        $status = $this->trackingStatus($post, $ageHours);

        if ($status === 'stopped') {
            $post->forceFill([
                'tracking_status' => $status,
                'next_metrics_scrape_at' => null,
                'tracking_stopped_at' => $post->tracking_stopped_at ?: $scheduledAt,
            ])->save();

            return;
        }

        $post->forceFill([
            'tracking_status' => $status,
            'next_metrics_scrape_at' => CarbonImmutable::instance($scheduledAt)->addHours(
                $this->intervalHours($post, $status, $ageHours),
            ),
            'tracking_stopped_at' => null,
        ])->save();
    }

    public function markUnavailable(ContentPost $post, CarbonInterface $checkedAt): void
    {
        $ageHours = max(0, $post->published_at->diffInHours($checkedAt));
        $nextStatus = match (true) {
            $post->tracking_status === 'hot' => 'warm',
            $post->tracking_status === 'warm' => 'cold',
            $ageHours <= 24 * 7 => 'cold',
            default => 'stopped',
        };

        $post->forceFill([
            'tracking_status' => $nextStatus,
            'next_metrics_scrape_at' => $nextStatus === 'stopped'
                ? null
                : CarbonImmutable::instance($checkedAt)->addHours(
                    (int) config('instagram_scraping.posts.intervals_hours.cold'),
                ),
            'tracking_stopped_at' => $nextStatus === 'stopped' ? $checkedAt : null,
        ])->save();
    }

    public function postponeAfterFailure(ContentPost $post, CarbonInterface $failedAt): void
    {
        $post->forceFill([
            'next_metrics_scrape_at' => CarbonImmutable::instance($failedAt)->addHours(
                (int) config('instagram_scraping.posts.intervals_hours.failure_backoff'),
            ),
        ])->save();
    }

    private function trackingStatus(ContentPost $post, float $ageHours): string
    {
        $velocityRatio = $this->velocityRatio($post);
        $meaningfulGrowth = $post->metrics_growth_rate >= (float) config('instagram_scraping.posts.meaningful_growth_rate');
        $hasGrowthHistory = $post->metricSnapshots()->count() > 1;
        $hot = ($post->outlier_score >= (float) config('instagram_scraping.posts.hot_outlier_score')
                && (! $hasGrowthHistory || $meaningfulGrowth))
            || $velocityRatio >= (float) config('instagram_scraping.posts.hot_velocity_multiplier')
            || ($post->views_acceleration > 0 && $meaningfulGrowth);

        if ($hot && $ageHours <= 24 * 30) {
            return 'hot';
        }

        if ($ageHours > 24 * 30) {
            $protected = $post->savedContent()->exists() || $post->remixes()->exists();

            return $protected
                && $post->outlier_score >= (float) config('instagram_scraping.posts.exceptional_outlier_score')
                ? 'cold'
                : 'stopped';
        }

        if ($post->tracking_status === 'hot') {
            return 'warm';
        }

        if ($post->tracking_status === 'warm') {
            return $meaningfulGrowth ? 'warm' : 'cold';
        }

        if ($post->tracking_status === 'cold' && ! $meaningfulGrowth) {
            return 'stopped';
        }

        if ($ageHours <= 24 * 7) {
            return 'active';
        }

        $protected = $post->savedContent()->exists() || $post->remixes()->exists();
        $important = $post->outlier_score >= (float) config('services.discovery.min_outlier_score')
            || $velocityRatio >= (float) config('instagram_scraping.posts.warm_velocity_multiplier')
            || $meaningfulGrowth
            || $protected;

        return match (true) {
            $important => 'warm',
            default => 'stopped',
        };
    }

    private function intervalHours(ContentPost $post, string $status, float $ageHours): int
    {
        if ($status === 'hot') {
            return $post->views_acceleration > 0
                ? (int) config('instagram_scraping.posts.intervals_hours.hot_min')
                : (int) config('instagram_scraping.posts.intervals_hours.hot_max');
        }

        if ($status === 'warm') {
            return (int) config("instagram_scraping.posts.intervals_hours.{$status}");
        }

        if ($status === 'cold') {
            return $ageHours > 24 * 30
                ? (int) config('instagram_scraping.posts.intervals_hours.exceptional')
                : (int) config('instagram_scraping.posts.intervals_hours.cold');
        }

        return match (true) {
            $ageHours <= 24 => (int) config('instagram_scraping.posts.intervals_hours.first_day'),
            $ageHours <= 24 * 3 => (int) config('instagram_scraping.posts.intervals_hours.days_one_to_three'),
            default => (int) config('instagram_scraping.posts.intervals_hours.days_four_to_seven'),
        };
    }

    private function velocityRatio(ContentPost $post): float
    {
        $baselineViews = (float) data_get($post->creator?->performance_baselines, 'views', 0);
        $maturityHours = max(1, (int) config('instagram_scraping.posts.baseline_maturity_hours'));
        $normalVelocity = $baselineViews / $maturityHours;

        return $normalVelocity > 0 ? $post->views_velocity / $normalVelocity : 0;
    }
}
