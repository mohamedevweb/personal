<?php

namespace App\Services\Feed;

use App\Models\Creator;
use App\Models\CreatorProfile;

/**
 * Measures the creator-level fit only. Post relevance is intentionally not part
 * of this score, so a generally relevant creator cannot rescue an off-topic post.
 */
class CreatorAffinity
{
    public function __construct(private readonly ContentTopicClassifier $classifier) {}

    /**
     * @return float|null A bounded score where niche and sub-niche evidence
     *                    outweighs the broad vertical.
     */
    public function score(?CreatorProfile $profile, Creator $creator): ?float
    {
        if (! $profile) {
            return null;
        }

        $profileClassification = $this->classifier->profile($profile);
        $creatorClassification = $this->classifier->creator($creator);

        if ($profileClassification['vertical'] === null
            && $profileClassification['primary_niche'] === null
            && $profileClassification['sub_niches'] === []
            && $profileClassification['topics'] === []) {
            return null;
        }

        $score = $profileClassification['vertical'] !== null
            && $profileClassification['vertical'] === $creatorClassification['vertical']
            ? 0.10
            : 0.0;

        if ($profileClassification['primary_niche'] !== null
            && $profileClassification['primary_niche'] === $creatorClassification['primary_niche']) {
            $score += 0.30;
        }

        $sharedSubNiches = array_intersect(
            $profileClassification['sub_niches'],
            $creatorClassification['sub_niches'],
        );
        $score += match (count($sharedSubNiches)) {
            0 => 0.0,
            1 => 0.20,
            default => 0.30,
        };

        $sharedTopics = array_intersect(
            $profileClassification['topics'],
            $creatorClassification['topics'],
        );
        $score += min(0.10, count($sharedTopics) * 0.05);

        return round(min(1.0, $score), 4);
    }
}
