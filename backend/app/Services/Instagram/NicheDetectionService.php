<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Services\Llm\LlmJsonService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Works out a creator's niche from the richest signals on their profile — the
 * bio, the link in their bio (and what that page is about) and a sample of their
 * captions — via a language model. When no model is configured it falls back to a
 * word-frequency heuristic, so the sync never depends on an LLM being present.
 *
 * @phpstan-type Signals array{
 *   primary_niche: ?string,
 *   sub_niches: list<string>,
 *   topics: list<string>,
 *   audience: list<string>,
 *   language: string,
 *   content_pillars: list<string>,
 *   tone: list<string>
 * }
 */
class NicheDetectionService
{
    public function __construct(private readonly LlmJsonService $llm) {}

    /**
     * @param  list<array<string, mixed>>  $media
     * @return Signals
     */
    public function detect(InstagramAccount $account, array $media): array
    {
        $captions = collect($media)->pluck('caption')->filter()->take(30)->implode("\n");

        $signals = $this->viaLlm($account, $captions) ?? $this->heuristic($account, $captions, $media);

        return $signals;
    }

    /**
     * @return Signals|null
     */
    private function viaLlm(InstagramAccount $account, string $captions): ?array
    {
        $context = array_filter([
            'Display name' => $account->display_name,
            'Account type' => $account->account_type,
            'Bio' => $account->bio,
            'Bio link' => $account->website,
            'What the linked page is about' => $this->linkPreview($account->website),
            'Recent captions' => Str::limit($captions, 1500),
        ]);

        if ($context === []) {
            return null;
        }

        $input = collect($context)->map(fn ($v, $k): string => "{$k}: {$v}")->implode("\n");

        $result = $this->llm->object(
            'Build a precise Creator DNA from an Instagram profile. Identify the narrowest defensible primary niche, '
            .'then its adjacent sub-niches, concrete topics, intended audiences, main content pillars, language and '
            .'tone. Preserve useful hierarchy: for example business, entrepreneurship, SaaS, AI SaaS, build in public. '
            .'Base every field on the bio, link metadata, captions and available profile evidence, never guesses.',
            $input,
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['primary_niche', 'sub_niches', 'topics', 'audience', 'language', 'content_pillars', 'tone'],
                'properties' => [
                    'primary_niche' => ['type' => 'string'],
                    'sub_niches' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'topics' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'audience' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'language' => ['type' => 'string'],
                    'content_pillars' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'tone' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        );

        if (! $result) {
            return null;
        }

        $niche = trim((string) ($result['primary_niche'] ?? ''));

        return [
            'primary_niche' => $niche !== '' ? $niche : null,
            'sub_niches' => $this->stringList($result['sub_niches'] ?? [], 6),
            'topics' => $this->stringList($result['topics'] ?? [], 10),
            'audience' => $this->stringList($result['audience'] ?? [], 6),
            'language' => trim((string) ($result['language'] ?? 'und')) ?: 'und',
            'content_pillars' => $this->stringList($result['content_pillars'] ?? [], 8),
            'tone' => $this->stringList($result['tone'] ?? [], 3),
        ];
    }

    /**
     * Best-effort read of the bio link so the model knows what the creator sells or
     * makes. Only the page's title and description are used, with a tight timeout;
     * any failure is swallowed and the link is simply skipped.
     */
    private function linkPreview(?string $url): ?string
    {
        if (! is_string($url) || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        try {
            $html = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; PersonalBot/1.0)'])
                ->get($url)
                ->body();
        } catch (Throwable) {
            return null;
        }

        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $title);
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $html, $description);

        $text = trim(html_entity_decode(strip_tags(($title[1] ?? '').' — '.($description[1] ?? ''))));

        return $text !== '—' && $text !== '' ? Str::limit($text, 300) : null;
    }

    /**
     * The original word-frequency fallback: rank the meaningful words in the bio and
     * captions. Deterministic and free — used whenever the model is unavailable.
     *
     * @param  list<array<string, mixed>>  $media
     * @return Signals
     */
    private function heuristic(InstagramAccount $account, string $captions, array $media): array
    {
        $source = Str::lower(trim(($account->bio ?? '').' '.$account->website.' '.$captions));
        $stopWords = ['about', 'after', 'again', 'also', 'been', 'from', 'have', 'here', 'into', 'just', 'more', 'that', 'their', 'there', 'these', 'they', 'this', 'what', 'when', 'where', 'which', 'with', 'your', 'youre'];

        preg_match_all('/[\pL\pN]{4,}/u', $source, $matches);
        $topics = collect($matches[0] ?? [])
            ->reject(fn (string $word) => in_array($word, $stopWords, true))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(5)
            ->map(fn (string $word) => Str::headline($word))
            ->values()
            ->all();

        $tone = [];
        if (preg_match('/\b(i|my|me|we|our)\b/i', $captions)) {
            $tone[] = 'Personal';
        }
        if (preg_match('/\b(how|why|lesson|learn|steps|tips)\b/i', $captions)) {
            $tone[] = 'Educational';
        }
        if ($captions !== '') {
            $tone[] = Str::length($captions) / max(count($media), 1) < 350 ? 'Concise' : 'Story-driven';
        }

        $primaryNiche = $topics === [] ? null : implode(' / ', array_slice($topics, 0, 2));

        return [
            'primary_niche' => $primaryNiche,
            'sub_niches' => array_slice($topics, 1, 4),
            'topics' => $topics,
            'audience' => [],
            'language' => $this->language($captions.' '.($account->bio ?? '')),
            'content_pillars' => array_slice($topics, 0, 5),
            'tone' => array_values(array_unique($tone)),
        ];
    }

    private function language(string $text): string
    {
        $frenchSignals = preg_match_all('/\b(avec|dans|pour|une|des|les|mon|mes|comment|pourquoi)\b/iu', $text);
        $englishSignals = preg_match_all('/\b(with|this|that|your|how|why|from|into|building)\b/iu', $text);

        return $frenchSignals > $englishSignals ? 'fr' : ($englishSignals > 0 ? 'en' : 'und');
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values, int $limit): array
    {
        return collect(is_array($values) ? $values : [])
            ->filter(fn (mixed $value): bool => is_string($value))
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }
}
