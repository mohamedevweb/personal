<?php

namespace App\Services\Instagram;

use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InstagramMediaProxy
{
    /** @var list<string> */
    private const ALLOWED_HOST_SUFFIXES = ['.fbcdn.net', '.cdninstagram.com'];

    public function supports(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user'], $parts['pass'], $parts['port'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return collect(self::ALLOWED_HOST_SUFFIXES)
            ->contains(fn (string $suffix): bool => str_ends_with($host, $suffix));
    }

    public function response(string $sourceUrl, string $cacheKey): ?Response
    {
        $image = $this->image($sourceUrl, $cacheKey);

        return $image ? $this->imageResponse($image['body'], $image['content_type']) : null;
    }

    public function moderationDataUrl(string $sourceUrl): ?string
    {
        $image = $this->image($sourceUrl, 'moderation:'.hash('sha256', $sourceUrl));

        return $image
            ? 'data:'.$image['content_type'].';base64,'.base64_encode($image['body'])
            : null;
    }

    /** @return array{body: string, content_type: string}|null */
    private function image(string $sourceUrl, string $cacheKey): ?array
    {
        if (! $this->supports($sourceUrl)) {
            return null;
        }

        $disk = Storage::disk((string) config('services.instagram_media_proxy.disk'));
        $base = 'instagram-media/'.hash('sha256', $cacheKey);
        $mediaPath = $base.'.bin';
        $metadataPath = $base.'.json';
        $cached = $this->cached($disk, $mediaPath, $metadataPath);

        if ($cached && $cached['fresh']) {
            return ['body' => $cached['body'], 'content_type' => $cached['content_type']];
        }

        $upstream = $this->fetch($sourceUrl);
        if ($upstream) {
            $body = $upstream->body();
            $contentType = strtolower(trim((string) $upstream->header('Content-Type')));

            if ($this->validImage($body, $contentType)) {
                $disk->put($mediaPath, $body);
                $disk->put($metadataPath, json_encode([
                    'content_type' => $contentType,
                    'source_hash' => hash('sha256', $sourceUrl),
                    'cached_at' => now()->toIso8601String(),
                ], JSON_THROW_ON_ERROR));

                return ['body' => $body, 'content_type' => $contentType];
            }
        }

        return $cached ? ['body' => $cached['body'], 'content_type' => $cached['content_type']] : null;
    }

    /** @return array{body: string, content_type: string, fresh: bool}|null */
    private function cached($disk, string $mediaPath, string $metadataPath): ?array
    {
        if (! $disk->exists($mediaPath) || ! $disk->exists($metadataPath)) {
            return null;
        }

        $metadata = json_decode((string) $disk->get($metadataPath), true);
        $contentType = is_array($metadata) ? ($metadata['content_type'] ?? null) : null;

        if (! is_string($contentType) || ! str_starts_with($contentType, 'image/')) {
            return null;
        }

        $maxAge = max(1, (int) config('services.instagram_media_proxy.cache_days')) * 86_400;

        return [
            'body' => (string) $disk->get($mediaPath),
            'content_type' => $contentType,
            'fresh' => $disk->lastModified($mediaPath) >= now()->timestamp - $maxAge,
        ];
    }

    private function fetch(string $url): ?ClientResponse
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'image/avif,image/webp,image/jpeg,image/png,image/*',
                'User-Agent' => 'PersonalMediaProxy/1.0',
            ])
                ->timeout((int) config('services.instagram_media_proxy.timeout'))
                ->withOptions(['allow_redirects' => false])
                ->retry(2, 200, throw: false)
                ->get($url);

            return $response->successful() ? $response : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function validImage(string $body, string $contentType): bool
    {
        return $body !== ''
            && str_starts_with($contentType, 'image/')
            && strlen($body) <= (int) config('services.instagram_media_proxy.max_bytes');
    }

    private function imageResponse(string $body, string $contentType): Response
    {
        $browserMaxAge = max(1, (int) config('services.instagram_media_proxy.browser_cache_hours')) * 3_600;

        return response($body, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => "public, max-age={$browserMaxAge}, stale-while-revalidate=86400",
            'Content-Type' => $contentType,
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
