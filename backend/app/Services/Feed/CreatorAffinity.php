<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Services\Discovery\CanonicalCreatorVerticals;
use Illuminate\Support\Str;

/**
 * Measures how closely a benchmark creator matches the member's Creator DNA.
 * Performance still decides whether a post is useful; this score decides which
 * useful posts belong in this particular creator's feed.
 */
class CreatorAffinity
{
    private const VERTICAL_WEIGHT = 0.4;

    private const TOPIC_WEIGHT = 0.6;

    public function __construct(private readonly CanonicalCreatorVerticals $verticals) {}

    public function score(?CreatorProfile $profile, Creator $creator, ?ContentPost $post = null): ?float
    {
        if (! $profile) {
            return null;
        }

        $profileVertical = $this->verticals->canonical($profile->primary_vertical)
            ?? $this->verticals->fromSignals($this->profileSignals($profile));
        $creatorVertical = $this->verticals->canonical($creator->niche);
        $profileTokens = $this->tokens($this->profileSignals($profile));

        if ($profileVertical === null && $profileTokens === []) {
            return null;
        }

        $vertical = $profileVertical !== null && $profileVertical === $creatorVertical
            ? self::VERTICAL_WEIGHT
            : 0.0;
        $candidateTokens = $this->tokens([
            $creator->niche,
            ...($creator->niche_topics ?? []),
            $creator->bio,
            ...($post?->tags ?? []),
            $post?->hook,
        ]);
        $coverage = $profileTokens === []
            ? 0.0
            : count(array_intersect($profileTokens, $candidateTokens)) / count($profileTokens);

        return round(min(1.0, $vertical + (self::TOPIC_WEIGHT * sqrt($coverage))), 4);
    }

    /** @return list<string|null> */
    private function profileSignals(CreatorProfile $profile): array
    {
        $dna = $profile->creator_dna ?? [];

        return [
            $dna['primary_niche'] ?? $profile->niche,
            ...($dna['sub_niches'] ?? []),
            ...($dna['topics'] ?? $profile->topics ?? []),
            ...($dna['content_pillars'] ?? []),
            ...($dna['audience'] ?? []),
        ];
    }

    /** @param list<mixed> $signals @return list<string> */
    private function tokens(array $signals): array
    {
        $blocked = [
            'avec', 'dans', 'des', 'for', 'from', 'les', 'pour', 'the', 'une',
            'and', 'aux', 'content', 'contenu', 'creator', 'createur', 'creation',
        ];

        return collect($signals)
            ->filter(fn (mixed $signal): bool => is_string($signal))
            ->flatMap(function (string $signal): array {
                preg_match_all('/[a-z0-9]{3,}/', Str::lower(Str::ascii($signal)), $matches);

                return $matches[0] ?? [];
            })
            ->reject(fn (string $token): bool => in_array($token, $blocked, true))
            ->unique()
            ->values()
            ->all();
    }
}
