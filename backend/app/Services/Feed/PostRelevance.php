<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\CreatorProfile;

/**
 * Decides whether a post belongs in For You, Explore or nowhere. It is a gate,
 * not a ranking function: performance is considered only after this decision.
 */
class PostRelevance
{
    public const FOR_YOU = 'for_you';

    public const EXPLORE = 'explore';

    public function __construct(
        private readonly CreatorAffinity $affinity,
        private readonly ContentTopicClassifier $classifier,
    ) {}

    /** @return array<string, mixed> */
    public function assess(?CreatorProfile $profile, ContentPost $post, bool $allowBroaderMatch = false): array
    {
        if (! $profile) {
            return $this->verdict(self::FOR_YOU, 1.0, null, null, [], [], []);
        }

        $profileClassification = $this->classifier->profile($profile);
        $postClassification = $this->classifier->post($post);
        $creatorClassification = $this->classifier->creator($post->creator);
        $creatorAffinity = $this->affinity->score($profile, $post->creator);
        $profileHasContext = $profileClassification['primary_niche'] !== null
            || $profileClassification['sub_niches'] !== []
            || $profileClassification['topics'] !== [];

        // Before DNA exists there is no personal gate to apply. Keep the
        // existing catalogue experience for that state; once any subject is
        // known, every post must prove its own relevance.
        if (! $profileHasContext) {
            if ($profileClassification['vertical'] === null) {
                return $this->verdict(self::FOR_YOU, 1.0, null, $postClassification['vertical'], [], [], [], $creatorAffinity, $profileClassification, $postClassification);
            }

            $candidateVertical = $postClassification['vertical'] ?? $creatorClassification['vertical'];

            return $this->verdict(
                $candidateVertical === $profileClassification['vertical'] ? self::FOR_YOU : null,
                $candidateVertical === $profileClassification['vertical'] ? 0.5 : 0.0,
                $profileClassification['vertical'],
                $postClassification['vertical'],
                [],
                [],
                [],
                $creatorAffinity,
                $profileClassification,
                $postClassification,
            );
        }

        $profileNiches = $this->nicheConcepts($profileClassification);
        $postNiches = $this->nicheConcepts($postClassification);
        $sharedNiches = array_values(array_intersect($profileNiches, $postNiches));
        $sharedTopics = array_values(array_intersect(
            $profileClassification['topics'],
            $postClassification['topics'],
        ));
        $matchedAvoidTopics = array_values(array_intersect(
            $profileClassification['avoid_topics'],
            array_values(array_unique([...$postNiches, ...$postClassification['topics']])),
        ));
        $primary = $profileClassification['vertical'];
        $contentVertical = $postClassification['vertical'];
        $candidateVertical = $contentVertical ?? $creatorClassification['vertical'];
        $samePrimaryNiche = $profileClassification['primary_niche'] !== null
            && $profileClassification['primary_niche'] === $postClassification['primary_niche'];
        $hasPostEvidence = $postNiches !== [] || $postClassification['topics'] !== [];

        if ($matchedAvoidTopics !== []) {
            return $this->verdict(
                null,
                0.0,
                $primary,
                $contentVertical,
                $sharedNiches,
                $sharedTopics,
                $matchedAvoidTopics,
                $creatorAffinity,
                $profileClassification,
                $postClassification,
            );
        }

        // With an identified profile, an unreadable post has no subject proof in
        // the strict pass. The broader fallback may use the creator's canonical
        // vertical, but never raw creator labels or affinity alone.
        if (! $hasPostEvidence) {
            if ($allowBroaderMatch && $primary !== null && $candidateVertical === $primary) {
                return $this->verdict(
                    self::FOR_YOU,
                    0.4,
                    $primary,
                    $contentVertical,
                    $sharedNiches,
                    $sharedTopics,
                    $matchedAvoidTopics,
                    $creatorAffinity,
                    $profileClassification,
                    $postClassification,
                );
            }

            if ($allowBroaderMatch && $primary !== null && $this->adjacent($primary, $candidateVertical)) {
                return $this->verdict(
                    self::EXPLORE,
                    0.25,
                    $primary,
                    $contentVertical,
                    $sharedNiches,
                    $sharedTopics,
                    $matchedAvoidTopics,
                    $creatorAffinity,
                    $profileClassification,
                    $postClassification,
                );
            }

            return $this->verdict(
                null,
                0.0,
                $primary,
                $contentVertical,
                $sharedNiches,
                $sharedTopics,
                $matchedAvoidTopics,
                $creatorAffinity,
                $profileClassification,
                $postClassification,
            );
        }

        // One strong niche match is enough. The profile does not need to match
        // every topic in its DNA, because a post normally has one main subject.
        $postScore = $samePrimaryNiche
            ? 1.0
            : (count($sharedNiches) >= 1
                ? 0.85
                : (count($sharedTopics) >= 2 ? 0.75 : (count($sharedTopics) === 1 ? 0.65 : 0.0)));

        if ($postScore <= 0.0) {
            if ($allowBroaderMatch && $primary !== null && $this->adjacent($primary, $candidateVertical)) {
                return $this->verdict(
                    self::EXPLORE,
                    0.2,
                    $primary,
                    $contentVertical,
                    $sharedNiches,
                    $sharedTopics,
                    $matchedAvoidTopics,
                    $creatorAffinity,
                    $profileClassification,
                    $postClassification,
                );
            }

            return $this->verdict(
                null,
                0.0,
                $primary,
                $contentVertical,
                $sharedNiches,
                $sharedTopics,
                $matchedAvoidTopics,
                $creatorAffinity,
                $profileClassification,
                $postClassification,
            );
        }

        if ($primary === null || $contentVertical === null || $contentVertical === $primary) {
            return $this->verdict(
                self::FOR_YOU,
                $postScore,
                $primary,
                $contentVertical,
                $sharedNiches,
                $sharedTopics,
                $matchedAvoidTopics,
                $creatorAffinity,
                $profileClassification,
                $postClassification,
            );
        }

        return $this->verdict(
            $this->adjacent($primary, $contentVertical) ? self::EXPLORE : null,
            $postScore,
            $primary,
            $contentVertical,
            $sharedNiches,
            $sharedTopics,
            $matchedAvoidTopics,
            $creatorAffinity,
            $profileClassification,
            $postClassification,
        );
    }

