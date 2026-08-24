<?php

namespace App\Services;

use App\Exceptions\ContentGenerationException;
use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\User;

/**
 * Turns a model's JSON answer into the payload the editor renders.
 *
 * The model only ever supplies the creative half. The source hook, the selected
 * moment and the creator profile are our own records and are attached here, so no
 * provider can invent or drift them.
 */
class ContentDraftAssembler
{
    /** @return array<string, mixed> */
    public function assemble(
        ?string $json,
        ContentPost $source,
        User $user,
        string $format,
        ?LifeMoment $moment,
    ): array {
        $generated = $this->decode($json);
        $profile = $user->creatorProfile;

        return [
            'original_pattern' => $source->hook,
            'why_it_works' => array_values(array_map(
                strval(...),
                (array) ($generated['why_it_works'] ?? []),
            )),
            'your_context' => $moment?->content
                ?? $profile?->positioning
                ?? 'No specific moment was provided.',
            'your_version' => (string) ($generated['your_version'] ?? ''),
            'profile_used' => [
                'niche' => $profile?->niche,
                'tone' => $profile?->tone ?? [],
                'topics' => $profile?->topics ?? [],
            ],
        ] + $this->formatPayload($format, $generated);
    }

    public function block(?string $json): string
    {
        $generated = $this->decode($json);
        $text = trim((string) ($generated['text'] ?? ''));

        if ($text === '') {
            throw new ContentGenerationException('Personal received an unusable rewrite. Please try again.');
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $generated
     * @return array<string, mixed>
     */
    private function formatPayload(string $format, array $generated): array
    {
        if ($format !== 'carousel') {
            return array_intersect_key($generated, array_flip(match ($format) {
                'reel' => ['hook', 'script', 'visual', 'ending', 'cta'],
                default => ['caption'],
            }));
        }

        $slides = (array) ($generated['slides'] ?? []);

        // Slide ids drive the editor's reorder and delete controls, so they are
        // numbered here rather than trusted from the response.
        return ['slides' => array_values(array_map(
            fn (int $index, array $slide): array => [
                'id' => $index + 1,
                'text' => (string) ($slide['text'] ?? ''),
            ],
            array_keys($slides),
            $slides,
        ))];
    }

    /** @return array<string, mixed> */
    private function decode(?string $json): array
    {
        $decoded = json_decode((string) $json, true);

        if (! is_array($decoded)) {
            throw new ContentGenerationException('Personal received an unusable draft. Please try again.');
        }

        return $decoded;
    }
}
