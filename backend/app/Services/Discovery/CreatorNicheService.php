<?php

namespace App\Services\Discovery;

use App\Services\Llm\LlmJsonService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads what a *discovered* account is actually about, from its own bio, captions
 * and recurring hashtags.
 *
 * Discovery used to stamp every creator with the niche of whichever user found
 * them, so the label described the searcher, and two users sharing the content
 * pool overwrote each other. The feed cannot tell a real niche match from a
 * coincidence on a label like that, which is what this replaces.
 *
 * Mirrors NicheDetectionService — the same job for the authenticated creator —
 * including its rule that a missing model degrades to a heuristic rather than
 * failing the run.
 *
 * @phpstan-type Signals array{niche: string, topics: list<string>, primary_vertical: ?string}
 */
class CreatorNicheService
{
    public const ANALYSIS_VERSION = 3;

    private const CAPTION_LIMIT = 15;

    private const CAPTION_CHARACTERS = 500;

    public function __construct(
        private readonly LlmJsonService $llm,
        private readonly CanonicalCreatorVerticals $verticals,
    ) {}

    /** @return Signals */
    public function detect(DiscoveredProfile $profile): array
    {
        $posts = $profile->posts->take(self::CAPTION_LIMIT)->values();
        $hashtags = $this->recurringHashtags($profile, $posts);

        return $this->viaLlm($profile, $posts, $hashtags)
            ?? $this->heuristic($profile, $posts, array_keys($hashtags));
    }

