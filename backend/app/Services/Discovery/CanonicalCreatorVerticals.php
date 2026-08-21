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

        foreach ((array) config('creator_catalog.verticals') as $slug => $vertical) {
            foreach ($vertical['aliases'] ?? [] as $alias) {
                if (Str::contains($text, Str::lower($alias))) {
                    return $slug;
                }
            }
        }

        return null;
    }
}
