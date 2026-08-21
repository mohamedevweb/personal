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

class HikerInstagramProvider implements InstagramDataProvider
{
    public function getProfile(string $username): ?DiscoveredProfile
    {
        $payload = $this->get('/v1/user/by/username', ['username' => ltrim($username, '@')]);
        $user = is_array($payload['user'] ?? null) ? $payload['user'] : $payload;

        return $this->normalizeProfile($user);
    }

    public function getPosts(string $username, int $limit, ?string $externalId = null): Collection
    {
        $profile = $externalId ? null : $this->getProfile($username);
        $userId = $externalId ?: $profile?->externalId;

        if (! $userId) {
            return collect();
        }

        $payload = $this->get('/v1/user/medias/chunk', ['user_id' => $userId]);
        $rows = is_array($payload[0] ?? null) ? $payload[0] : ($payload['items'] ?? []);

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): ?DiscoveredPost => $this->normalizePost($row, ltrim($username, '@'), $profile))
            ->filter()
            ->take($limit)
            ->values();
    }

    public function searchAccounts(string $query, int $limit): Collection
    {
        return $this->profilesFromPayload(
            $this->get('/v2/fbsearch/accounts', ['query' => $query, 'safe_int' => true]),
            $limit,
        );
    }

    public function getRelatedAccounts(string $externalId, int $limit, ?string $username = null): Collection
    {
        return $this->profilesFromPayload(
            $this->get('/v2/user/suggested/profiles', [
                'user_id' => $externalId,
                'expand_suggestion' => true,
                'safe_int' => true,
            ]),
            $limit,
        );
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    private function get(string $path, array $query): array
    {
        if ((string) config('services.discovery.hiker.api_key') === '') {
            throw new ContentDiscoveryException('HikerAPI is not configured. Set HIKER_API_KEY or choose another discovery driver.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.discovery.hiker.base_url'), '/'))
                ->acceptJson()
                ->withHeaders(['x-access-key' => (string) config('services.discovery.hiker.api_key')])
                ->timeout((int) config('services.discovery.hiker.timeout'))
                ->retry(
                    (int) config('services.discovery.hiker.retries'),
                    (int) config('services.discovery.hiker.retry_delay_ms'),
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
            throw new ContentDiscoveryException('HikerAPI could not be reached.', $exception);
        }

        return $this->validatedJson($response);
    }

    /** @return array<string, mixed> */
    private function validatedJson(Response $response): array
    {
        if (! $response->successful()) {
            throw new ContentDiscoveryException("HikerAPI failed (HTTP {$response->status()}).");
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /** @param array<string, mixed> $payload @return Collection<int, DiscoveredProfile> */
    private function profilesFromPayload(array $payload, int $limit): Collection
    {
        return collect($this->userRows($payload))
            ->map(function (array $row): ?DiscoveredProfile {
                $user = is_array($row['user'] ?? null) ? $row['user'] : $row;

                return $this->normalizeProfile($user);
            })
            ->filter(fn (?DiscoveredProfile $profile): bool => $profile !== null && ! $profile->isPrivate)
            ->unique(fn (DiscoveredProfile $profile): string => $profile->externalId ?: $profile->username)
            ->take($limit)
            ->values();
    }

    /** @param array<string, mixed> $payload @return list<array<string, mixed>> */
    private function userRows(array $payload): array
    {
        foreach (['users', 'suggested_users', 'suggestions', 'accounts', 'data'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }

        if (array_is_list($payload)) {
            $rows = is_array($payload[0] ?? null) && array_is_list($payload[0]) ? $payload[0] : $payload;

            return array_values(array_filter($rows, 'is_array'));
        }

        return [];
    }

    /** @param array<string, mixed> $user */
    private function normalizeProfile(array $user): ?DiscoveredProfile
    {
        $username = $user['username'] ?? null;
        $externalId = $user['pk'] ?? $user['pk_id'] ?? $user['id'] ?? null;

        if (! is_string($username) || $username === '') {
            return null;
        }

        return new DiscoveredProfile(
            username: $username,
            displayName: $this->nullableString($user['full_name'] ?? null),
            avatarUrl: $this->nullableString($user['profile_pic_url_hd'] ?? $user['profile_pic_url'] ?? null),
            followers: max(0, (int) ($user['follower_count'] ?? 0)),
            posts: collect(),
            bio: $this->nullableString($user['biography'] ?? null),
            externalId: is_int($externalId) || is_string($externalId) ? (string) $externalId : null,
            isPrivate: (bool) ($user['is_private'] ?? false),
            metadata: array_filter([
                'category' => $user['category_name'] ?? $user['business_category_name'] ?? null,
                'external_url' => $user['external_url'] ?? null,
                'is_verified' => $user['is_verified'] ?? null,
                'media_count' => $user['media_count'] ?? null,
                'following_count' => $user['following_count'] ?? null,
                'providers' => [
                    'hiker' => [
                        'raw_data' => Arr::only($user, [
                            'pk', 'pk_id', 'id', 'username', 'full_name', 'biography',
                            'follower_count', 'following_count', 'media_count', 'is_private',
                            'is_verified', 'category_name', 'business_category_name',
                            'profile_pic_url', 'profile_pic_url_hd',
                        ]),
                    ],
                ],
            ], fn (mixed $value): bool => $value !== null),
        );
    }

    private function normalizePost(array $row, string $requestedUsername, ?DiscoveredProfile $profile): ?DiscoveredPost
    {
        $code = $this->nullableString($row['code'] ?? null);
        $externalId = $row['pk'] ?? $row['id'] ?? null;
        $owner = is_array($row['user'] ?? null) ? $row['user'] : [];
        $username = $profile?->username ?: $this->nullableString($owner['username'] ?? null) ?: $requestedUsername;

        if (! $username || (! $code && $externalId === null)) {
            return null;
        }

        $format = $this->format((int) ($row['media_type'] ?? 1), (string) ($row['product_type'] ?? ''));
        $caption = (string) ($row['caption_text'] ?? data_get($row, 'caption.text', ''));

        $thumbnailUrl = $this->thumbnail($row);

        return new DiscoveredPost(
            sourceUrl: $code
                ? 'https://www.instagram.com/'.($format === 'reel' ? 'reel' : 'p').'/'.$code.'/'
                : 'https://www.instagram.com/'.$username.'/',
            username: $username,
            displayName: $profile?->displayName ?: $this->nullableString($owner['full_name'] ?? null),
            avatarUrl: $profile?->avatarUrl ?: $this->nullableString($owner['profile_pic_url'] ?? null),
            followers: $profile?->followers ?? 0,
            caption: $caption,
            thumbnailUrl: $thumbnailUrl,
            likes: max(0, (int) ($row['like_count'] ?? 0)),
            comments: max(0, (int) ($row['comment_count'] ?? 0)),
            views: max(0, (int) ($row['play_count'] ?? $row['view_count'] ?? 0)),
            publishedAt: $this->publishedAt($row['taken_at'] ?? $row['taken_at_ts'] ?? null),
            format: $format,
            hashtags: $this->hashtags($caption),
            externalId: is_int($externalId) || is_string($externalId) ? (string) $externalId : null,
            metadata: array_filter([
                'code' => $code,
                'product_type' => $row['product_type'] ?? null,
                'video_duration' => $row['video_duration'] ?? null,
                'is_paid_partnership' => $row['is_paid_partnership'] ?? null,
                'providers' => [
                    'hiker' => [
                        'raw_data' => Arr::only($row, [
                            'pk', 'id', 'code', 'media_type', 'product_type', 'taken_at',
                            'taken_at_ts', 'like_count', 'comment_count', 'play_count',
                            'view_count', 'video_duration', 'is_paid_partnership',
                        ]),
                    ],
                ],
            ], fn (mixed $value): bool => $value !== null),
            mediaUrls: $format === 'carousel' ? InstagramCarouselMedia::urls($row, $thumbnailUrl) : [],
        );
    }

    /** @param array<string, mixed> $row */
    private function thumbnail(array $row): ?string
    {
        $url = $row['thumbnail_url'] ?? data_get($row, 'image_versions.0.url');

        return $this->nullableString($url);
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

    private function format(int $mediaType, string $productType): string
    {
        if ($mediaType === 8) {
            return 'carousel';
        }

        return $mediaType === 2 || in_array(strtolower($productType), ['clips', 'reels'], true) ? 'reel' : 'image';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
