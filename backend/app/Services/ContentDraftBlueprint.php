<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\User;

/**
 * Everything a model needs to draft a piece of content: the standing instructions,
 * the brief assembled from the creator's own records, and the JSON shape the answer
 * has to take.
 *
 * This is deliberately provider-agnostic. Swapping the model behind
 * ContentGenerationService should never mean rewriting the prompt.
 */
class ContentDraftBlueprint
{
    private const SYSTEM = <<<'PROMPT'
        You are a ghostwriter for an independent creator. You are given a piece of
        content that performed well for someone else, plus real material from the
        creator's own life and work.

        Borrow only the structure of the source: its hook shape, the order it
        reveals information, the way it lands a takeaway. Never borrow its subject
        matter, its claims, or its phrasing. Every fact in what you write must come
        from the creator's own material — if their material is thin, write something
        smaller and true rather than inventing detail, numbers, or anecdotes.

        Write in the creator's voice, in first person, at the tone and reading level
        their audience already expects. Plain sentences, no hype, no emoji, no
        hashtags, no "in today's fast-paced world" openings.
        PROMPT;

    public function system(): string
    {
        $language = app()->getLocale() === 'fr' ? 'natural French' : 'English';

        return self::SYSTEM."\n\nWrite every part of the draft in {$language}.";
    }

    public function brief(ContentPost $source, User $user, string $format, ?LifeMoment $moment): string
    {
        $profile = $user->creatorProfile;

        $sections = [
            'THE PATTERN THAT WORKED (structure only — do not reuse its subject or wording)',
            "Creator niche: {$source->creator->niche}",
            "Hook: {$source->hook}",
            "Caption: {$source->caption}",
            "Why it works: {$source->why_it_works}",
            "Hook analysis: {$source->hook_analysis}",
            "Structure: {$source->structure_analysis}",
            '',
            'THE CREATOR YOU ARE WRITING AS',
            'Niche: '.($profile?->niche ?? 'unspecified'),
            'Positioning: '.($profile?->positioning ?? 'unspecified'),
            'Audience: '.($profile?->audience_description ?? 'unspecified'),
            'Tone: '.$this->list($profile?->tone),
            'Topics: '.$this->list($profile?->topics),
            'Current projects: '.$this->list($profile?->current_projects),
            'Content strengths: '.$this->list($profile?->content_strengths),
        ];

        if ($moment) {
            $sections = [...$sections, '', 'THE MOMENT TO BUILD ON (the only source of facts)',
                "Category: {$moment->category}",
                "What happened: {$moment->content}",
                'Why it has story potential: '.$this->list($moment->story_reasons),
            ];
        } else {
            $sections = [...$sections, '', 'No specific life moment was selected. Build on the creator profile above and keep every claim general enough to be true of it.'];
        }

        return implode("\n", [...$sections, '', $this->formatInstruction($format)]);
    }

    /**
     * Every property is required and no object accepts extra keys, which is both
     * what strict structured-output modes demand and what keeps the response
     * shape predictable for the editor.
     *
     * @return array<string, mixed>
     */
    public function schema(string $format): array
    {
        $properties = [
            'why_it_works' => [
                'type' => 'array',
                'description' => 'Three to five short reasons the borrowed structure works, written about this draft.',
                'items' => ['type' => 'string'],
            ],
            'your_version' => [
                'type' => 'string',
                'description' => 'The single strongest line of the draft, usable as a standalone hook.',
            ],
        ];

        $properties += match ($format) {
            'carousel' => ['slides' => [
                'type' => 'array',
                'description' => 'Exactly six slides in reading order.',
                'items' => [
                    'type' => 'object',
                    'properties' => ['text' => ['type' => 'string']],
                    'required' => ['text'],
                    'additionalProperties' => false,
                ],
            ]],
            'reel' => [
                'hook' => ['type' => 'string', 'description' => 'The spoken opening line.'],
                'script' => ['type' => 'string', 'description' => 'The full spoken script.'],
                'visual' => ['type' => 'string', 'description' => 'What to film and when to cut.'],
                'cta' => ['type' => 'string', 'description' => 'A closing question for the caption.'],
            ],
            default => ['caption' => ['type' => 'string', 'description' => 'The full caption, paragraphs separated by blank lines.']],
        };

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    private function formatInstruction(string $format): string
    {
        return match ($format) {
            'carousel' => 'Write a 6-slide Instagram carousel. Slide 1 is the hook and must stand alone. Each later slide advances the story by one beat and is at most two sentences.',
            'reel' => 'Write a talking-head Instagram reel: a spoken hook of one sentence, a script of roughly 45 to 60 seconds, a shot idea that suits the creator filming alone, and a closing question.',
            default => 'Write a single Instagram caption of three to five short paragraphs, opening on the hook line.',
        };
    }

    /** @param list<string>|null $values */
    private function list(?array $values): string
    {
        return $values ? implode(', ', $values) : 'unspecified';
    }
}
