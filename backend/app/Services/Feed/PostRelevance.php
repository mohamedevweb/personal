<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\CreatorProfile;
use App\Services\Discovery\CanonicalCreatorVerticals;

class PostRelevance
{
    public const FOR_YOU = 'for_you';

    public const EXPLORE = 'explore';

    public function __construct(
        private readonly CreatorAffinity $affinity,
        private readonly ContentTopicClassifier $classifier,
        private readonly CanonicalCreatorVerticals $verticals,
    ) {}

    /** @return array{bucket: ?string, affinity: ?float, primary_vertical: ?string, content_vertical: ?string} */
    public function assess(?CreatorProfile $profile, ContentPost $post): array
    {
        if (! $profile) {
            return [
                'bucket' => self::FOR_YOU,
                'affinity' => null,
                'primary_vertical' => null,
                'content_vertical' => null,
            ];
        }

        $profileClassification = $this->classifier->profile($profile);
        $postClassification = $this->classifier->post($post);
        $creatorClassification = $this->classifier->creator($post->creator);
        $primary = $profileClassification['vertical'];
        $contentVertical = $postClassification['vertical'];
        $creatorVertical = $this->verticals->canonical($post->creator->niche)
            ?? $creatorClassification['vertical'];
        $affinity = $this->affinity->score($profile, $post->creator, $post);

        if ($primary === null) {
            return [
                'bucket' => self::FOR_YOU,
                'affinity' => $affinity,
                'primary_vertical' => null,
                'content_vertical' => $contentVertical,
            ];
        }

        $minimum = max(0.0, (float) config('services.discovery.personalization.minimum_affinity'));
        $exploreMinimum = max(0.0, (float) config('services.discovery.personalization.explore_minimum_affinity'));
        $hasDetailedProfile = $profileClassification['clusters'] !== []
            || count($profileClassification['tokens']) >= 3;
        $sameVertical = $contentVertical === $primary
            || ($contentVertical === null && $creatorVertical === $primary);
        $explicitlyOffTopic = $contentVertical !== null
            && $contentVertical !== $primary
            && ! $this->adjacent($primary, $contentVertical);

        if (! $explicitlyOffTopic && $sameVertical
            && (! $hasDetailedProfile || ($affinity ?? 0.0) >= $minimum)) {
            return [
                'bucket' => self::FOR_YOU,
                'affinity' => $affinity,
                'primary_vertical' => $primary,
                'content_vertical' => $contentVertical,
            ];
        }

        $candidateVertical = $contentVertical ?? $creatorVertical;
        $sharedClusters = array_intersect(
            $profileClassification['clusters'],
            array_unique([...$postClassification['clusters'], ...$creatorClassification['clusters']]),
        );

        if ($candidateVertical !== null
            && $this->adjacent($primary, $candidateVertical)
            && ($sharedClusters !== [] || ($affinity ?? 0.0) >= $exploreMinimum)) {
            return [
                'bucket' => self::EXPLORE,
                'affinity' => $affinity,
                'primary_vertical' => $primary,
                'content_vertical' => $contentVertical,
            ];
        }

        return [
            'bucket' => null,
            'affinity' => $affinity,
            'primary_vertical' => $primary,
            'content_vertical' => $contentVertical,
        ];
    }

    private function adjacent(string $primary, string $candidate): bool
    {
        return in_array(
            $candidate,
            (array) config("creator_catalog.adjacent_verticals.{$primary}"),
            true,
        );
    }
}
