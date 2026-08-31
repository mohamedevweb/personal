<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Services\Discovery\CanonicalCreatorVerticals;
use Illuminate\Support\Str;

/**
 * Turns stored profile, creator and post evidence into the small taxonomy used
 * by the feed. It never calls a model. A future ingestion pass can provide a
 * `metadata.feed_classification` payload, which is preferred over heuristics.
 */
class ContentTopicClassifier
{
    private const GENERIC_TOPICS = [
        'business', 'content', 'creator', 'creation', 'growth', 'marketing',
        'motivation', 'productivity', 'success', 'entrepreneur', 'entrepreneurship',
    ];

    public function __construct(private readonly CanonicalCreatorVerticals $verticals) {}

    /** @return array{vertical: ?string, primary_niche: ?string, sub_niches: list<string>, topics: list<string>, avoid_topics: list<string>, clusters: list<string>, tokens: list<string>} */
    public function post(ContentPost $post): array
    {
        $stored = data_get($post->metadata, 'feed_classification');

        if (is_array($stored)) {
            return $this->structured($stored, $this->postSignals($post));
        }

        $signals = $this->postSignals($post);
        $classification = $this->structured([], $signals);

        // Hashtags and imported tags are explicit post subjects. They are a
        // safe fallback for subjects outside the local taxonomy, unlike
        // arbitrary caption words which would create noisy labels.
        if ($classification['primary_niche'] === null) {
            $tagLabels = $this->fallbackLabels($post->tags ?? [], allowSingleWord: true);

            if ($tagLabels !== []) {
                $classification['primary_niche'] = $tagLabels[0];
                $classification['topics'] = array_values(array_unique([
                    ...$classification['topics'],
                    $tagLabels[0],
                ]));
                $classification['clusters'] = array_values(array_unique([
                    ...$classification['clusters'],
                    $tagLabels[0],
                ]));
            }
        }

        return $classification;
    }

    /** @return array{vertical: ?string, primary_niche: ?string, sub_niches: list<string>, topics: list<string>, avoid_topics: list<string>, clusters: list<string>, tokens: list<string>} */
    public function creator(Creator $creator): array
    {
        $stored = data_get($creator->metadata, 'feed_classification');
        $data = is_array($stored) ? $stored : [
            'vertical' => $creator->primary_vertical,
            'primary_niche' => $creator->niche,
            'sub_niches' => $creator->niche_topics ?? [],
            'topics' => $creator->niche_topics ?? [],
        ];

        $classification = $this->structured($data, [
            $creator->username,
            $creator->niche,
            ...($creator->niche_topics ?? []),
            $creator->bio,
        ]);

        $classification['vertical'] = $this->verticals->canonical($creator->primary_vertical)
            ?? $classification['vertical'];

        return $classification;
    }

    /** @return array{vertical: ?string, primary_niche: ?string, sub_niches: list<string>, topics: list<string>, avoid_topics: list<string>, clusters: list<string>, tokens: list<string>} */
    public function profile(CreatorProfile $profile): array
    {
        $dna = is_array($profile->creator_dna) ? $profile->creator_dna : [];
        $data = [
            'vertical' => $profile->primary_vertical,
            'primary_niche' => $dna['primary_niche'] ?? $profile->niche,
            'sub_niches' => $dna['sub_niches'] ?? [],
            'topics' => [
                ...($dna['topics'] ?? $profile->topics ?? []),
                ...($dna['content_pillars'] ?? []),
            ],
            'avoid_topics' => $dna['avoid_topics'] ?? [],
        ];

        return $this->structured($data, [
            $data['primary_niche'],
            ...$data['sub_niches'],
            ...$data['topics'],
            $dna['audience'] ?? [],
            $profile->display_name,
            $profile->bio,
            $profile->positioning,
            $profile->audience_description,
        ]);
    }

    /** @return list<string|null> */
    public function postSignals(ContentPost $post): array
    {
        return [
            $post->hook,
            $post->caption,
            ...($post->tags ?? []),
            $post->transcript,
            ...$this->carouselText($post->carousel_analysis),
        ];
    }

    /** @param list<mixed> $signals */
    public function classify(array $signals): array
    {
        return $this->structured([], $signals);
    }

