<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\CreatorProfile;

/**
 * Decides which section a post belongs to, never how good it is.
 *
 * The vertical is the broad recall layer; semantic clusters decide whether the
 * post is actually in the creator's universe. Performance has already answered
 * "is this worth reading" upstream, and affinity answers "in what order" in the
 * ranking. Adjacent subjects stay stricter, because a neighbouring vertical is a
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
        $sharedTokens = array_intersect(
            $profileClassification['tokens'],
            array_unique([...$postClassification['tokens'], ...$creatorClassification['tokens']]),
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
                $sharedClusters !== []
                    || count(array_filter($sharedTokens, fn (string $token): bool => strlen($token) >= 6)) >= 1
                    || $affinity >= $exploreMinimum
                    ? self::FOR_YOU
                    : null,
                $affinity,
                null,
                $contentVertical,
            );
        }

        $candidateClusters = array_values(array_unique([
            ...$postClassification['clusters'],
            ...$creatorClassification['clusters'],
        ]));

        // A broad vertical is only the recall layer. Once a profile has a
        // precise subject, a post from the same broad vertical still needs a
        // shared semantic cluster. Without this second gate, consumer-tech
        // reviews and SaaS founder content both look like `tech-ai` and the
        // strongest unrelated post wins on performance.
        if ($contentVertical === $primary) {
            if ($profileClassification['clusters'] !== []
                && ($candidateClusters === [] || $sharedClusters === [])) {
                return $this->verdict(null, $affinity, $primary, $contentVertical);
            }

            return $this->verdict(self::FOR_YOU, $affinity, $primary, $contentVertical);
        }

        // The post itself could not be read, but the account publishing it lives
        // in the member's universe. A precise profile still requires the account
        // to share a cluster before its unreadable post can be trusted.
        if ($contentVertical === null && $creatorVertical === $primary) {
            if ($profileClassification['clusters'] !== []
                && ($creatorClassification['clusters'] === [] || $sharedClusters === [])) {
                return $this->verdict(null, $affinity, $primary, $contentVertical);
            }

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

        // A neighbouring subject is optional discovery, not a relevance escape
        // hatch. It must share an explicit cluster with the profile. A generic
        // token overlap such as `tech` is not enough to surface a gadget account
        // next to a startup profile.
        $candidateVertical = $contentVertical ?? $creatorVertical;

        if ($candidateVertical !== null
            && $this->adjacent($primary, $candidateVertical)
            && $sharedClusters !== []) {
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
