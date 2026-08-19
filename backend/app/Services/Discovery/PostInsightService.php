<?php

namespace App\Services\Discovery;

use App\Models\ContentPost;
use App\Services\Llm\LlmJsonService;
use Illuminate\Support\Str;

/**
 * Fills a discovered post's analysis fields the first time a creator opens it.
 * Discovery stores posts with these fields empty to stay cheap and fast; this
 * generates the full breakdown lazily, with a heuristic fallback when no LLM key
 * is configured so a post is never shown blank.
 */
class PostInsightService
{
    public function __construct(private readonly LlmJsonService $llm) {}

    public function ensureAnalyzed(ContentPost $post): void
    {
        if ($post->hook_analysis !== '' && $post->structure_analysis !== '') {
            return;
        }

        $analysis = $this->analyze($post) ?? $this->fallback($post);

        $post->forceFill($analysis)->save();
    }

    /** @return array{why_it_works: string, hook_analysis: string, structure_analysis: string}|null */
    private function analyze(ContentPost $post): ?array
    {
        $post->loadMissing('creator');

        $result = $this->llm->object(
            'You are a short-form content strategist. Analyze why an Instagram post performs, in plain, '
            .'specific language a creator can act on. Two to three sentences per field.',
            implode("\n", [
                'Niche: '.($post->creator->niche ?? 'unknown'),
                'Format: '.$post->format,
                'Hook: '.$post->hook,
                'Caption: '.Str::limit($post->caption, 600),
                "Engagement: {$post->likes} likes, {$post->comments} comments, {$post->views} views.",
            ]),
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['why_it_works', 'hook_analysis', 'structure_analysis'],
                'properties' => [
                    'why_it_works' => ['type' => 'string'],
                    'hook_analysis' => ['type' => 'string'],
                    'structure_analysis' => ['type' => 'string'],
                ],
            ],
        );

        if (! $result) {
            return null;
        }

        return [
            'why_it_works' => (string) ($result['why_it_works'] ?? ''),
            'hook_analysis' => (string) ($result['hook_analysis'] ?? ''),
            'structure_analysis' => (string) ($result['structure_analysis'] ?? ''),
        ];
    }

    /** @return array{why_it_works: string, hook_analysis: string, structure_analysis: string} */
    private function fallback(ContentPost $post): array
    {
        return [
            'why_it_works' => $post->why_it_works
                ?: 'Strong engagement relative to its niche suggests the topic and timing resonated.',
            'hook_analysis' => "The hook \"{$post->hook}\" leads with a clear promise, which stops the scroll and "
                .'sets an expectation the post then pays off.',
            'structure_analysis' => 'A '.$post->format.' format with a tight hook, a single idea, and an explicit '
                .'save-worthy takeaway — the structure that reliably earns reach in this niche.',
        ];
    }
}
