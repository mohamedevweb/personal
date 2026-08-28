<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;

class CreatorMarketDetector
{
    /** @return array{market: ?string, confidence: float, language: string, evidence: list<string>} */
    public function detect(string $text): array
    {
        $text = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))) ?: '';
        $french = $this->matches($text, [' le ', ' la ', ' les ', ' une ', ' des ', ' avec ', ' pour ', ' dans ', ' conseils ', ' france ', ' paris ', ' lyon ', ' marseille ', ' country code fr ']);
        $english = $this->matches($text, [' the ', ' and ', ' with ', ' your ', ' how ', ' why ', ' coach ', ' creator ', ' founder ']);
        $spanish = $this->matches($text, [' el ', ' los ', ' las ', ' del ', ' al ', ' por ', ' con ', ' una ', ' para ', ' esta ', ' este ', ' mundo ', ' feliz ', ' lunes ', ' gracias ', ' nuevo ', ' segundo ', ' disco ', ' disponible ', ' espana ', ' madrid ', ' barcelona ']);
        $portuguese = $this->matches($text, [' que ', ' para ', ' com ', ' uma ', ' nao ', ' hoje ', ' voce ', ' seguidores ', ' brasil ', ' brasileiro ', ' brasileira ', ' sao paulo ', ' rio de janeiro ', ' country code br ']);
        $gb = $this->matches($text, [' uk ', ' united kingdom ', ' london ', ' manchester ', ' birmingham ', ' glasgow ', ' britain ', ' british ', ' england ', ' scotland ', ' wales ', ' country code gb ', ' country code uk ']);
        $us = $this->matches($text, [' usa ', ' united states ', ' new york ', ' nyc ', ' los angeles ', ' california ', ' miami ', ' austin ', ' texas ', ' american ', ' country code us ']);
        $language = match (true) {
            $french > 0 && $english > 0 && abs($french - $english) <= 1 => 'mixed',
            $spanish > max($french, $english, $portuguese) => 'es',
            $portuguese > max($french, $english, $spanish) => 'pt',
            $french > $english => 'fr',
            $english > 0 => 'en',
            default => 'unknown',
        };

        // Strong content-language evidence takes precedence over a provider
        // country code, which can describe the account location, not its audience.
        if ($spanish >= 2 && $spanish > max($french, $english, $portuguese)) {
            return ['market' => 'ES', 'confidence' => min(0.98, 0.70 + ($spanish * 0.04)), 'language' => 'es', 'evidence' => ['spanish_language']];
        }

        if ($portuguese >= 2 && $portuguese > max($french, $english, $spanish)) {
            return ['market' => 'BR', 'confidence' => min(0.98, 0.70 + ($portuguese * 0.04)), 'language' => 'pt', 'evidence' => ['portuguese_language']];
        }

        foreach (['fr' => 'FR', 'gb' => 'GB', 'uk' => 'GB', 'us' => 'US', 'br' => 'BR'] as $code => $market) {
            if (str_contains(" {$text} ", " country code {$code} ")) {
                return [
                    'market' => $market,
                    'confidence' => 0.98,
                    'language' => $market === 'FR' ? 'fr' : ($market === 'BR' ? 'pt' : 'en'),
                    'evidence' => ['provider_country_code'],
                ];
            }
        }

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