    /**
     * @param  Collection<int, DiscoveredPost>  $posts
     * @param  array<string, int>  $hashtags
     * @return Signals|null
     */
    private function viaLlm(DiscoveredProfile $profile, Collection $posts, array $hashtags): ?array
    {
        $context = array_filter([
            'Handle' => $profile->username,
            'Display name' => $profile->displayName,
            'Bio' => $profile->bio,
            'Profile category' => data_get($profile->metadata, 'category')
                ?? data_get($profile->metadata, 'business_category'),
            'External website' => data_get($profile->metadata, 'external_url'),
            'Recurring hashtags' => $hashtags === []
                ? 'None repeated across multiple posts'
                : collect($hashtags)
                    ->map(fn (int $count, string $tag): string => "{$tag} ({$count}/{$posts->count()} posts)")
                    ->implode(', '),
            'Caption sample' => $this->captionSample($posts),
        ]);

        if ($context === []) {
            return null;
        }

        $verticalChoices = collect($this->verticals->slugs())
            ->map(fn (string $slug): string => "{$slug} (".config("creator_catalog.verticals.{$slug}.name").')')
            ->implode(', ');

        $result = $this->llm->object(
            'First synthesize a durable Creator DNA summary from the stable editorial identity of an Instagram account, '
            .'then classify primary_vertical from that synthesized summary. Do not assign the vertical from an isolated '
            .'keyword or directly from the latest post. The result must describe the account, not the subject of its '
            .'latest post or campaign. Use this evidence hierarchy: profile bio and category first, themes repeated across distinct '
            .'captions second, and hashtags repeated across distinct posts third. A giveaway, competition, collaboration, '
            .'location, prop, anecdote, product mention or food shown in one post is not a niche. Treat recurring '
            .'presentation tactics such as giveaways, street interviews and challenges as content mechanics, not the '
            .'underlying subject, unless the account consistently teaches that mechanic itself. Every topic must be '
            .'supported by the bio or by at least two distinct posts. Return a durable niche label of 2 to 4 words, '
            .'3 to 8 durable lowercase topics, any recurring content mechanics separately, a one-sentence evidence '
            .'summary, and confidence from 0 to 1. After synthesizing that Creator DNA summary, choose exactly one '
            .'primary_vertical from this closed taxonomy: '
            .$verticalChoices.'. The vertical describes the account’s main subject, not its location, format, audience '
            .'or a one-off campaign. An account that creates and promotes recurring social events belongs to events, '
            .'even when those events are local. Use local-culture only when local discovery or culture is the actual subject. '
            .'Prefer a broad defensible label such as entrepreneurship over a campaign-specific hybrid such as '
            .'entrepreneurship giveaways.',
            collect($context)->map(fn ($value, $key): string => "{$key}: {$value}")->implode("\n"),
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['primary_vertical', 'niche', 'topics', 'content_mechanics', 'evidence_summary', 'confidence'],
                'properties' => [
                    'primary_vertical' => ['type' => 'string', 'enum' => $this->verticals->slugs()],
                    'niche' => ['type' => 'string'],
                    'topics' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'content_mechanics' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'evidence_summary' => ['type' => 'string'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
            ],
        );

        $niche = $this->stableNiche(
            trim((string) ($result['niche'] ?? '')),
            $result['content_mechanics'] ?? [],
        );

        if ($niche === '') {
            return null;
        }

        return [
            'niche' => $niche,
            'topics' => $this->supportedTopics($result['topics'] ?? [], $profile, $posts, array_keys($hashtags)),
            'primary_vertical' => $this->verticals->canonical($result['primary_vertical'] ?? null),
        ];
    }

    /**
     * Free, deterministic fallback: the account's recurring hashtags are the niche,
     * topped up with the most frequent meaningful words in its bio and captions.
     *
     * @param  Collection<int, DiscoveredPost>  $posts
     * @param  list<string>  $hashtags
     * @return Signals
     */
    private function heuristic(DiscoveredProfile $profile, Collection $posts, array $hashtags): array
    {
        $bioWords = $this->meaningfulTokens((string) $profile->bio);
        $captionWords = $posts
            ->map(fn (DiscoveredPost $post): array => array_values(array_unique($this->meaningfulTokens($post->caption))))
            ->flatten()
            ->countBy()
            ->sortDesc()
            ->filter(fn (int $count): bool => $count >= 2)
            ->keys()
            ->take(6)
            ->all();

        $topics = collect([...$hashtags, ...$bioWords, ...$captionWords])->unique()->take(8)->values()->all();

        if ($topics === []) {
            $topics = $this->meaningfulTokens(str_replace(['.', '_'], ' ', $profile->username));
        }

        return [
            'niche' => $topics === []
                ? Str::headline(Str::before($profile->username, '.'))
                : Str::headline(implode(' ', array_slice($topics, 0, 2))),
            'topics' => $topics,
            'primary_vertical' => null,
        ];
    }

    /**
     * Counts a hashtag once per post, then discards one-off and self tags before
     * the model sees them. Calling the remainder "recurring" is now truthful.
     *
     * @param  Collection<int, DiscoveredPost>  $posts
     * @return array<string, int>
     */
    private function recurringHashtags(DiscoveredProfile $profile, Collection $posts): array
    {
        $selfTags = $this->meaningfulTokens(str_replace(['.', '_'], ' ', $profile->username));
        $blocked = (array) config('services.discovery.blocked_hashtags');

        return $posts
            ->flatMap(fn (DiscoveredPost $post): array => collect($post->hashtags)
                ->map(fn (string $tag): string => Str::lower(ltrim(trim($tag), '#')))
                ->filter()
                ->unique()
                ->all())
            ->countBy()
            ->filter(fn (int $count, string $tag): bool => $count >= 2
                && ! in_array($tag, $blocked, true)
                && ! in_array($tag, $selfTags, true)
                && Str::lower($profile->username) !== $tag)
            ->sortDesc()
            ->take(12)
            ->all();
    }

    /** @param Collection<int, DiscoveredPost> $posts */
    private function captionSample(Collection $posts): string
    {
        return $posts
            ->values()
            ->map(fn (DiscoveredPost $post, int $index): string => '[Post '.($index + 1).'] '
                .Str::limit(trim($post->caption), self::CAPTION_CHARACTERS))
            ->filter(fn (string $caption): bool => trim(Str::after($caption, ']')) !== '')
            ->implode("\n");
    }

    /**
     * The model can still overreact to a vivid noun. A topic survives only when
     * its words occur in persistent profile metadata, a recurring hashtag, or at
     * least two distinct captions.
     *
     * @param  Collection<int, DiscoveredPost>  $posts
     * @param  list<string>  $hashtags
     * @return list<string>
     */
    private function supportedTopics(mixed $values, DiscoveredProfile $profile, Collection $posts, array $hashtags): array
    {
        $persistentTokens = $this->meaningfulTokens(implode(' ', array_filter([
            $profile->displayName,
            $profile->bio,
            data_get($profile->metadata, 'category'),
            data_get($profile->metadata, 'business_category'),
        ], 'is_string')));
        $hashtagTokens = $this->meaningfulTokens(implode(' ', $hashtags));
        $captionTokens = $posts->map(fn (DiscoveredPost $post): array => $this->meaningfulTokens($post->caption));

        return collect($this->stringList($values, 12))
            ->filter(function (string $topic) use ($persistentTokens, $hashtagTokens, $captionTokens): bool {
                $tokens = $this->meaningfulTokens($topic);

                if ($tokens === []) {
                    return false;
                }

                if (array_intersect($tokens, $persistentTokens) !== [] || array_intersect($tokens, $hashtagTokens) !== []) {
                    return true;
                }

                return $captionTokens
                    ->filter(fn (array $caption): bool => array_intersect($tokens, $caption) !== [])
                    ->count() >= 2;
            })
            ->take(8)
            ->values()
            ->all();
    }

    private function stableNiche(string $niche, mixed $contentMechanics): string
    {
        if ($niche === '') {
            return '';
        }

        $mechanicTokens = $this->meaningfulTokens(implode(' ', $this->stringList($contentMechanics, 8)));

        if ($mechanicTokens === []) {
            return $niche;
        }

        $stableWords = collect(preg_split('/\s+/u', $niche) ?: [])
            ->reject(fn (string $word): bool => array_intersect($this->meaningfulTokens($word), $mechanicTokens) !== [])
            ->values()
            ->all();

        return $stableWords === [] ? $niche : implode(' ', $stableWords);
    }

    /** @return list<string> */
    private function meaningfulTokens(string $text): array
    {
        preg_match_all('/[\pL\pN]{3,}/u', Str::lower($text), $matches);
        $blocked = [
            'about', 'after', 'again', 'also', 'avec', 'been', 'dans', 'from', 'have', 'here', 'into',
            'just', 'link', 'more', 'pour', 'recent', 'sample', 'that', 'their', 'there', 'these', 'they',
            'this', 'une', 'what', 'when', 'where', 'which', 'with', 'your', 'youre', 'bio', 'post',
        ];

        return collect($matches[0] ?? [])
            ->reject(fn (string $word): bool => in_array($word, $blocked, true))
            ->map(fn (string $word): string => Str::endsWith($word, 's') && ! Str::endsWith($word, 'ss') && Str::length($word) > 4
                ? Str::substr($word, 0, -1)
                : $word)
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function stringList(mixed $values, int $limit): array
    {
        return collect(is_array($values) ? $values : [])
            ->filter(fn (mixed $value): bool => is_string($value))
            ->map(fn (string $value): string => Str::lower(trim($value)))
            ->filter()
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }
}
