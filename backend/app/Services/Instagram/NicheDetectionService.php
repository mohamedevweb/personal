<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Llm\LlmJsonService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Works out a creator's niche from the richest signals on their profile — the
 * bio, the link in their bio (and what that page is about) and a sample of their
 * captions — via a language model. When no model is configured it falls back to a
 * conservative canonical classification, so the sync never invents a niche from
 * isolated words.
 *
 * @phpstan-type Signals array{
 *   primary_niche: ?string,
 *   sub_niches: list<string>,
 *   topics: list<string>,
 *   audience: list<string>,
 *   positioning: ?string,
 *   language: string,
 *   content_pillars: list<string>,
 *   tone: list<string>,
 *   current_projects: list<string>,
 *   goals: list<string>,
 *   content_strengths: list<string>,
 *   voice_profile: ?string,
 *   analysis_version: int,
 *   analysis_status: string,
 *   analysis_method: string,
 *   confidence: float,
 *   evidence: array{caption_count: int, bio_available: bool, link_preview_available: bool}
 * }
 */
class NicheDetectionService
{
    public const ANALYSIS_VERSION = 2;

    private const CAPTION_CHARACTERS = 500;

    public function __construct(
        private readonly LlmJsonService $llm,
        private readonly CanonicalCreatorVerticals $verticals,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $media
     * @return Signals
     */
    public function detect(InstagramAccount $account, array $media): array
    {
        $captionList = collect($media)
            ->pluck('caption')
            ->filter(fn (mixed $caption): bool => is_string($caption))
            ->map(fn (string $caption): string => $this->cleanText($caption))
            ->filter()
            ->take(30)
            ->values();
        $captions = $captionList->implode("\n");
        $bio = $this->cleanText((string) $account->bio);
        $linkPreview = $this->linkPreview($account->website);
        $evidence = [
            'caption_count' => $captionList->count(),
            'bio_available' => $bio !== '',
            'link_preview_available' => $linkPreview !== null,
        ];

        if (! $this->hasEnoughEvidence($bio, $captionList->all(), $linkPreview)) {
            return $this->emptySignals('insufficient_evidence', 'none', $evidence);
        }

        $signals = $this->viaLlm($account, $bio, $this->captionSample($captionList), $linkPreview);

        if ($signals !== null) {
            $confidence = max(0.0, min(1.0, (float) ($signals['confidence'] ?? 0.0)));

            return [
                ...$signals,
                'analysis_status' => $confidence >= 0.65 ? 'complete' : 'partial',
                'analysis_method' => 'llm',
                'analysis_version' => self::ANALYSIS_VERSION,
                'confidence' => $confidence,
                'evidence' => $evidence,
            ];
        }

        return $this->heuristic($account, $bio, $captions, $media, $evidence);
    }

    /**
     * @return Signals|null
     */
    private function viaLlm(
        InstagramAccount $account,
        string $bio,
        string $captionSample,
        ?string $linkPreview,
    ): ?array {
        $context = array_filter([
            'Display name' => $this->cleanText((string) $account->display_name),
            'Account type' => $account->account_type,
            'Profile category' => $this->cleanText((string) $account->category),
            'Bio' => $bio,
            'What the linked page is about' => $linkPreview,
            'Caption sample' => $captionSample,
        ]);

        if ($context === []) {
            return null;
        }

        $input = collect($context)->map(fn ($v, $k): string => "{$k}: {$v}")->implode("\n");

        $result = $this->llm->object(
            'Build a precise Creator DNA from the stable editorial identity of an Instagram profile, not the subject '
            .'of its latest post or current campaign. Use this evidence hierarchy: bio and linked-page metadata first, '
            .'themes repeated across distinct captions second, and isolated mentions last. Identify the narrowest '
            .'defensible primary niche, then adjacent sub-niches, concrete topics, intended audiences, main content '
            .'pillars, language, tone and a concise voice profile. Also write one concise positioning statement that '
            .'explains what the creator consistently helps people understand, achieve or experience. Extract current '
            .'projects and goals only when the creator states them explicitly in the bio, linked page or captions. '
            .'Describe content strengths only as recurring, observable execution patterns across multiple posts. '
            .'A giveaway, competition, collaboration, location, '
            .'prop, anecdote, product mention or food shown in one post is not a niche or content pillar. A topic must '
            .'be supported by the bio, linked page or at least two distinct posts. Separate what the creator consistently '
            .'talks about from the mechanics used to present it. The voice profile must describe observable writing '
            .'habits such as sentence length, point of view, pacing, openings and how conclusions land. Preserve useful '
            .'hierarchy, for example business, entrepreneurship, SaaS, AI SaaS, build in public. Base every field on '
            .'available evidence, never guesses. Return an empty string or empty list when a positioning detail, project, '
            .'goal or strength is not supported. Return confidence from 0 to 1 based on consistency across the sample.',
            $input,
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['primary_niche', 'sub_niches', 'topics', 'audience', 'positioning', 'language', 'content_pillars', 'tone', 'current_projects', 'goals', 'content_strengths', 'voice_profile', 'confidence'],
                'properties' => [
                    'primary_niche' => ['type' => 'string'],
                    'sub_niches' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'topics' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'audience' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'positioning' => ['type' => 'string'],
                    'language' => ['type' => 'string'],
                    'content_pillars' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'tone' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'current_projects' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'goals' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'content_strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'voice_profile' => [
                        'type' => 'string',
                        'description' => 'Three to six concise, evidence-based observations about how this creator writes. No advice and no invented traits.',
                    ],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
            ],
        );

        if (! $result) {
            return null;
        }

        $niche = trim((string) ($result['primary_niche'] ?? ''));

        if ($this->looksLikeNoise($niche, $account)) {
            return null;
        }

        return [
            'primary_niche' => $niche !== '' ? $niche : null,
            'sub_niches' => $this->stringList($result['sub_niches'] ?? [], 6),
            'topics' => $this->stringList($result['topics'] ?? [], 10),
            'audience' => $this->stringList($result['audience'] ?? [], 6),
            'positioning' => Str::limit(trim((string) ($result['positioning'] ?? '')), 1000) ?: null,
            'language' => trim((string) ($result['language'] ?? 'und')) ?: 'und',
            'content_pillars' => $this->stringList($result['content_pillars'] ?? [], 8),
            'tone' => $this->stringList($result['tone'] ?? [], 3),
            'current_projects' => $this->stringList($result['current_projects'] ?? [], 6),
            'goals' => $this->stringList($result['goals'] ?? [], 6),
            'content_strengths' => $this->stringList($result['content_strengths'] ?? [], 6),
            'voice_profile' => Str::limit(trim((string) ($result['voice_profile'] ?? '')), 2000) ?: null,
            'confidence' => (float) ($result['confidence'] ?? 0.0),
        ];
    }

