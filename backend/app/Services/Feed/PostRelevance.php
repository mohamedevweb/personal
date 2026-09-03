<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\CreatorProfile;

/**
 * Decides whether a post belongs in For You, Explore or nowhere. During the
 * temporary vertical-only rollout, it gates on the canonical primary vertical
 * and leaves niche and topic signals available for ranking and diagnostics.
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
        // The publishing creator's canonical vertical is authoritative. A post
        // can carry an old or campaign-specific content classification, but it
        // must not move a travel or lifestyle creator into a Local Culture & Events For You shelf.
        $candidateVertical = $creatorClassification['vertical'];

        // Temporary rollout rule: the canonical primary vertical is the only
        // relevance gate. DNA niches, topics and avoid topics remain available
        // for debugging and future ranking, but cannot block a same-vertical post.
        if ($primary === null) {
            return $this->verdict(
                self::FOR_YOU,
                1.0,
                null,
                $contentVertical,
                $sharedNiches,
                $sharedTopics,
                $matchedAvoidTopics,
                $creatorAffinity,
                $profileClassification,
                $postClassification,
            );
        }

        if ($candidateVertical === $primary) {
            return $this->verdict(
                self::FOR_YOU,
                1.0,
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

        if ($this->adjacent($primary, $candidateVertical)) {
            return $this->verdict(
                self::EXPLORE,
                0.5,
                $primary,
                $contentVertical,
                $sharedNiches,
                $sharedTopics,
                [],
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
