<?php

namespace App\Services\Discovery;

use App\Services\InstagramMediaProxy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use Throwable;

/**
 * Keeps sexually explicit, abusive, hateful, violent and otherwise unsafe
 * public Instagram material out of the shared recommendation catalogue.
 */
class ContentSafetyPolicy
{
    public function __construct(
        private readonly OpenAiClient $openai,
        private readonly InstagramMediaProxy $media,
    ) {}

    public function creator(DiscoveredProfile $profile): ContentSafetyDecision
    {
        $text = implode("\n", array_filter([
            $profile->username,
            $profile->displayName,
            $profile->bio,
        ]));

        return $this->inspect($text, null, $profile->metadata);
    }

    public function post(DiscoveredPost $post): ContentSafetyDecision
    {
        $text = trim($post->caption."\n".implode(' ', $post->hashtags));

        return $this->inspect($text, $post->thumbnailUrl, $post->metadata);
    }

    private function inspect(string $text, ?string $imageUrl, array $metadata): ContentSafetyDecision
    {
        if (! config('services.discovery.safety.enabled')) {
            return new ContentSafetyDecision(ContentSafetyDecision::ALLOWED);
        }

        $localReasons = [
            ...$this->blockedTerms($text),
            ...$this->metadataFlags($metadata),
        ];

        if ($localReasons !== []) {
            return new ContentSafetyDecision(ContentSafetyDecision::BLOCKED, array_values(array_unique($localReasons)));
        }

        if (! config('services.discovery.safety.use_openai') || ! config('services.openai.api_key')) {
            return new ContentSafetyDecision(ContentSafetyDecision::ALLOWED);
        }

        try {
            $input = [['type' => 'text', 'text' => $text !== '' ? $text : 'Instagram publication']];

            if ($imageUrl) {
                $moderationUrl = $this->moderationImageUrl($imageUrl);

                if ($moderationUrl === null) {
                    return $this->unavailable();
                }

                $input[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $moderationUrl],
                ];
            }

            $response = $this->openai->moderations()->create([
                'model' => (string) config('services.discovery.safety.model'),
                'input' => $input,
            ]);
            $result = $response->results[0] ?? null;

            if (! $result) {
                return $this->unavailable();
            }

            $blockedCategories = (array) config('services.discovery.safety.blocked_categories');
            $reasons = collect($result->categories)
                ->filter(fn ($category, string $name): bool => $category->violated && in_array($name, $blockedCategories, true))
                ->keys()
                ->map(fn (string $category): string => 'moderation:'.$category)
                ->values()
                ->all();

            return new ContentSafetyDecision(
                $reasons === [] ? ContentSafetyDecision::ALLOWED : ContentSafetyDecision::BLOCKED,
                $reasons,
            );
        } catch (Throwable $exception) {
            Log::warning('Instagram content safety moderation unavailable.', ['exception' => $exception]);

            return $this->unavailable();
        }
    }

    /** @return list<string> */
    private function blockedTerms(string $text): array
    {
        $normalized = Str::lower(Str::ascii($text));

        return collect((array) config('services.discovery.safety.blocked_terms'))
            ->filter(fn (mixed $term): bool => is_string($term) && trim($term) !== '')
            ->filter(function (string $term) use ($normalized): bool {
                $pattern = '/(?<![\pL\pN])'.preg_quote(Str::lower(Str::ascii(trim($term))), '/').'(?![\pL\pN])/u';

                return preg_match($pattern, $normalized) === 1;
            })
            ->map(fn (string $term): string => 'term:'.Str::lower(Str::ascii(trim($term))))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function metadataFlags(array $metadata): array
    {
        $blockedFlags = (array) config('services.discovery.safety.blocked_metadata_flags');
        $reasons = [];

        array_walk_recursive($metadata, function (mixed $value, string|int $key) use ($blockedFlags, &$reasons): void {
            $normalizedKey = Str::snake((string) $key);

            if (in_array($normalizedKey, $blockedFlags, true) && filter_var($value, FILTER_VALIDATE_BOOL)) {
                $reasons[] = 'metadata:'.$normalizedKey;
            }
        });

        return array_values(array_unique($reasons));
    }

    private function unavailable(): ContentSafetyDecision
    {
        if (config('services.discovery.safety.fail_closed')) {
            return new ContentSafetyDecision(ContentSafetyDecision::PENDING, ['moderation:unavailable']);
        }

        return new ContentSafetyDecision(ContentSafetyDecision::ALLOWED);
    }

    private function moderationImageUrl(string $imageUrl): ?string
    {
        if (! $this->media->supports($imageUrl)) {
            return $imageUrl;
        }

        return $this->media->moderationDataUrl($imageUrl);
    }
}
