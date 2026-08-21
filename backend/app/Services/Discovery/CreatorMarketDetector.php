<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;

class CreatorMarketDetector
{
    /** @return array{market: ?string, confidence: float, language: string, evidence: list<string>} */
    public function detect(string $text): array
    {
        $text = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))) ?: '';
        $french = $this->matches($text, [' le ', ' la ', ' les ', ' une ', ' des ', ' avec ', ' pour ', ' dans ', ' conseils ', ' france ', ' paris ', ' lyon ', ' marseille ']);
        $english = $this->matches($text, [' the ', ' and ', ' with ', ' your ', ' how ', ' why ', ' coach ', ' creator ', ' founder ']);
        $gb = $this->matches($text, [' uk ', ' united kingdom ', ' london ', ' manchester ', ' birmingham ', ' glasgow ', ' britain ', ' british ', ' england ', ' scotland ', ' wales ']);
        $us = $this->matches($text, [' usa ', ' united states ', ' new york ', ' nyc ', ' los angeles ', ' california ', ' miami ', ' austin ', ' texas ', ' american ']);
        $language = match (true) {
            $french > 0 && $english > 0 && abs($french - $english) <= 1 => 'mixed',
            $french > $english => 'fr',
            $english > 0 => 'en',
            default => 'unknown',
        };

        if ($french >= 2 && $french > $english) {
            return ['market' => 'FR', 'confidence' => min(0.98, 0.70 + ($french * 0.04)), 'language' => $language, 'evidence' => ['french_language']];
        }

        if ($english > 0 && $gb > $us && $gb > 0) {
            return ['market' => 'GB', 'confidence' => min(0.98, 0.70 + ($gb * 0.06)), 'language' => $language, 'evidence' => ['english_language', 'uk_signal']];
        }

        if ($english > 0 && $us > $gb && $us > 0) {
            return ['market' => 'US', 'confidence' => min(0.98, 0.70 + ($us * 0.06)), 'language' => $language, 'evidence' => ['english_language', 'us_signal']];
        }

        if ($english > 0) {
            return ['market' => null, 'confidence' => 0.45, 'language' => $language, 'evidence' => ['english_language', 'market_ambiguous']];
        }

        return ['market' => null, 'confidence' => 0.20, 'language' => 'unknown', 'evidence' => ['insufficient_evidence']];
    }

    private function matches(string $text, array $signals): int
    {
        $padded = " {$text} ";

        return collect($signals)->sum(fn (string $signal): int => substr_count($padded, $signal));
    }
}