    /** @param array<string, mixed> $data @param list<mixed> $fallbackSignals */
    private function structured(array $data, array $fallbackSignals): array
    {
        $vertical = $this->verticals->canonical(is_string($data['vertical'] ?? null) ? $data['vertical'] : null)
            ?? $this->verticals->fromSignals($fallbackSignals);
        $fallbackConcepts = $this->conceptsFromText($fallbackSignals);
        $primaryValues = $this->values($data['primary_niche'] ?? null);
        $primaryConcepts = $this->conceptsForValues($primaryValues);
        $subNiches = $this->conceptsForValues($this->values($data['sub_niches'] ?? []));
        $topics = $this->conceptsForValues($this->values($data['topics'] ?? []));
        $avoidTopics = $this->conceptsForValues($this->values($data['avoid_topics'] ?? []));

        if ($primaryConcepts === [] && $primaryValues !== []) {
            $primaryConcepts = $this->fallbackLabels($primaryValues, allowSingleWord: true);
        }

        if ($primaryConcepts === [] && $data === []) {
            $primaryConcepts = array_slice($fallbackConcepts, 0, 1);
        }

        // Unstructured post text has no explicit fields. The first known
        // concept is its narrowest available subject. Without an ingestion-time
        // structured label, avoid guessing several coexisting sub-niches.
        if ($data === []) {
            $subNiches = [];
            // A post usually has one subject. Do not let a broad word such as
            // "recipe" make a specific baking post look like every food topic.
            $topics = array_values(array_unique([...$topics, ...$primaryConcepts]));
        }

        $primaryNiche = $primaryConcepts[0] ?? ($primaryValues[0] ?? null);
        $nicheConcepts = array_values(array_unique([...$primaryConcepts, ...$subNiches]));
        $clusters = array_values(array_unique([...$nicheConcepts, ...$topics]));

        return [
            'vertical' => $vertical,
            'primary_niche' => $primaryNiche,
            'sub_niches' => $subNiches,
            'topics' => $topics,
            'avoid_topics' => $avoidTopics,
            // Kept for interaction history and older callers. The feed gate
            // uses the structured fields above, not token overlap.
            'clusters' => $clusters,
            'tokens' => $this->tokens($this->text($fallbackSignals)),
        ];
    }

    /** @param list<mixed> $values @return list<string> */
    private function conceptsForValues(array $values): array
    {
        $concepts = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $concepts = [...$concepts, ...$this->conceptsFromText([$value])];
        }

        $known = array_values(array_unique($concepts));
        $labels = $this->fallbackLabels($values);

        return array_values(array_unique([...$known, ...$labels]));
    }

    /** @param list<mixed> $values @return list<string> */
    private function fallbackLabels(array $values, bool $allowSingleWord = false): array
    {
        return collect($values)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => Str::slug(Str::ascii(trim($value))))
            ->filter(function (string $value) use ($allowSingleWord): bool {
                $parts = array_values(array_filter(explode('-', $value)));

                return ($allowSingleWord || count($parts) > 1 || strlen($value) >= 8)
                    && ! in_array($value, self::GENERIC_TOPICS, true);
            })
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<mixed> $signals @return list<string> */
    private function conceptsFromText(array $signals): array
    {
        $text = $this->text($signals);
        $concepts = [];

        foreach ((array) config('creator_catalog.semantic_clusters') as $concept => $aliases) {
            $bestMatchLength = 0;
            foreach ($aliases as $alias) {
                $quoted = preg_quote(Str::lower(Str::ascii((string) $alias)), '/');

                if (preg_match('/(?<![a-z0-9])'.$quoted.'(?![a-z0-9])/', $text) === 1) {
                    $bestMatchLength = max($bestMatchLength, strlen((string) $alias));
                }
            }

            if ($bestMatchLength > 0) {
                $concepts[$concept] = $bestMatchLength;
            }
        }

        arsort($concepts);

        return array_keys($concepts);
    }

    /** @param list<mixed> $signals */
    private function text(array $signals): string
    {
        return Str::lower(Str::ascii(collect($signals)
            ->flatten()
            ->filter(fn (mixed $signal): bool => is_string($signal) && trim($signal) !== '')
            ->implode(' ')));
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/', $text, $matches);

        $blocked = [
            'avec', 'dans', 'des', 'for', 'from', 'les', 'pour', 'the', 'une',
            'and', 'aux', 'content', 'contenu', 'creator', 'createur', 'creation',
            'this', 'that', 'your', 'vous', 'notre', 'leur', 'elle', 'elles', 'ils',
        ];

        return collect($matches[0] ?? [])
            ->reject(fn (string $token): bool => in_array($token, $blocked, true))
            ->unique()
            ->take(120)
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function carouselText(mixed $analysis): array
    {
        if (! is_array($analysis)) {
            return [];
        }

        return collect($analysis)
            ->flatMap(fn (mixed $value): array => is_string($value)
                ? [$value]
                : (is_array($value) ? $this->carouselText($value) : []))
            ->take(80)
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function values(mixed $value): array
    {
        return is_array($value) ? array_values($value) : (is_string($value) && trim($value) !== '' ? [$value] : []);
    }
}