    /** @param Collection<int, string> $captions */
    private function captionSample(Collection $captions): string
    {
        return $captions
            ->values()
            ->map(fn (string $caption, int $index): string => '[Post '.($index + 1).'] '
                .Str::limit($caption, self::CAPTION_CHARACTERS))
            ->implode("\n");
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

        $text = $this->cleanText(html_entity_decode(strip_tags(($title[1] ?? '').' '.($description[1] ?? ''))));

        return $text !== '' ? Str::limit($text, 300) : null;
    }

    /**
     * The original word-frequency fallback: rank the meaningful words in the bio and
     * captions. Deterministic and free — used whenever the model is unavailable.
     *
     * @param  list<array<string, mixed>>  $media
     * @return Signals
     */
    private function heuristic(
        InstagramAccount $account,
        string $bio,
        string $captions,
        array $media,
        array $evidence,
    ): array {
        $source = Str::lower(trim($bio.' '.$captions));
        $vertical = $this->verticals->fromSignals([$source]);

        if ($vertical === null) {
            return $this->emptySignals('insufficient_evidence', 'heuristic', $evidence);
        }

        $bioWords = $this->meaningfulWords($bio, $account);
        $topics = collect([...$bioWords, ...$this->meaningfulWords($captions, $account)])
            ->countBy()
            ->sortDesc()
            ->filter(fn (int $count, string $word): bool => $count >= 2 || in_array($word, $bioWords, true))
            ->keys()
            ->take(8)
            ->map(fn (string $word) => Str::headline($word))
            ->values()
            ->all();

        $tone = [];
        if (preg_match('/\b(i|my|me|we|our)\b/i', $captions)) {
            $tone[] = 'Personal';
        }
        if (preg_match('/\b(how|why|lessons?|learn(?:ed|ing)?|steps?|tips?)\b/i', $captions)) {
            $tone[] = 'Educational';
        }
        if ($captions !== '') {
            $tone[] = Str::length($captions) / max(count($media), 1) < 350 ? 'Concise' : 'Story-driven';
        }

        $primaryNiche = (string) config("creator_catalog.verticals.{$vertical}.name");
        $confidence = min(0.6, 0.35 + ($bio !== '' ? 0.1 : 0.0) + (count($media) >= 3 ? 0.15 : 0.0));

        return [
            'primary_niche' => $primaryNiche !== '' ? $primaryNiche : null,
            'sub_niches' => [],
            'topics' => $topics,
            'audience' => [],
            'positioning' => $bio !== '' ? Str::limit($bio, 1000) : null,
            'language' => $this->language($captions.' '.$bio),
            'content_pillars' => array_slice($topics, 0, 5),
            'tone' => array_values(array_unique($tone)),
            'current_projects' => [],
            'goals' => [],
            'content_strengths' => $this->heuristicContentStrengths($tone),
            'voice_profile' => $this->heuristicVoiceProfile($captions, $media, $tone),
            'analysis_status' => 'partial',
            'analysis_method' => 'heuristic',
            'analysis_version' => self::ANALYSIS_VERSION,
            'confidence' => $confidence,
            'evidence' => $evidence,
        ];
    }

