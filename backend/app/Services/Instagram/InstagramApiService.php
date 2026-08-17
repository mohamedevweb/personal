<?php

namespace App\Services\Instagram;

use App\Exceptions\InstagramIntegrationException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class InstagramApiService
{
    /** @return array<string, mixed> */
    public function fetchProfile(string $accessToken): array
    {
        $response = $this->get('/me', $accessToken, [
            'fields' => implode(',', [
                'id',
                'user_id',
                'username',
                'name',
                'account_type',
                'profile_picture_url',
                'followers_count',
                'follows_count',
                'media_count',
                'biography',
            ]),
        ]);

        return [
            'instagram_user_id' => (string) ($response['user_id'] ?? $response['id']),
            'username' => (string) $response['username'],
            'display_name' => $response['name'] ?? null,
            'bio' => $response['biography'] ?? null,
            'profile_picture_url' => $response['profile_picture_url'] ?? null,
            'account_type' => $response['account_type'] ?? null,
            'followers_count' => $response['followers_count'] ?? null,
            'follows_count' => $response['follows_count'] ?? null,
            'media_count' => $response['media_count'] ?? null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function fetchRecentMedia(string $accessToken): array
    {
        $response = $this->get('/me/media', $accessToken, [
            'fields' => implode(',', [
                'id',
                'caption',
                'media_type',
                'media_product_type',
                'media_url',
                'permalink',
                'thumbnail_url',
                'timestamp',
                'username',
                'like_count',
                'comments_count',
            ]),
            'limit' => config('services.instagram.media_limit'),
        ]);

        return array_map(function (array $media) use ($accessToken): array {
            return [
                'instagram_media_id' => (string) $media['id'],
                'media_type' => (string) $media['media_type'],
                'media_product_type' => $media['media_product_type'] ?? null,
                'caption' => $media['caption'] ?? null,
                'permalink' => $media['permalink'] ?? null,
                'media_url' => $media['media_url'] ?? null,
                'thumbnail_url' => $media['thumbnail_url'] ?? null,
                'like_count' => $media['like_count'] ?? null,
                'comments_count' => $media['comments_count'] ?? null,
                'metrics' => $this->fetchMediaInsights((string) $media['id'], $accessToken),
                'published_at' => $media['timestamp'] ?? null,
            ];
        }, $response['data'] ?? []);
    }

    /** @return array<string, int|float> */
    public function fetchMediaInsights(string $mediaId, string $accessToken): array
    {
        $metrics = ['views', 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions'];

        try {
            return $this->normalizeInsights($this->get("/{$mediaId}/insights", $accessToken, [
                'metric' => implode(',', $metrics),
            ]));
        } catch (InstagramIntegrationException $exception) {
            if ($exception->metaCode === '190') {
                throw $exception;
            }
        }

        // Metric availability varies by media type and API version. Preserve only
        // values Meta actually returns instead of inventing zeros.
        $available = [];
        foreach ($metrics as $metric) {
            try {
                $available += $this->normalizeInsights($this->get("/{$mediaId}/insights", $accessToken, [
                    'metric' => $metric,
                ]));
            } catch (InstagramIntegrationException $exception) {
                if ($exception->metaCode === '190') {
                    throw $exception;
                }
            }
        }

        return $available;
    }

    /** @return array<string, int|float> */
    private function normalizeInsights(array $response): array
    {
        $metrics = [];

        foreach ($response['data'] ?? [] as $insight) {
            $value = $insight['values'][0]['value'] ?? $insight['total_value']['value'] ?? null;
            if (is_int($value) || is_float($value)) {
                $metrics[$insight['name']] = $value;
            }
        }

        return $metrics;
    }

    /** @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function get(string $path, string $accessToken, array $query = []): array
    {
        $baseUrl = rtrim((string) config('services.instagram.graph_url'), '/');
        $version = trim((string) config('services.instagram.api_version'), '/');
        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(15)
            ->retry(2, 250, throw: false)
            ->get("{$baseUrl}/{$version}{$path}", $query);

        return $this->validatedJson($response);
    }

    /** @return array<string, mixed> */
    private function validatedJson(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('error', []);

        throw new InstagramIntegrationException(
            $error['message'] ?? 'Instagram could not complete this request.',
            isset($error['code']) ? (string) $error['code'] : null,
        );
    }
}