    /** @param array<string, mixed> $classification @return list<string> */
    private function nicheConcepts(array $classification): array
    {
        return array_values(array_unique([
            ...($classification['primary_niche'] ? [$classification['primary_niche']] : []),
            ...$classification['sub_niches'],
        ]));
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $post @return array<string, mixed> */
    private function verdict(
        ?string $bucket,
        float $postRelevance,
        ?string $primary,
        ?string $contentVertical,
        array $sharedNiches,
        array $sharedTopics,
        array $matchedAvoidTopics,
        ?float $creatorAffinity = null,
        array $profile = [],
        array $post = [],
    ): array {
        return [
            'bucket' => $bucket,
            'affinity' => $creatorAffinity,
            'creator_affinity' => $creatorAffinity,
            'post_relevance' => round($postRelevance, 4),
            'primary_vertical' => $primary,
            'content_vertical' => $contentVertical,
            'profile_primary_niche' => $profile['primary_niche'] ?? null,
            'profile_sub_niches' => $profile['sub_niches'] ?? [],
            'post_primary_niche' => $post['primary_niche'] ?? null,
            'post_sub_niches' => $post['sub_niches'] ?? [],
            'shared_niches' => $sharedNiches,
            'shared_topics' => $sharedTopics,
            'matched_avoid_topics' => $matchedAvoidTopics,
        ];
    }

    private function adjacent(string $primary, ?string $candidate): bool
    {
        return $candidate !== null && in_array(
            $candidate,
            (array) config("creator_catalog.adjacent_verticals.{$primary}"),
            true,
        );
    }
}
