<?php

namespace App\Services\Discovery;

use App\Exceptions\ContentDiscoveryException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Scrapes whole accounts (not hashtag pages) through Apify's Instagram Profile
 * Scraper actor (apify/instagram-profile-scraper). Each result is a profile with
 * its real follower count and a `latestPosts` array, which together let the
 * engagement rate be measured per account.
 */
class ApifyProfileScraperService implements ProfileDiscoveryService
{
    public function profiles(array $usernames, int $postsPerProfile): Collection
    {
        if ((string) config('services.discovery.apify.token') === '') {
            throw new ContentDiscoveryException('Apify is not configured. Set APIFY_TOKEN or use DISCOVERY_DRIVER=mock.');
        }

        $usernames = array_values(array_unique(array_filter($usernames)));
        if ($usernames === []) {
            return collect();
        }

        $items = $this->run([
            'usernames' => $usernames,
            'resultsLimit' => $postsPerProfile,
        ]);

        return collect($items)
            ->map(fn (array $item): ?DiscoveredProfile => $this->normalize($item, $postsPerProfile))
            ->filter()
            ->unique(fn (DiscoveredProfile $profile): string => $profile->username)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function run(array $input): array
    {
        $token = (string) config('services.discovery.apify.token');
        $actor = (string) config('services.discovery.apify.profile_actor');

        try {
            $response = Http::timeout((int) config('services.discovery.apify.timeout'))
                ->post("https://api.apify.com/v2/acts/{$actor}/run-sync-get-dataset-items?token={$token}", $input);
        } catch (Throwable $exception) {
            throw new ContentDiscoveryException('The Instagram profile scraper could not be reached.', $exception);
        }

        if (! $response->successful()) {
            throw new ContentDiscoveryException("The Instagram profile scraper failed (HTTP {$response->status()}).");
        }

        return $this->rows($response);
    }

    /** @return list<array<string, mixed>> */
    private function rows(Response $response): array
    {
        return array_values(array_filter((array) $response->json(), 'is_array'));
    }

    /** @param array<string, mixed> $item */
    private function normalize(array $item, int $postsPerProfile): ?DiscoveredProfile
    {
        $username = $item['username'] ?? null;

        if (! is_string($username) || $username === '') {
            return null;
        }

        $followers = (int) ($item['followersCount'] ?? 0);
        $displayName = $item['fullName'] ?? null;
        $avatarUrl = $item['profilePicUrl'] ?? null;

        $posts = collect((array) ($item['latestPosts'] ?? []))
            ->filter('is_array')
            ->map(fn (array $post): ?DiscoveredPost => $this->normalizePost($post, $username, $displayName, $avatarUrl, $followers))
            ->filter()
            ->take($postsPerProfile)
            ->values();

        return new DiscoveredProfile(
            username: $username,
            displayName: is_string($displayName) ? $displayName : null,
            avatarUrl: is_string($avatarUrl) ? $avatarUrl : null,
            followers: $followers,
            posts: $posts,
            bio: is_string($item['biography'] ?? null) ? $item['biography'] : null,
        );
    }

    /** @param array<string, mixed> $post */
    private function normalizePost(array $post, string $username, ?string $displayName, ?string $avatarUrl, int $followers): ?DiscoveredPost
    {
        $url = $post['url'] ?? null;

        if (! is_string($url)) {
            return null;
        }

        return new DiscoveredPost(
            sourceUrl: $url,
            username: $username,
            displayName: is_string($displayName) ? $displayName : null,
            avatarUrl: is_string($avatarUrl) ? $avatarUrl : null,
            followers: $followers,
            caption: (string) ($post['caption'] ?? ''),
            thumbnailUrl: $post['displayUrl'] ?? null,
            // Apify returns -1 when Instagram hides the count; treat that as unknown.
            likes: max(0, (int) ($post['likesCount'] ?? 0)),
            comments: max(0, (int) ($post['commentsCount'] ?? 0)),
            views: max(0, (int) ($post['videoViewCount'] ?? $post['videoPlayCount'] ?? 0)),
            publishedAt: $this->publishedAt($post['timestamp'] ?? null),
            format: $this->format((string) ($post['type'] ?? 'Image')),
            hashtags: array_values(array_filter((array) ($post['hashtags'] ?? []), 'is_string')),
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
