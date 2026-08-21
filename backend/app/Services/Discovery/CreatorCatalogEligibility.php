<?php

namespace App\Services\Discovery;

use Carbon\CarbonInterface;

class CreatorCatalogEligibility
{
    public function __construct(private readonly CreatorMarketDetector $markets) {}

    /** @return array<string, mixed> */
    public function evaluate(DiscoveredProfile $profile, array $entry): array
    {
        $window = now()->subDays((int) config('creator_catalog.audit.posts_window_days'));
        $posts = $profile->posts
            ->filter(fn (DiscoveredPost $post): bool => $post->publishedAt->greaterThanOrEqualTo($window))
            ->filter(fn (DiscoveredPost $post): bool => $post->sourceUrl !== '' && in_array($post->format, ['reel', 'carousel', 'image'], true))
            ->values();
        $measured = $posts->filter(fn (DiscoveredPost $post): bool => $post->views > 0 || $post->engagement() > 0);
        $coverage = $posts->isEmpty() ? 0.0 : $measured->count() / $posts->count();
        $engagements = $posts->map(fn (DiscoveredPost $post): int => $post->engagement())->sort()->values();
        $median = $this->median($engagements->all());
        $latest = $posts->max(fn (DiscoveredPost $post): CarbonInterface => $post->publishedAt);
        $market = $this->markets->detect(implode("\n", array_filter([
            $profile->displayName,
            $profile->bio,
            $posts->take(12)->pluck('caption')->implode("\n"),
        ])));
        $reasons = [];
        $warnings = [];
        $suggestions = [];

        if ($profile->isPrivate) {
            $reasons[] = 'private_account';
        }
        if ($this->looksImpersonal($profile)) {
            $reasons[] = 'impersonal_brand_or_aggregator';
        }
        if ($profile->followers < (int) config('creator_catalog.audit.min_followers')) {
            $reasons[] = 'followers_below_minimum';
        }
        if (! $latest || $latest->lessThan(now()->subDays((int) config('creator_catalog.audit.active_within_days')))) {
            $reasons[] = 'inactive';
        }
        if ($posts->count() < (int) config('creator_catalog.audit.min_posts')) {
            $reasons[] = 'insufficient_recent_posts';
        }
        if ($coverage < (float) config('creator_catalog.audit.min_metric_coverage')) {
            $reasons[] = 'metric_coverage_below_minimum';
        }
        if ($median < (int) config('creator_catalog.audit.min_median_engagement')) {
            $reasons[] = 'median_engagement_below_minimum';
        }
        if ($market['market'] === null || $market['confidence'] < 0.70) {
            $warnings[] = 'market_unverified';
        } elseif ($market['market'] !== $entry['market']) {
            $warnings[] = 'market_signal_mismatch';
        }

        $detectedTier = $this->tier($profile->followers);
        $expectedTier = $entry['recognition_tier'] ?? null;
        if ($expectedTier !== null && $detectedTier !== $expectedTier) {
            $warnings[] = 'recognition_tier_mismatch';
            $suggestions[] = 'Set recognition_tier to '.($detectedTier ?? 'null').'.';
        }

        return [
            'handle' => $entry['handle'],
            'status' => $entry['status'],
            'provider_status' => 'ok',
            'provider_error' => null,
            'accepted' => $reasons === [],
            'reasons' => $reasons,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
            'expected_market' => $entry['market'],
            'detected_market' => $market['market'],
            'market_confidence' => $market['confidence'],
            'primary_language' => $market['language'],
            'vertical' => $entry['vertical'],
            'expected_tier' => $expectedTier,
            'detected_tier' => $detectedTier,
            'followers' => $profile->followers,
            'recent_posts' => $posts->count(),
            'latest_post_at' => $latest?->toIso8601String(),
            'metric_coverage' => round($coverage, 4),
            'median_engagement' => $median,
            'instagram_user_id' => $profile->externalId,
            'display_name' => $profile->displayName,
            'bio' => $profile->bio,
        ];
    }

    public function tier(int $followers): ?string
    {
        return match (true) {
            $followers >= 250000 => 'leader',
            $followers >= 50000 => 'established',
            $followers >= 25000 => 'expert',
            default => null,
        };
    }

    private function median(array $values): int
    {
        $count = count($values);

        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (int) $values[$middle]
            : (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function looksImpersonal(DiscoveredProfile $profile): bool
    {
        $text = str_replace(['_', '-', '.'], ' ', strtolower(implode(' ', [$profile->username, $profile->displayName, $profile->bio])));

        return (bool) preg_match('/\b(repost|fanpage|aggregator|memes?|giveaway|follow\s*for\s*follow|crypto\s*signals|betting\s*tips)\b/i', $text);
    }
}
