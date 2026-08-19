<?php

namespace App\Services\Instagram;

use App\Exceptions\InstagramIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

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
                'website',
            ]),
        ]);

        return [
            'instagram_user_id' => (string) ($response['user_id'] ?? $response['id']),
            'username' => (string) $response['username'],
            'display_name' => $response['name'] ?? null,
            'bio' => $response['biography'] ?? null,
            'website' => $response['website'] ?? null,
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
                'metrics' => $this->fetchMediaInsights(
                    (string) $media['id'],
                    $media['media_product_type'] ?? null,
                    $accessToken,
                ),
                'published_at' => $media['timestamp'] ?? null,
            ];
        }, $response['data'] ?? []);
    }

    /**
     * Metric availability varies by media type, so the request only asks for the
     * metrics this media can actually report. A rejected batch falls back once to
     * the two metrics every type supports rather than probing each metric
     * individually — a per-metric loop multiplies a sync by the metric count and
     * runs into Meta's rate limits well before it finishes.
     *
     * @return array<string, int|float>
     */
    public function fetchMediaInsights(
        string $mediaId,
        ?string $mediaProductType,
        string $accessToken,
    ): array {
        $metrics = $this->supportedMetrics($mediaProductType);
        $insights = $this->tryInsights($mediaId, $metrics, $accessToken);

        if ($insights !== null) {
            return $insights;
        }

        $fallback = array_values(array_intersect($metrics, ['views', 'reach']));

        if ($fallback === [] || $fallback === $metrics) {
            return [];
        }

        return $this->tryInsights($mediaId, $fallback, $accessToken) ?? [];
    }

    /** @return list<string> */
    private function supportedMetrics(?string $mediaProductType): array
    {
        // Stories expose replies and never expose likes, comments or saves.
        if (strtoupper((string) $mediaProductType) === 'STORY') {
            return ['views', 'reach', 'replies', 'shares', 'total_interactions'];
        }

        return ['views', 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions'];
    }

    /** @param list<string> $metrics
     * @return array<string, int|float>|null
     */
    private function tryInsights(string $mediaId, array $metrics, string $accessToken): ?array
    {
        try {
            return $this->normalizeInsights($this->get("/{$mediaId}/insights", $accessToken, [
                'metric' => implode(',', $metrics),
            ]));
        } catch (InstagramIntegrationException $exception) {
            // An invalid or expired token fails the whole sync; anything else is a
            // per-media metric problem the import can carry on without.
            if ($exception->metaCode === '190') {
                throw $exception;
            }

            return null;
        }
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
            // Only transport failures and Meta-side errors are worth retrying. A
            // 4xx is a decision about the request itself and repeating it just
            // multiplies the call volume of a sync.
            ->retry(2, 250, function (Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                return $exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429);
            }, throw: false)
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
