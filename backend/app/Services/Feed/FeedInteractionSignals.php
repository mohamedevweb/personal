<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\User;

/**
 * Turns explicit product behaviour into a small, bounded preference profile.
 * Saves and remixes are positive evidence. A dismissal can suppress one topic,
 * one creator or one language without rewriting the creator's Personal memory.
 */
class FeedInteractionSignals
{
    private const HISTORY_LIMIT = 60;

    public function __construct(private readonly ContentTopicClassifier $classifier) {}

    /**
     * @return array{
     *   positive_creators: array<int, int>,
     *   positive_clusters: array<string, int>,
     *   positive_tokens: array<string, int>,
     *   blocked_creators: list<int>,
     *   blocked_languages: list<string>,
     *   rejected_topics: list<array{clusters: list<string>, tokens: list<string>}>
     * }
     */
    public function forUser(User $user): array
    {
        $savedIds = $user->savedContent()->latest()->limit(self::HISTORY_LIMIT)->pluck('content_post_id');
        $remixedIds = $user->remixes()->latest()->limit(self::HISTORY_LIMIT)->pluck('source_content_id');
        $positiveWeights = [];

        foreach ($savedIds as $id) {
            $positiveWeights[(int) $id] = max(1, $positiveWeights[(int) $id] ?? 0);
        }
        foreach ($remixedIds as $id) {
            $positiveWeights[(int) $id] = max(2, $positiveWeights[(int) $id] ?? 0);
        }

        $positiveCreators = [];
        $positiveClusters = [];
        $positiveTokens = [];
        ContentPost::query()
            ->with('creator')
            ->whereIn('id', array_keys($positiveWeights))
            ->get()
            ->each(function (ContentPost $post) use (&$positiveCreators, &$positiveClusters, &$positiveTokens, $positiveWeights): void {
                $weight = $positiveWeights[$post->id];
                $positiveCreators[$post->creator_id] = ($positiveCreators[$post->creator_id] ?? 0) + $weight;
                $classification = $this->candidateClassification($post);

                foreach ($classification['clusters'] as $cluster) {
                    $positiveClusters[$cluster] = ($positiveClusters[$cluster] ?? 0) + $weight;
                }
                foreach ($classification['tokens'] as $token) {
                    $positiveTokens[$token] = ($positiveTokens[$token] ?? 0) + $weight;
                }
            });

        $blockedCreators = [];
        $blockedLanguages = [];
        $rejectedTopics = [];
        $user->dismissedContent()
            ->with('contentPost.creator')
            ->latest()
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->each(function ($dismissal) use (&$blockedCreators, &$blockedLanguages, &$rejectedTopics): void {
                $post = $dismissal->contentPost;

                if (! $post) {
                    return;
                }

                if ($dismissal->reason === 'creator') {
                    $blockedCreators[] = $post->creator_id;
                } elseif ($dismissal->reason === 'language') {
                    $language = $post->creator?->primary_language;

                    if ($language && $language !== 'unknown') {
                        $blockedLanguages[] = $language;
                    }
                } else {
                    $classification = $this->classifier->post($post);
                    $rejectedTopics[] = [
                        'clusters' => $classification['clusters'],
                        'tokens' => $classification['tokens'],
                    ];
                }
            });

        return [
            'positive_creators' => $positiveCreators,
            'positive_clusters' => $positiveClusters,
            'positive_tokens' => $positiveTokens,
            'blocked_creators' => array_values(array_unique($blockedCreators)),
            'blocked_languages' => array_values(array_unique($blockedLanguages)),
            'rejected_topics' => $rejectedTopics,
        ];
    }

    /** @param array<string, mixed> $signals */
    public function excludes(ContentPost $post, array $signals): bool
    {
        if (in_array($post->creator_id, $signals['blocked_creators'], true)
            || in_array($post->creator->primary_language, $signals['blocked_languages'], true)) {
            return true;
        }

        // A subject dismissal learns from the publication itself. Including the
        // creator bio here would quietly turn "not this topic" into "never show
        // this creator again", which is a separate explicit choice in the UI.
        $classification = $this->classifier->post($post);
        $candidate = [
            'clusters' => $classification['clusters'],
            'tokens' => $classification['tokens'],
        ];

        foreach ($signals['rejected_topics'] as $rejected) {
            $sharedTokens = count(array_intersect($candidate['tokens'], $rejected['tokens']));

            if ($sharedTokens >= 3
                || ($sharedTokens >= 2 && array_intersect($candidate['clusters'], $rejected['clusters']) !== [])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $signals */
    public function adjustment(ContentPost $post, array $signals): float
    {
        $classification = $this->candidateClassification($post);
        $creatorWeight = min(2, (int) ($signals['positive_creators'][$post->creator_id] ?? 0));
        $clusterWeight = collect($classification['clusters'])
            ->max(fn (string $cluster): int => min(2, (int) ($signals['positive_clusters'][$cluster] ?? 0))) ?? 0;
        $tokenMatches = collect($classification['tokens'])
            ->filter(fn (string $token): bool => ($signals['positive_tokens'][$token] ?? 0) > 0)
            ->count();

        return min(15.0, ($creatorWeight * 4.0) + ($clusterWeight * 2.0) + min(3, $tokenMatches));
    }

    /** @return array{clusters: list<string>, tokens: list<string>} */
    private function candidateClassification(ContentPost $post): array
    {
        $postClassification = $this->classifier->post($post);
        $creatorClassification = $this->classifier->creator($post->creator);

        return [
            'clusters' => array_values(array_unique([
                ...$postClassification['clusters'],
                ...$creatorClassification['clusters'],
            ])),
            'tokens' => array_values(array_unique([
                ...$postClassification['tokens'],
                ...$creatorClassification['tokens'],
            ])),
        ];
    }
}