    /** @param list<string> $captions */
    private function hasEnoughEvidence(string $bio, array $captions, ?string $linkPreview): bool
    {
        return count($this->meaningfulWords($bio)) >= 4
            || count($captions) >= 3
            || count($this->meaningfulWords((string) $linkPreview)) >= 6;
    }

    /** @return list<string> */
    private function meaningfulWords(string $text, ?InstagramAccount $account = null): array
    {
        preg_match_all('/[\pL\pN]{3,}/u', Str::lower($this->cleanText($text)), $matches);

        $blocked = [
            'about', 'after', 'again', 'also', 'and', 'avec', 'been', 'before', 'cette', 'dans', 'day', 'depuis',
            'elle', 'elles', 'for', 'from', 'have', 'here', 'http', 'https', 'into', 'just', 'leurs', 'mais',
            'mes', 'more', 'notre', 'nous', 'our', 'pour', 'sans', 'site', 'sont', 'sur', 'that', 'the', 'their',
            'there', 'these', 'they', 'this', 'une', 'very', 'vous', 'was', 'were', 'what', 'when', 'where',
            'which', 'who', 'with', 'www', 'your', 'youre', 'instagram',
            'reel', 'reels', 'post', 'posts', 'video', 'videos', 'follow', 'link', 'bio',
        ];
        $usernameParts = preg_split('/[^\pL\pN]+/u', Str::lower((string) $account?->username)) ?: [];

        return collect($matches[0] ?? [])
            ->reject(fn (string $word): bool => in_array($word, $blocked, true))
            ->reject(fn (string $word): bool => in_array($word, $usernameParts, true))
            ->reject(fn (string $word): bool => preg_match('/^(?:19|20)\d{2}$/', $word) === 1)
            ->values()
            ->all();
    }

    private function cleanText(string $text): string
    {
        return Str::of(html_entity_decode(strip_tags($text)))
            ->replaceMatches('#https?://\S+|www\.\S+#iu', ' ')
            ->replaceMatches('/@[\pL\pN._-]+/u', ' ')
            ->replaceMatches('/#(?=[\pL\pN])/u', '')
            ->replaceMatches('/\b(?:19|20)\d{2}\b/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->value();
    }

    private function looksLikeNoise(string $niche, InstagramAccount $account): bool
    {
        if ($niche === '' || preg_match('#https?://|www\.|@#i', $niche)) {
            return true;
        }

        $normalizedNiche = Str::lower(preg_replace('/[^\pL\pN]/u', '', $niche) ?? '');
        $normalizedUsername = Str::lower(preg_replace('/[^\pL\pN]/u', '', (string) $account->username) ?? '');

        return $normalizedNiche === '' || ($normalizedUsername !== '' && $normalizedNiche === $normalizedUsername);
    }

    /** @return Signals */
    private function emptySignals(string $status, string $method, array $evidence): array
    {
        return [
            'primary_niche' => null,
            'sub_niches' => [],
            'topics' => [],
            'audience' => [],
            'positioning' => null,
            'language' => 'und',
            'content_pillars' => [],
            'tone' => [],
            'current_projects' => [],
            'goals' => [],
            'content_strengths' => [],
            'voice_profile' => null,
            'analysis_status' => $status,
            'analysis_method' => $method,
            'analysis_version' => self::ANALYSIS_VERSION,
            'confidence' => 0.0,
            'evidence' => $evidence,
        ];
    }

    private function language(string $text): string
    {
        $frenchSignals = preg_match_all('/\b(avec|dans|pour|une|des|les|mon|mes|comment|pourquoi)\b/iu', $text);
        $englishSignals = preg_match_all('/\b(with|this|that|your|how|why|from|into|building)\b/iu', $text);

        return $frenchSignals > $englishSignals ? 'fr' : ($englishSignals > 0 ? 'en' : 'und');
    }

    /** @param list<array<string, mixed>> $media */
    private function heuristicVoiceProfile(string $captions, array $media, array $tone): ?string
    {
        if ($captions === '') {
            return null;
        }

        $observations = [];
        if (preg_match('/\b(i|my|me|we|our)\b/i', $captions)) {
            $observations[] = 'Writes in the first person and speaks from direct experience.';
        }

        $averageLength = Str::length($captions) / max(count($media), 1);
        $observations[] = $averageLength < 350
            ? 'Uses concise captions and reaches the point quickly.'
            : 'Develops ideas through longer, story-led captions.';

        if (in_array('Educational', $tone, true)) {
            $observations[] = 'Frames ideas as practical lessons for the audience.';
        }

        return implode(' ', $observations);
    }

    /** @param list<string> $tone @return list<string> */
    private function heuristicContentStrengths(array $tone): array
    {
        $strengths = collect($tone)->map(fn (string $signal): ?string => match ($signal) {
            'Personal' => 'First-person storytelling',
            'Educational' => 'Practical education',
            'Concise' => 'Concise delivery',
            'Story-driven' => 'Story-led content',
            default => null,
        });

        return $strengths->filter()->values()->all();
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
