<?php

namespace App\Services\Discovery;

use App\Exceptions\ContentDiscoveryException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class ScrapeCreatorsInstagramProvider implements InstagramDataProvider
{
    public function getProfile(string $username): ?DiscoveredProfile
    {
        $payload = $this->get('/v1/instagram/profile', array_filter([
            'handle' => ltrim($username, '@'),
            'cache_max_age' => config('services.discovery.scrapecreators.cache_max_age'),
        ]), allowNotFound: true);
        $user = data_get($payload, 'data.user');

        return is_array($user) ? $this->normalizeProfile($user, includePosts: true) : null;
    }

    public function getPosts(string $username, int $limit, ?string $externalId = null): Collection
    {
        $payload = $this->get('/v2/instagram/user/posts', [
            'handle' => ltrim($username, '@'),
        ], allowNotFound: true);
        $profile = $this->profileFromPostsPayload($payload, $username);

        return collect(is_array($payload['items'] ?? null) ? $payload['items'] : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): ?DiscoveredPost => $this->normalizePost($row, ltrim($username, '@'), $profile))
            ->filter()
            ->unique(fn (DiscoveredPost $post): string => $post->externalId ?: $post->sourceUrl)
            ->take($limit)
            ->values();
    }

    public function searchAccounts(string $query, int $limit): Collection
    {
        $payload = $this->get('/v1/instagram/search/profiles', ['query' => $query]);

        return collect(is_array($payload['profiles'] ?? null) ? $payload['profiles'] : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): ?DiscoveredProfile => $this->normalizeProfile($row))
            ->filter(fn (?DiscoveredProfile $profile): bool => $profile !== null && ! $profile->isPrivate)
            ->unique(fn (DiscoveredProfile $profile): string => $profile->externalId ?: $profile->username)
            ->take($limit)
            ->values();
    }

    public function getRelatedAccounts(string $externalId, int $limit, ?string $username = null): Collection
    {
        if (! $username) {
            return collect();
        }

        $payload = $this->get('/v1/instagram/profile', array_filter([
            'handle' => ltrim($username, '@'),
            'cache_max_age' => config('services.discovery.scrapecreators.cache_max_age'),
        ]), allowNotFound: true);
        $edges = data_get($payload, 'data.user.edge_related_profiles.edges', []);

        return collect(is_array($edges) ? $edges : [])
            ->map(function (mixed $edge): ?DiscoveredProfile {
                $user = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : $edge;

                return is_array($user) ? $this->normalizeProfile($user) : null;
            })
            ->filter(fn (?DiscoveredProfile $profile): bool => $profile !== null && ! $profile->isPrivate)
            ->unique(fn (DiscoveredProfile $profile): string => $profile->externalId ?: $profile->username)
            ->take($limit)
            ->values();
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    private function get(string $path, array $query, bool $allowNotFound = false): array
    {
        if ((string) config('services.discovery.scrapecreators.api_key') === '') {
            throw new ContentDiscoveryException('ScrapeCreators is not configured. Set SCRAPECREATORS_API_KEY or choose another discovery driver.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.discovery.scrapecreators.base_url'), '/'))
                ->acceptJson()
                ->withHeaders(['x-api-key' => (string) config('services.discovery.scrapecreators.api_key')])
                ->timeout((int) config('services.discovery.scrapecreators.timeout'))
                ->retry(
                    (int) config('services.discovery.scrapecreators.retries'),
                    (int) config('services.discovery.scrapecreators.retry_delay_ms'),
                    function (Throwable $exception): bool {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        return $exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429);
                    },
                    throw: false,
                )
                ->get($path, $query);
        } catch (Throwable $exception) {
            throw new ContentDiscoveryException('ScrapeCreators could not be reached.', $exception);
        }

        return $this->validatedJson($response, $allowNotFound);
    }

    /** @return array<string, mixed> */
    private function validatedJson(Response $response, bool $allowNotFound): array
    {
        if ($allowNotFound && $response->notFound()) {
            return [];
        }

        if (! $response->successful()) {
            $detail = $response->json('message') ?? $response->json('error');
            $detail = is_string($detail) ? trim($detail) : '';
            $suffix = $detail !== '' ? ' '.mb_substr($detail, 0, 300) : '';

            throw new ContentDiscoveryException("ScrapeCreators failed (HTTP {$response->status()}).{$suffix}");
        }

        $payload = $response->json();

        if (! is_array($payload) || (($payload['success'] ?? true) !== true)) {
            throw new ContentDiscoveryException('ScrapeCreators returned an invalid response.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $user */
    private function normalizeProfile(array $user, bool $includePosts = false): ?DiscoveredProfile
    {
        $username = $this->nullableString($user['username'] ?? null);

        if (! $username) {
            return null;
        }

        $externalId = $user['pk'] ?? $user['pk_id'] ?? $user['id'] ?? null;
        $followers = $user['follower_count'] ?? data_get($user, 'edge_followed_by.count', 0);
        $following = $user['following_count'] ?? data_get($user, 'edge_follow.count');
        $postsCount = $user['media_count'] ?? data_get($user, 'edge_owner_to_timeline_media.count');
        $rows = $includePosts ? data_get($user, 'edge_owner_to_timeline_media.edges', []) : [];
        $profile = new DiscoveredProfile(
            username: $username,
            displayName: $this->nullableString($user['full_name'] ?? null),
            avatarUrl: $this->nullableString($user['profile_pic_url_hd'] ?? $user['profile_pic_url'] ?? null),
            followers: max(0, (int) $followers),
            posts: collect(),
            bio: $this->nullableString($user['biography'] ?? null),
            externalId: is_int($externalId) || is_string($externalId) ? (string) $externalId : null,
            isPrivate: (bool) ($user['is_private'] ?? false),
            metadata: array_filter([
                'category' => $user['category_name'] ?? null,
                'external_url' => $user['external_url'] ?? null,
                'is_verified' => $user['is_verified'] ?? null,
                'media_count' => $postsCount,
                'following_count' => $following,
                'providers' => [
                    'scrapecreators' => [
                        'raw_data' => Arr::only($user, [
                            'id', 'pk', 'username', 'full_name', 'biography', 'follower_count',
                            'following_count', 'media_count', 'is_private', 'is_verified',
                            'is_business_account', 'is_professional_account', 'category_name',
                            'profile_pic_url', 'profile_pic_url_hd', 'matched_from',
                        ]),
                    ],
                ],
            ], fn (mixed $value): bool => $value !== null),
        );

        if (! is_array($rows)) {
            return $profile;
        }

        $posts = collect($rows)
            ->map(function (mixed $edge) use ($profile): ?DiscoveredPost {
                $row = is_array($edge) && is_array($edge['node'] ?? null) ? $edge['node'] : $edge;

                return is_array($row) ? $this->normalizePost($row, $profile->username, $profile) : null;
            })
            ->filter()
            ->unique(fn (DiscoveredPost $post): string => $post->externalId ?: $post->sourceUrl)
            ->values();

        return new DiscoveredProfile(
            username: $profile->username,
            displayName: $profile->displayName,
            avatarUrl: $profile->avatarUrl,
            followers: $profile->followers,
            posts: $posts,
            bio: $profile->bio,
            externalId: $profile->externalId,
            isPrivate: $profile->isPrivate,
            metadata: $profile->metadata,
        );
    }

    /** @param array<string, mixed> $payload */
    private function profileFromPostsPayload(array $payload, string $requestedUsername): ?DiscoveredProfile
    {
        $user = is_array($payload['user'] ?? null) ? $payload['user'] : [];
        $user['username'] ??= ltrim($requestedUsername, '@');

        return $this->normalizeProfile($user);
    }

    /** @param array<string, mixed> $row */
    private function normalizePost(array $row, string $requestedUsername, ?DiscoveredProfile $profile): ?DiscoveredPost
    {
        $code = $this->nullableString($row['code'] ?? $row['shortcode'] ?? null);
        $externalId = $row['pk'] ?? $row['media_id'] ?? $row['id'] ?? null;
        $owner = is_array($row['user'] ?? null) ? $row['user'] : (is_array($row['owner'] ?? null) ? $row['owner'] : []);
        $username = $profile?->username ?: $this->nullableString($owner['username'] ?? null) ?: ltrim($requestedUsername, '@');

        if (! $username || (! $code && $externalId === null)) {
            return null;
        }

        if (is_string($externalId) && str_contains($externalId, '_')) {
            $externalId = explode('_', $externalId, 2)[0];
        }

        $format = $this->format($row);
        $caption = $row['caption_text'] ?? $row['caption'] ?? data_get($row, 'edge_media_to_caption.edges.0.node.text', '');
        $caption = is_array($caption) ? ($caption['text'] ?? '') : $caption;
        $caption = is_string($caption) ? $caption : '';

        return new DiscoveredPost(
            sourceUrl: $code
                ? 'https://www.instagram.com/'.($format === 'reel' ? 'reel' : 'p').'/'.$code.'/'
                : 'https://www.instagram.com/'.$username.'/',
            username: $username,
            displayName: $profile?->displayName ?: $this->nullableString($owner['full_name'] ?? null),
            avatarUrl: $profile?->avatarUrl ?: $this->nullableString($owner['profile_pic_url'] ?? null),
            followers: $profile?->followers ?? 0,
            caption: $caption,
            thumbnailUrl: $this->thumbnail($row),
            likes: max(0, (int) ($row['like_count'] ?? data_get($row, 'edge_media_preview_like.count', 0))),
            comments: max(0, (int) ($row['comment_count'] ?? data_get($row, 'edge_media_to_comment.count', 0))),
            views: max(0, (int) ($row['play_count'] ?? $row['video_play_count'] ?? $row['video_view_count'] ?? $row['view_count'] ?? 0)),
            publishedAt: $this->publishedAt($row['taken_at'] ?? $row['taken_at_timestamp'] ?? null),
            format: $format,
            hashtags: $this->hashtags($caption),
            externalId: is_int($externalId) || is_string($externalId) ? (string) $externalId : null,
            shares: max(0, (int) ($row['reshare_count'] ?? $row['share_count'] ?? 0)),
            metadata: array_filter([
                'code' => $code,
                'product_type' => $row['product_type'] ?? null,
                'video_duration' => $row['video_duration'] ?? null,
                'is_paid_partnership' => $row['is_paid_partnership'] ?? null,
                'providers' => [
                    'scrapecreators' => [
                        'raw_data' => Arr::only($row, [
                            'pk', 'id', 'code', 'shortcode', '__typename', 'media_type',
                            'product_type', 'taken_at', 'taken_at_timestamp', 'like_count',
                            'comment_count', 'play_count', 'video_play_count', 'video_view_count',
                            'view_count', 'reshare_count', 'share_count', 'video_duration',
                            'is_paid_partnership',
                        ]),
                    ],
                ],
            ], fn (mixed $value): bool => $value !== null),
        );
    }

    /** @param array<string, mixed> $row */
    private function thumbnail(array $row): ?string
    {
        return $this->nullableString(
            $row['thumbnail_url']
                ?? $row['thumbnail_src']
                ?? $row['display_uri']
                ?? $row['display_url']
                ?? data_get($row, 'image_versions2.candidates.0.url')
                ?? data_get($row, 'image_versions.0.url'),
        );
    }

    /** @return list<string> */
    private function hashtags(string $caption): array
    {
        preg_match_all('/#([\pL\pN_]+)/u', $caption, $matches);

        return array_values(array_unique(array_map('mb_strtolower', $matches[1] ?? [])));
    }

    private function publishedAt(mixed $timestamp): CarbonImmutable
    {
        try {
            if (is_int($timestamp) || (is_string($timestamp) && ctype_digit($timestamp))) {
                return CarbonImmutable::createFromTimestamp((int) $timestamp);
            }

            return is_string($timestamp) ? CarbonImmutable::parse($timestamp) : CarbonImmutable::now();
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    /** @param array<string, mixed> $row */
    private function format(array $row): string
    {
        $mediaType = (int) ($row['media_type'] ?? 0);
        $productType = strtolower((string) ($row['product_type'] ?? ''));
        $typeName = strtolower((string) ($row['__typename'] ?? ''));

        if ($mediaType === 8 || str_contains($typeName, 'sidecar')) {
            return 'carousel';
        }

        return $mediaType === 2
            || in_array($productType, ['clips', 'reels'], true)
            || str_contains($typeName, 'video') ? 'reel' : 'image';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
