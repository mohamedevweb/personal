<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

class CreatorCandidateFinder
{
    /**
     * @param  list<array<string, mixed>>  $seeds
     * @param  Collection<string, int>  $knownHandles  lowercase handles to skip
     * @return list<array{row: array<string, mixed>, profile: DiscoveredProfile}>
     */
    public function find(
        InstagramDataProvider $provider,
        CreatorCatalogEligibility $eligibility,
        array $seeds,
        Collection $knownHandles,
        int $perSeed,
        int $max,
    ): array {
        $candidates = [];

        foreach ($seeds as $seed) {
            $profile = $provider->getProfile((string) $seed['handle']);
            if (! $profile?->externalId) {
                continue;
            }

            foreach ($provider->getRelatedAccounts($profile->externalId, $perSeed, $profile->username) as $candidate) {
                $handle = strtolower(ltrim($candidate->username, '@'));
                if ($knownHandles->has($handle) || isset($candidates[$handle])) {
                    continue;
                }

                $full = $this->withPosts($provider->getProfile($handle) ?? $candidate, $provider);
                $row = $eligibility->evaluate($full, $seed);
                $row['handle'] = $handle;
                $row['source_seed'] = $seed['handle'];
                $row['status'] = 'candidate';
                $row['candidate_score'] = $this->candidateScore($row);
                $candidates[$handle] = ['row' => $row, 'profile' => $full];

                if (count($candidates) >= $max) {
                    break 2;
                }
            }
        }

        return collect($candidates)->sortByDesc(fn (array $pair): float => $pair['row']['candidate_score'])->values()->all();
    }

    private function withPosts(DiscoveredProfile $profile, InstagramDataProvider $provider): DiscoveredProfile
    {
        if ($profile->isPrivate) {
            return $profile;
        }

        $posts = $profile->posts
            ->concat($provider->getPosts($profile->username, 30, $profile->externalId))
            ->unique(fn ($post): string => $post->externalId ?: $post->sourceUrl)
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

    private function candidateScore(array $row): float
    {
        $activity = min(1, ((int) ($row['recent_posts'] ?? 0)) / 12);
        $metrics = (float) ($row['metric_coverage'] ?? 0);
        $engagement = min(1, log10(max(1, (int) ($row['median_engagement'] ?? 0))) / 5);

        return round(($activity * 0.35) + ($metrics * 0.25) + ($engagement * 0.30) + 0.10, 4);
    }
}
