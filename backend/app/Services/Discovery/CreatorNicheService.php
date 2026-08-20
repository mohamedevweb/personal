<?php

namespace App\Services\Discovery;

use App\Services\Llm\LlmJsonService;
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
 * @phpstan-type Signals array{niche: string, topics: list<string>}
 */
class CreatorNicheService
{
    public function __construct(private readonly LlmJsonService $llm) {}

    /** @return Signals */
    public function detect(DiscoveredProfile $profile): array
    {
        $captions = $profile->posts
            ->map(fn (DiscoveredPost $post): string => $post->caption)
            ->filter()
            ->take(15)
            ->implode("\n");

        // Recurring hashtags are the sharpest niche signal an account gives off:
        // a creator tags what they want to be found for, post after post.
        $hashtags = $profile->posts
            ->flatMap(fn (DiscoveredPost $post): array => $post->hashtags)
            ->map(fn (string $tag): string => Str::lower($tag))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(12)
            ->all();

        return $this->viaLlm($profile, $captions, $hashtags)
            ?? $this->heuristic($profile, $captions, $hashtags);
    }

    /**
     * @param  list<string>  $hashtags
     * @return Signals|null
     */
    private function viaLlm(DiscoveredProfile $profile, string $captions, array $hashtags): ?array
    {
        $context = array_filter([
            'Handle' => $profile->username,
            'Display name' => $profile->displayName,
            'Bio' => $profile->bio,
            'Most used hashtags' => implode(', ', $hashtags),
            'Recent captions' => Str::limit($captions, 1500),
        ]);

        if ($context === []) {
            return null;
        }

        $result = $this->llm->object(
            'You classify an Instagram account into the content niche it publishes in. Read the bio, the hashtags '
            .'it uses repeatedly and a sample of captions. Return a short niche label (2-4 words) and the concrete '
            .'topics it covers, as single lowercase words or short phrases. Describe what the account posts about, '
            .'not who follows it, and base it on evidence rather than guesses.',
            collect($context)->map(fn ($value, $key): string => "{$key}: {$value}")->implode("\n"),
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['niche', 'topics'],
                'properties' => [
                    'niche' => ['type' => 'string'],
                    'topics' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        );

        $niche = trim((string) ($result['niche'] ?? ''));

        if ($niche === '') {
            return null;
        }

        return [
            'niche' => $niche,
            'topics' => $this->stringList($result['topics'] ?? [], 8),
        ];
    }

    /**
     * Free, deterministic fallback: the account's recurring hashtags are the niche,
     * topped up with the most frequent meaningful words in its bio and captions.
     *
     * @param  list<string>  $hashtags
     * @return Signals
     */
    private function heuristic(DiscoveredProfile $profile, string $captions, array $hashtags): array
    {
        $stopWords = ['about', 'after', 'again', 'also', 'been', 'from', 'have', 'here', 'into', 'just', 'link', 'more', 'that', 'their', 'there', 'these', 'they', 'this', 'what', 'when', 'where', 'which', 'with', 'your', 'youre'];

        preg_match_all('/[\pL\pN]{4,}/u', Str::lower(($profile->bio ?? '').' '.$captions), $matches);

        $words = collect($matches[0] ?? [])
            ->reject(fn (string $word): bool => in_array($word, $stopWords, true))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(6)
            ->all();

        $topics = collect([...$hashtags, ...$words])->unique()->take(8)->values()->all();

        return [
            'niche' => $topics === []
                ? Str::headline(Str::before($profile->username, '.'))
                : Str::headline(implode(' ', array_slice($topics, 0, 2))),
            'topics' => $topics,
        ];
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
