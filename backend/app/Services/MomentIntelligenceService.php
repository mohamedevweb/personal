<?php

namespace App\Services;

use Illuminate\Support\Str;

class MomentIntelligenceService
{
    /** @return array{score: int, reasons: list<string>} */
    public function analyze(string $content, string $category): array
    {
        $score = 5;
        $reasons = ['personal and specific'];

        if (in_array($category, ['Failure', 'Launch', 'Milestone', 'Win'], true)) {
            $score += 2;
            $reasons[] = 'contains a clear narrative event';
        }
        if (preg_match('/\b(decided|changed|realized|learned|pivot|failed|first|finally)\b/i', $content)) {
            $score += 1;
            $reasons[] = 'strong transformation';
        }
        if (Str::length($content) >= 70) {
            $score += 1;
            $reasons[] = 'enough detail to make the story credible';
        }

        return ['score' => min(10, $score), 'reasons' => array_values(array_unique($reasons))];
    }
}
