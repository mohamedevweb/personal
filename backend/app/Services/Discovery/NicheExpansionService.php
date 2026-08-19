<?php

namespace App\Services\Discovery;

use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\Llm\LlmJsonService;
use Illuminate\Support\Str;

/**
 * Turns a creator's thin profile (niche + topics) into a richer set of Instagram
 * hashtags to scrape. An LLM does the expansion when a key is configured; without
 * one it falls back to the profile's own terms. Results are cached on the profile
 * so discovery does not pay for an expansion on every run.
 */
class NicheExpansionService
{
    public function __construct(private readonly LlmJsonService $llm) {}

    /** @return list<string> */
    public function hashtagsFor(User $user): array
    {
        $profile = $user->creatorProfile;

        if ($profile && $this->cacheIsFresh($profile)) {
            return $profile->discovery_hashtags;
        }

        $seed = $this->seedTerms($profile);
        $hashtags = $this->expand($seed, $profile) ?: $seed;
        $hashtags = $this->clean($hashtags);

        if ($profile) {
            $profile->forceFill([
                'discovery_hashtags' => $hashtags,
                'discovery_refreshed_at' => now(),
            ])->save();
        }

        return $hashtags;
    }

    private function cacheIsFresh(CreatorProfile $profile): bool
    {
        return is_array($profile->discovery_hashtags)
            && $profile->discovery_hashtags !== []
            && $profile->discovery_refreshed_at !== null
            && $profile->discovery_refreshed_at->isAfter(now()->subDays((int) config('services.discovery.cache_days')));
    }

    /**
     * @param  list<string>  $seed
     * @return list<string>|null
     */
    private function expand(array $seed, ?CreatorProfile $profile): ?array
    {
        if ($seed === []) {
            return null;
        }

        $limit = (int) config('services.discovery.hashtag_limit');
        $context = trim(($profile?->niche ?? '').' — topics: '.implode(', ', $seed));

        $result = $this->llm->object(
            "You are an Instagram growth strategist. Given a creator's niche, return the {$limit} most relevant, "
            .'active Instagram hashtags to find high-performing posts in that niche. Bare tags, no # symbol, no spaces.',
            "Creator niche and topics: {$context}",
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['hashtags'],
                'properties' => [
                    'hashtags' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
        );

        $hashtags = $result['hashtags'] ?? null;

        return is_array($hashtags) ? array_values(array_filter($hashtags, 'is_string')) : null;
    }

    /** @return list<string> */
    private function seedTerms(?CreatorProfile $profile): array
    {
        return $this->clean([
            ...preg_split('/\W+/', (string) ($profile?->niche ?? '')) ?: [],
            ...($profile?->topics ?? []),
        ]);
    }

    /**
     * @param  list<string>  $terms
     * @return list<string>
     */
    private function clean(array $terms): array
    {
        return collect($terms)
            ->map(fn (string $term): string => Str::of($term)->lower()->replaceMatches('/[^a-z0-9]/', '')->value())
            ->filter(fn (string $term): bool => strlen($term) > 2)
            ->unique()
            ->take((int) config('services.discovery.hashtag_limit'))
            ->values()
            ->all();
    }
}
