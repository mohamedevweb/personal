<?php

namespace App\Services\Content;

use Illuminate\Support\Str;

class MomentIntelligenceService
{
    /** @return array{score: int, reasons: list<string>} */
    public function analyze(string $content, string $category): array
    {
        $score = 5;
        $reasons = [$this->reason('personal_specific')];

        if (in_array($category, ['Failure', 'Launch', 'Milestone', 'Win'], true)) {
            $score += 2;
            $reasons[] = $this->reason('narrative_event');
        }
        if (preg_match('/\b(decided|changed|realized|learned|pivot|failed|first|finally|décidé|décidée|changé|changée|compris|réalisé|réalisée|appris|pivoté|pivotée|échoué|échouée|premier|première|enfin)\b/iu', $content)) {
            $score += 1;
            $reasons[] = $this->reason('transformation');
        }
        if (Str::length($content) >= 70) {
            $score += 1;
            $reasons[] = $this->reason('credible_detail');
        }

        return ['score' => min(10, $score), 'reasons' => array_values(array_unique($reasons))];
    }

    private function reason(string $reason): string
    {
        $reasons = app()->getLocale() === 'fr'
            ? [
                'personal_specific' => 'personnel et précis',
                'narrative_event' => 'contient un événement narratif clair',
                'transformation' => 'transformation forte',
                'credible_detail' => 'assez de détails pour rendre le récit crédible',
            ]
            : [
                'personal_specific' => 'personal and specific',
                'narrative_event' => 'contains a clear narrative event',
                'transformation' => 'strong transformation',
                'credible_detail' => 'enough detail to make the story credible',
            ];

        return $reasons[$reason];
    }
}
