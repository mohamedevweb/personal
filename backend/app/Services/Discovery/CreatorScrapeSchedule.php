<?php

namespace App\Services\Discovery;

use App\Models\Creator;
use App\Models\CreatorProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CreatorScrapeSchedule
{
    public function reprioritize(Creator $creator, CarbonInterface $scheduledAt): void
    {
        $lastPostAt = $creator->posts()->max('published_at');
        $lastPostAt = $lastPostAt ? CarbonImmutable::parse($lastPostAt) : null;
        $priority = $this->priority($creator, $lastPostAt, $scheduledAt);
        $status = $this->status($priority);
        $nextScrapeAt = CarbonImmutable::instance($scheduledAt)->addHours(
            $this->intervalHours($status, $priority),
        );

        if ($creator->next_scrape_at && $creator->next_scrape_at->lessThanOrEqualTo($scheduledAt)) {
            $nextScrapeAt = $creator->next_scrape_at;
        }

        $creator->forceFill([
            'next_scrape_at' => $nextScrapeAt,
            'last_post_at' => $lastPostAt,
            'scrape_priority' => $priority,
            'scrape_status' => $status,
        ])->save();
    }

    public function recordSuccess(Creator $creator, CarbonInterface $scrapedAt): void
    {
        $lastPostAt = $creator->posts()->max('published_at');
        $lastPostAt = $lastPostAt ? CarbonImmutable::parse($lastPostAt) : null;
        $priority = $this->priority($creator, $lastPostAt, $scrapedAt);
        $status = $this->status($priority);

        $creator->forceFill([
            'last_scraped_at' => $scrapedAt,
            'next_scrape_at' => CarbonImmutable::instance($scrapedAt)->addHours(
                $this->intervalHours($status, $priority),
            ),
            'last_post_at' => $lastPostAt,
            'scrape_priority' => $priority,
            'scrape_status' => $status,
            'scrape_failures' => 0,
        ])->save();
    }

    public function recordFailure(Creator $creator, CarbonInterface $failedAt): void
    {
        $failures = min(10, $creator->scrape_failures + 1);
        $base = max(1, (int) config('instagram_scraping.creator.failure_backoff_hours'));
        $maximum = max($base, (int) config('instagram_scraping.creator.failure_backoff_max_hours'));
        $delay = min($maximum, $base * (2 ** ($failures - 1)));

        $creator->forceFill([
            'scrape_failures' => $failures,
            'next_scrape_at' => CarbonImmutable::instance($failedAt)->addHours($delay),
        ])->save();
    }

    private function priority(Creator $creator, ?CarbonInterface $lastPostAt, CarbonInterface $now): float
    {
        $weights = config('instagram_scraping.creator.weights');
        $selectedBy = $creator->inspiredByUsers()->count();
        // Members are stored with a canonical vertical, so the creator has to be
        // compared on its own canonical vertical rather than on its label.
        $relevantFeeds = $creator->curation_status === 'approved' && $creator->primary_vertical
            ? CreatorProfile::query()->where('primary_vertical', $creator->primary_vertical)->count()
            : 0;
        $recentPosts = $creator->posts()->where('published_at', '>=', $now->copy()->subDays(30))->count();
        $recentOutliers = $creator->posts()
            ->where('published_at', '>=', $now->copy()->subDays(30))
            ->where('outlier_score', '>=', (float) config('services.discovery.min_outlier_score'))
            ->count();
        $hotPosts = $creator->posts()->where('tracking_status', 'hot')->count();

        $recency = match (true) {
            ! $lastPostAt => 0,
            $lastPostAt->greaterThanOrEqualTo($now->copy()->subDays(2)) => 1,
            $lastPostAt->greaterThanOrEqualTo($now->copy()->subDays(7)) => 0.6,
            $lastPostAt->greaterThanOrEqualTo($now->copy()->subDays(30)) => 0.25,
            default => 0,
        };

        $priority = min(30, $selectedBy * (float) $weights['user_selection'])
            + min(20, $relevantFeeds * (float) $weights['relevant_feed'])
            + ($creator->curation_status === 'approved' ? (float) $weights['approved_catalog'] : 0)
            + min(1, $recentPosts / 8) * (float) $weights['posting_frequency']
            + $recency * (float) $weights['recent_post']
            + min(20, $recentOutliers * (float) $weights['recent_outlier'])
            + min(10, $hotPosts * (float) $weights['hot_post']);

        return round(min(100, max(0, $priority)), 2);
    }

    private function status(float $priority): string
    {
        $thresholds = config('instagram_scraping.creator.thresholds');

        return match (true) {
            $priority >= (float) $thresholds['hot'] => 'hot',
            $priority >= (float) $thresholds['active'] => 'active',
            $priority >= (float) $thresholds['warm'] => 'warm',
            default => 'cold',
        };
    }

    private function intervalHours(string $status, float $priority): int
    {
        [$minimum, $maximum] = config("instagram_scraping.creator.intervals_hours.{$status}");
        $minimum = max(1, (int) $minimum);
        $maximum = max($minimum, (int) $maximum);
        $thresholds = config('instagram_scraping.creator.thresholds');
        $floor = match ($status) {
            'hot' => (float) $thresholds['hot'],
            'active' => (float) $thresholds['active'],
            'warm' => (float) $thresholds['warm'],
            default => 0,
        };
        $ceiling = match ($status) {
            'hot' => 100,
            'active' => (float) $thresholds['hot'],
            'warm' => (float) $thresholds['active'],
            default => (float) $thresholds['warm'],
        };
        $position = $ceiling > $floor ? ($priority - $floor) / ($ceiling - $floor) : 1;

        return (int) round($maximum - (min(1, max(0, $position)) * ($maximum - $minimum)));
    }
}
