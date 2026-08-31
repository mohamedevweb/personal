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
    public function queriesFor(User $user, bool $force = false): array
    {
        $profile = $user->creatorProfile;

        if ($profile
            && ! $force
            && is_array($profile->discovery_queries)
            && $profile->discovery_queries !== []
            && $this->cacheIsFresh($profile)) {
            return $profile->discovery_queries;
        }

        $seed = $this->querySeeds($profile);
        $queries = $this->expandQueries($seed, $profile) ?: $seed;
        $queries = $this->cleanQueries($queries);

        if ($profile) {
            $profile->forceFill([
                'discovery_queries' => $queries,
                'discovery_refreshed_at' => now(),
            ])->save();
        }

        return $queries;
    }

    /** @return list<string> */
    public function hashtagsFor(User $user): array
    {
        $profile = $user->creatorProfile;

        if ($profile
            && is_array($profile->discovery_hashtags)
            && $profile->discovery_hashtags !== []
            && $this->cacheIsFresh($profile)) {
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

    /** @param list<string> $seed @return list<string>|null */
    private function expandQueries(array $seed, ?CreatorProfile $profile): ?array
    {
        if ($seed === []) {
            return null;
        }

        $limit = (int) config('services.discovery.search_query_limit');
        $dna = $profile?->creator_dna ?? [];

        $result = $this->llm->object(
            "Generate {$limit} concise Instagram account-search queries for this Creator DNA. Queries should find "
            .'people who consistently publish in the same precise niche, such as AI founder, SaaS founder, indie '
            .'hacker, solopreneur or build in public. Use natural search phrases, not hashtags or generic reach terms.',
            json_encode([
                'primary_niche' => $dna['primary_niche'] ?? $profile?->niche,
                'sub_niches' => $dna['sub_niches'] ?? [],
                'topics' => $dna['topics'] ?? $profile?->topics ?? [],
                'primary_vertical' => $profile?->primary_vertical,
                'audience' => $dna['audience'] ?? [],
                'content_pillars' => $dna['content_pillars'] ?? [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: implode(', ', $seed),
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['queries'],
                'properties' => [
                    'queries' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        );

        $queries = $result['queries'] ?? null;

        return is_array($queries) ? array_values(array_filter($queries, 'is_string')) : null;
    }

    /** @return list<string> */
    private function querySeeds(?CreatorProfile $profile): array
    {
        $dna = $profile?->creator_dna ?? [];

        return $this->cleanQueries([
            (string) ($dna['primary_niche'] ?? $profile?->niche ?? ''),
            (string) ($profile?->primary_vertical ?? ''),
            ...($dna['sub_niches'] ?? []),
            ...($dna['topics'] ?? $profile?->topics ?? []),
            ...($dna['content_pillars'] ?? []),
        ]);
    }

    /** @param list<string> $queries @return list<string> */
    private function cleanQueries(array $queries): array
    {
        $blocked = (array) config('services.discovery.blocked_hashtags');

        return collect($queries)
            ->filter(fn (mixed $query): bool => is_string($query))
            ->map(fn (string $query): string => Str::of($query)
                ->lower()
                ->replaceMatches('/[^\pL\pN\s-]/u', ' ')
                ->squish()
                ->value())
            ->filter(fn (string $query): bool => strlen($query) > 2)
            ->reject(fn (string $query): bool => in_array(str_replace(' ', '', $query), $blocked, true))
            ->unique()
            ->take((int) config('services.discovery.search_query_limit'))
            ->values()
            ->all();
    }

    private function cacheIsFresh(CreatorProfile $profile): bool
    {
        return $profile->discovery_refreshed_at !== null
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
        $context = trim(($profile?->niche ?? '').'. Topics: '.implode(', ', $seed));

        $result = $this->llm->object(
            "You are an Instagram growth strategist. Given a creator's niche, return the {$limit} most relevant, "
            .'active Instagram hashtags to find high-performing posts in that niche. Bare tags, no # symbol, no spaces. '
            .'Return tags that describe the subject matter, the craft or the audience of this niche specifically. '
            .'Never return generic reach tags (viral, reels, explorepage, fyp, trending, instagood, follow4follow '
            .'and the like): established accounts in a niche do not use them, so they only surface accounts with no '
            .'audience trying to be seen.',
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
        $blocked = (array) config('services.discovery.blocked_hashtags');

        return collect($terms)
            ->map(fn (string $term): string => Str::of($term)->lower()->replaceMatches('/[^a-z0-9]/', '')->value())
            ->filter(fn (string $term): bool => strlen($term) > 2)
            // A model asked for "hashtags to find high-performing posts" reaches for
            // reach-bait however the prompt is worded, so the list is enforced here
            // rather than trusted to the instructions.
            ->reject(fn (string $term): bool => in_array($term, $blocked, true))
            ->unique()
            ->take((int) config('services.discovery.hashtag_limit'))
            ->values()
            ->all();
    }
}
