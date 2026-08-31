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

        return in_array($needle, $this->slugs(), true) ? $needle : null;
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return array_values(array_keys((array) config('creator_catalog.verticals')));
    }

    public function fromSignals(array $signals): ?string
    {
        foreach ($signals as $signal) {
            if ($canonical = $this->canonical(is_string($signal) ? $signal : null)) {
                return $canonical;
            }
        }

        // Free text is intentionally not mapped here. The analysis model owns
        // the editorial decision; this method only validates an explicit slug.
        return null;
    }
}
