<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;

/**
 * Measures how closely a benchmark creator matches the member's Creator DNA.
 * Performance still decides whether a post is useful; this score decides which
 * useful posts belong in this particular creator's feed.
 */
class CreatorAffinity
{
    private const VERTICAL_WEIGHT = 0.35;

    private const CLUSTER_WEIGHT = 0.35;

    private const TOPIC_WEIGHT = 0.30;

    public function __construct(private readonly ContentTopicClassifier $classifier) {}

    public function score(?CreatorProfile $profile, Creator $creator, ?ContentPost $post = null): ?float
    {
        if (! $profile) {
            return null;
        }

        $profileClassification = $this->classifier->profile($profile);
        $creatorClassification = $this->classifier->creator($creator);
        $postClassification = $post ? $this->classifier->post($post) : ['clusters' => [], 'tokens' => []];
        $profileVertical = $profileClassification['vertical'];
        $creatorVertical = $creatorClassification['vertical'];
        $profileTokens = $profileClassification['tokens'];

        if ($profileVertical === null && $profileTokens === []) {
            return null;
        }

        $vertical = $profileVertical !== null && $profileVertical === $creatorVertical
            ? self::VERTICAL_WEIGHT
            : 0.0;
        $candidateClusters = array_values(array_unique([
            ...$creatorClassification['clusters'],
            ...$postClassification['clusters'],
        ]));
        $clusterCoverage = $profileClassification['clusters'] === []
            ? 0.0
            : count(array_intersect($profileClassification['clusters'], $candidateClusters))
                / count($profileClassification['clusters']);
        $candidateTokens = array_values(array_unique([
            ...$creatorClassification['tokens'],
            ...$postClassification['tokens'],
        ]));
        $coverage = $profileTokens === [] || $candidateTokens === []
            ? 0.0
            : count(array_intersect($profileTokens, $candidateTokens))
                / min(count($profileTokens), count($candidateTokens));

        return round(min(1.0,
            $vertical
            + (self::CLUSTER_WEIGHT * $clusterCoverage)
            + (self::TOPIC_WEIGHT * sqrt($coverage)),
        ), 4);
    }
}
