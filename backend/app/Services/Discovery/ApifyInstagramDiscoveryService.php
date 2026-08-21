<?php

namespace App\Services\Discovery;

use App\Exceptions\ContentDiscoveryException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Scrapes real niche posts through Apify's Instagram Scraper actor
 * (apify/instagram-scraper) in two stages:
 *
 *   1. Hashtag pages give candidate post URLs, but Instagram hides like counts
 *      there, so those results carry no engagement.
 *   2. Re-scraping the individual post URLs returns likes and views, which the
 *      feed ranks on.
 *
 * Stage 2 can be disabled (APIFY_ENRICH_METRICS=false) to halve the cost, at the
 * price of engagement-blind ranking.
 */
class ApifyInstagramDiscoveryService implements ContentDiscoveryService
{
    public function discover(array $hashtags, int $limit): Collection
    {
        if ((string) config('services.discovery.apify.token') === '') {
            throw new ContentDiscoveryException('Apify is not configured. Set APIFY_TOKEN or use DISCOVERY_DRIVER=mock.');
        }

        $hashtags = array_values(array_filter($hashtags));
        if ($hashtags === []) {
            return collect();
        }

        $urls = $this->candidateUrls($hashtags, $limit);
        if ($urls === []) {
            return collect();
        }

        $items = config('services.discovery.apify.enrich_metrics')
            ? $this->run(['directUrls' => $urls, 'resultsType' => 'posts', 'resultsLimit' => count($urls)])
            : $this->run($this->hashtagInput($hashtags, $limit));

        return collect($items)
            ->map(fn (array $item): ?DiscoveredPost => $this->normalize($item))
            ->filter()
            ->unique(fn (DiscoveredPost $post): string => $post->sourceUrl)
            ->take($limit)
            ->values();
    }

    /**
     * Stage 1: the hashtag pages, reduced to a de-duplicated list of post URLs.
     *
     * @param  list<string>  $hashtags
     * @return list<string>
     */
    private function candidateUrls(array $hashtags, int $limit): array
    {
        return collect($this->run($this->hashtagInput($hashtags, $limit)))
            ->pluck('url')
            ->filter(fn ($url): bool => is_string($url))
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $hashtags
     * @return array<string, mixed>
     */
    private function hashtagInput(array $hashtags, int $limit): array
    {
        return [
            'directUrls' => array_map(
                fn (string $tag): string => 'https://www.instagram.com/explore/tags/'.rawurlencode($tag).'/',
                $hashtags,
            ),
            'resultsType' => 'posts',
            'resultsLimit' => $limit,
            'addParentData' => false,
            // Filtered by the actor rather than by us: a post older than the feed
            // window can never be shown, so paying Apify to return it is waste.
            'onlyPostsNewerThan' => config('services.discovery.feed_window_days').' days',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function run(array $input): array
    {
        $token = (string) config('services.discovery.apify.token');
        $actor = (string) config('services.discovery.apify.actor');

        try {
            $response = Http::timeout((int) config('services.discovery.apify.timeout'))
                ->post("https://api.apify.com/v2/acts/{$actor}/run-sync-get-dataset-items?token={$token}", $input);
        } catch (Throwable $exception) {
            throw new ContentDiscoveryException('The Instagram scraper could not be reached.', $exception);
        }

        if (! $response->successful()) {
            throw new ContentDiscoveryException("The Instagram scraper failed (HTTP {$response->status()}).");
        }

        return $this->rows($response);
    }

    /** @return list<array<string, mixed>> */
    private function rows(Response $response): array
    {
        return array_values(array_filter((array) $response->json(), 'is_array'));
    }

    /** @param array<string, mixed> $item */
    private function normalize(array $item): ?DiscoveredPost
    {
        $url = $item['url'] ?? null;
        $username = $item['ownerUsername'] ?? null;

        if (! is_string($url) || ! is_string($username) || $username === '') {
            return null;
        }

        $thumbnailUrl = is_string($item['displayUrl'] ?? null) ? $item['displayUrl'] : null;
        $format = $this->format((string) ($item['type'] ?? 'Image'));

        return new DiscoveredPost(
            sourceUrl: $url,
            username: $username,
            displayName: $item['ownerFullName'] ?? null,
            avatarUrl: null,
            // Always zero: the actor documents no follower count on post-level
            // results, only on profile ones. Which is the whole reason a hashtag
            // result cannot be judged and has to be re-scraped as a profile.
            followers: 0,
            caption: (string) ($item['caption'] ?? ''),
            thumbnailUrl: $thumbnailUrl,
            // Apify returns -1 when Instagram hides the count; treat that as unknown.
            likes: max(0, (int) ($item['likesCount'] ?? 0)),
            comments: max(0, (int) ($item['commentsCount'] ?? 0)),
            views: max(0, (int) ($item['videoViewCount'] ?? $item['videoPlayCount'] ?? 0)),
            publishedAt: $this->publishedAt($item['timestamp'] ?? null),
            format: $format,
            hashtags: array_values(array_filter((array) ($item['hashtags'] ?? []), 'is_string')),
            externalId: isset($item['id']) ? (string) $item['id'] : null,
            shares: max(0, (int) ($item['sharesCount'] ?? 0)),
            mediaUrls: $format === 'carousel' ? InstagramCarouselMedia::urls($item, $thumbnailUrl) : [],
        );
    }

    private function publishedAt(mixed $timestamp): CarbonImmutable
    {
        try {
            return is_string($timestamp) ? CarbonImmutable::parse($timestamp) : CarbonImmutable::now();
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    private function format(string $type): string
    {
        return match ($type) {
            'Video' => 'reel',
            'Sidecar' => 'carousel',
            default => 'image',
        };
    }
}
