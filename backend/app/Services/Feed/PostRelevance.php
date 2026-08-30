<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\CreatorProfile;

/**
 * Decides which section a post belongs to, never how good it is.
 *
 * The vertical answers "is this the creator's universe"; performance has already
 * answered "is this worth reading" upstream; affinity answers "in what order",
 * in the ranking. A post from the member's own vertical therefore enters For You
 * on the strength of that vertical alone — an affinity score measures how much
 * of a profile a single post covers, which is a ranking signal, not a right of
 * entry. Adjacent subjects stay stricter, because a neighbouring vertical is a
 * weaker claim and Explore is a smaller shelf.
 */
class PostRelevance
{
    public const FOR_YOU = 'for_you';

    public const EXPLORE = 'explore';

    public function __construct(
        private readonly CreatorAffinity $affinity,
        private readonly ContentTopicClassifier $classifier,
    ) {}

    /** @return array{bucket: ?string, affinity: ?float, primary_vertical: ?string, content_vertical: ?string} */
    public function assess(?CreatorProfile $profile, ContentPost $post): array
    {
        if (! $profile) {
            return $this->verdict(self::FOR_YOU, null, null, null);
        }

        $profileClassification = $this->classifier->profile($profile);
        $postClassification = $this->classifier->post($post);
        $creatorClassification = $this->classifier->creator($post->creator);
        $primary = $profileClassification['vertical'];
        $contentVertical = $postClassification['vertical'];
        $creatorVertical = $creatorClassification['vertical'];
        $affinity = $this->affinity->score($profile, $post->creator, $post);

        $sharedClusters = array_intersect(
            $profileClassification['clusters'],
            array_unique([...$postClassification['clusters'], ...$creatorClassification['clusters']]),
        );
        $exploreMinimum = max(0.0, (float) config('services.discovery.personalization.explore_minimum_affinity'));

        // No vertical to place the post against. An empty profile has nothing to
        // filter with and gets the best of the pool; a profile that does say
        // what it is about is judged on its subjects instead, so an
        // unclassifiable niche never turns into a feed of every vertical.
        if ($primary === null) {
            $comparable = $profileClassification['clusters'] !== []
                || $profileClassification['tokens'] !== [];

            if (! $comparable || $affinity === null) {
                return $this->verdict(self::FOR_YOU, $affinity, null, $contentVertical);
            }

            return $this->verdict(
                $sharedClusters !== [] || $affinity >= $exploreMinimum ? self::FOR_YOU : null,
                $affinity,
                null,
                $contentVertical,
            );
        }

        // The post says on its own what it is about, and it is this creator's
        // subject. That is the strongest evidence there is, and it is enough.
        if ($contentVertical === $primary) {
            return $this->verdict(self::FOR_YOU, $affinity, $primary, $contentVertical);
        }

        // The post itself could not be read, but the account publishing it lives
        // in the member's universe. Related enough and it belongs in For You;
        // otherwise it drops to Explore — never out of the feed, since the only
        // thing against it is that a caption was unreadable.
        if ($contentVertical === null && $creatorVertical === $primary) {
            $minimum = max(0.0, (float) config('services.discovery.personalization.minimum_affinity'));
            // A profile with almost no subject of its own cannot produce a
            // meaningful affinity, so there is nothing to judge the post with.
            $comparable = $profileClassification['clusters'] !== []
                || count($profileClassification['tokens']) >= 3;

            return $this->verdict(
                ! $comparable || $affinity === null || $affinity >= $minimum ? self::FOR_YOU : self::EXPLORE,
                $affinity,
                $primary,
                $contentVertical,
            );
        }

        // A neighbouring subject: useful, but it has to earn its place with a
        // shared cluster or real affinity.
        $candidateVertical = $contentVertical ?? $creatorVertical;

        if ($candidateVertical !== null
            && $this->adjacent($primary, $candidateVertical)
            && ($sharedClusters !== [] || ($affinity ?? 0.0) >= $exploreMinimum)) {
            return $this->verdict(self::EXPLORE, $affinity, $primary, $contentVertical);
        }

        return $this->verdict(null, $affinity, $primary, $contentVertical);
    }

    /** @return array{bucket: ?string, affinity: ?float, primary_vertical: ?string, content_vertical: ?string} */
    private function verdict(?string $bucket, ?float $affinity, ?string $primary, ?string $contentVertical): array
    {
        return [
            'bucket' => $bucket,
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
