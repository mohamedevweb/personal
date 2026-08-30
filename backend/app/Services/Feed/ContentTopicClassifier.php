<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Services\Discovery\CanonicalCreatorVerticals;
use Illuminate\Support\Str;

/**
 * Reads the subject of a publication from all evidence already stored locally.
 * It is deliberately deterministic and cheap enough for feed ranking: richer
 * transcript and carousel readings refine the caption without adding a model
 * call to a request.
 */
class ContentTopicClassifier
{
    public function __construct(private readonly CanonicalCreatorVerticals $verticals) {}

    /** @return array{vertical: ?string, clusters: list<string>, tokens: list<string>} */
    public function post(ContentPost $post): array
    {
        $signals = $this->postSignals($post);

        return $this->classify($signals);
    }

    /** @return array{vertical: ?string, clusters: list<string>, tokens: list<string>} */
    public function creator(Creator $creator): array
    {
        $classification = $this->classify([
            $creator->niche,
            ...($creator->niche_topics ?? []),
            $creator->bio,
        ]);

        // The stored vertical is the same derivation, written once when the
        // creator was analysed. Preferring it keeps every read — the feed query,
        // the ranking, this classification — on one value.
        $classification['vertical'] = $this->verticals->canonical($creator->primary_vertical)
            ?? $classification['vertical'];

        return $classification;
    }

    /** @return array{vertical: ?string, clusters: list<string>, tokens: list<string>} */
    public function profile(CreatorProfile $profile): array
    {
        $dna = $profile->creator_dna ?? [];
        $signals = [
            $dna['primary_niche'] ?? $profile->niche,
            ...($dna['sub_niches'] ?? []),
            ...($dna['topics'] ?? $profile->topics ?? []),
            ...($dna['content_pillars'] ?? []),
            ...($dna['audience'] ?? []),
            $profile->positioning,
            $profile->audience_description,
        ];

        $classification = $this->classify($signals);
        $classification['vertical'] ??= $this->verticals->canonical($profile->primary_vertical);

        return $classification;
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

    /** @param list<mixed> $signals @return array{vertical: ?string, clusters: list<string>, tokens: list<string>} */
    public function classify(array $signals): array
    {
        $text = Str::lower(Str::ascii(collect($signals)
            ->filter(fn (mixed $signal): bool => is_string($signal) && trim($signal) !== '')
            ->implode(' ')));

        return [
            'vertical' => $this->verticals->fromSignals($signals),
            'clusters' => $this->clusters($text),
            'tokens' => $this->tokens($text),
        ];
    }

    /** @return list<string> */
    private function clusters(string $text): array
    {
        return collect((array) config('creator_catalog.semantic_clusters'))
            ->filter(function (array $aliases) use ($text): bool {
                foreach ($aliases as $alias) {
                    $quoted = preg_quote(Str::lower(Str::ascii((string) $alias)), '/');

                    if (preg_match("/(?<![a-z0-9]){$quoted}(?![a-z0-9])/", $text) === 1) {
                        return true;
                    }
                }

                return false;
            })
            ->keys()
            ->values()
            ->all();
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
            ->flatMap(function (mixed $value): array {
                if (is_string($value)) {
                    return [$value];
                }

                return is_array($value) ? $this->carouselText($value) : [];
            })
            ->take(80)
            ->values()
            ->all();
    }
}
