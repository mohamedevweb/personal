<?php

namespace App\Services\Creator;

use App\Models\Creator;
use App\Models\CreatorProfile;

/**
 * Keeps the public-handle and OAuth imports on the same Creator DNA contract.
 * A memory the creator edited themselves always wins over inferred values.
 */
class CreatorProfileDnaWriter
{
    /** @param array<string, mixed> $signals */
    public function apply(
        CreatorProfile $profile,
        array $signals,
        ?string $primaryVertical,
        ?Creator $existingCreator = null,
    ): void {
        if (data_get($profile->creator_dna, 'analysis_method') === 'manual') {
            return;
        }

        $signals['primary_niche'] = filled($signals['primary_niche'] ?? null)
            ? $signals['primary_niche']
            : $existingCreator?->niche;
        $signals['topics'] = ! empty($signals['topics'] ?? [])
            ? $signals['topics']
            : ($existingCreator?->niche_topics ?? []);
        $signals['primary_vertical'] = $primaryVertical;

        $profile->fill([
            'niche' => $signals['primary_niche'] ?? null,
            'positioning' => $signals['positioning'] ?? null,
            'topics' => $signals['topics'] ?? [],
            'tone' => $signals['tone'] ?? [],
            'voice_profile' => $signals['voice_profile'] ?? null,
            'audience_description' => ($signals['audience'] ?? []) === []
                ? null
                : implode(', ', $signals['audience']),
            'current_projects' => $signals['current_projects'] ?? [],
            'goals' => $signals['goals'] ?? [],
            'content_strengths' => $signals['content_strengths'] ?? [],
            'creator_dna' => [
                ...$signals,
                'primary_vertical' => $primaryVertical,
            ],
            'primary_vertical' => $primaryVertical,
            'dna_analyzed_at' => now(),
            'discovery_queries' => null,
            'discovery_hashtags' => null,
            'discovery_refreshed_at' => null,
        ]);
    }
}
