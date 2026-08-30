<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;

class CanonicalCreatorVerticals
{
    public function canonical(?string $value): ?string
    {
        $needle = Str::lower(trim((string) $value));

        if ($needle === '') {
            return null;
        }

        foreach ((array) config('creator_catalog.verticals') as $slug => $vertical) {
            if ($needle === $slug || in_array($needle, array_map(fn (string $alias): string => Str::lower($alias), $vertical['aliases'] ?? []), true)) {
                return $slug;
            }
        }

        return null;
    }

    public function fromSignals(array $signals): ?string
    {
        foreach ($signals as $signal) {
            if ($canonical = $this->canonical(is_string($signal) ? $signal : null)) {
                return $canonical;
            }
        }

        $text = Str::lower(implode(' ', array_filter($signals, 'is_string')));

        $scores = [];

        foreach ((array) config('creator_catalog.verticals') as $slug => $vertical) {
            $scores[$slug] = 0.0;

            foreach ($vertical['aliases'] ?? [] as $alias) {
                $quoted = preg_quote(Str::lower($alias), '/');

                if (preg_match("/(?<![\\pL\\pN]){$quoted}(?![\\pL\\pN])/u", $text) === 1) {
                    // A phrase such as "intelligence artificielle" carries more
                    // evidence than a generic single word. Counting all matches
                    // also prevents the configuration order from deciding that
                    // "tech entrepreneurship + SaaS" is only personal branding.
                    $scores[$slug] += 1 + (substr_count(trim($alias), ' ') * 0.25);
                }
            }
        }

        $best = collect($scores)->sortDesc()->first();

        return $best > 0 ? (string) collect($scores)->search($best, strict: true) : null;
    }
}
