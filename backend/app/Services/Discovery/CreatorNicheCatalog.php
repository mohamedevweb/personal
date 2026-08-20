<?php

namespace App\Services\Discovery;

use App\Models\Creator;
use App\Models\Niche;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatorNicheCatalog
{
    /** @param list<string> $topics */
    public function sync(Creator $creator, string $primaryNiche, array $topics): void
    {
        DB::transaction(function () use ($creator, $primaryNiche, $topics): void {
            $primary = $this->niche($primaryNiche);
            $pivots = [$primary->id => ['relevance_score' => 1, 'source' => 'analysis']];

            foreach (collect($topics)->filter()->unique()->take(8) as $topic) {
                $niche = $this->niche((string) $topic, $primary);

                if ($niche->is($primary)) {
                    continue;
                }

                $pivots[$niche->id] = ['relevance_score' => 0.75, 'source' => 'analysis'];
            }

            $creator->niches()->sync($pivots);
        });
    }

    private function niche(string $name, ?Niche $parent = null): Niche
    {
        $name = trim($name);
        $slug = Str::slug($name) ?: substr(hash('sha256', $name), 0, 20);

        return Niche::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'parent_id' => $parent?->id],
        );
    }
}
