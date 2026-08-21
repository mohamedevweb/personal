<?php

namespace App\Services;

use App\Models\ContentPost;
use Illuminate\Support\Collection;

class MarketFeedAllocator
{
    /**
     * @param  Collection<int, array{post: ContentPost, ranking: array<string, float>}>  $ranked
     * @return Collection<int, array{post: ContentPost, ranking: array<string, float>}>
     */
    public function allocate(Collection $ranked, ?string $market, int $limit): Collection
    {
        $markets = ['FR', 'GB', 'US'];
        $quotas = $this->quotas(in_array($market, $markets, true) ? $market : null, $limit);
        $selected = collect();

        foreach ($quotas as $candidateMarket => $quota) {
            $selected->push(...$ranked
                ->filter(fn (array $item): bool => $item['post']->creator->market === $candidateMarket)
                ->take($quota));
        }

        $selectedIds = $selected->pluck('post.id')->flip();
        $selected->push(...$ranked
            ->reject(fn (array $item): bool => $selectedIds->has($item['post']->id))
            ->take(max(0, $limit - $selected->count())));

        return $selected->sortByDesc('ranking.score')->take($limit)->values();
    }

    /** @return array<string, int> */
    private function quotas(?string $market, int $limit): array
    {
        if ($market === null) {
            $base = intdiv($limit, 3);
            $remainder = $limit % 3;

            return [
                'FR' => $base + ($remainder > 0 ? 1 : 0),
                'GB' => $base + ($remainder > 1 ? 1 : 0),
                'US' => $base,
            ];
        }

        $primary = (int) ceil($limit * 2 / 3);
        $secondary = intdiv($limit - $primary, 2);
        $quotas = array_fill_keys(['FR', 'GB', 'US'], $secondary);
        $quotas[$market] = $primary;

        foreach (array_keys($quotas) as $candidate) {
            if ($candidate !== $market && array_sum($quotas) < $limit) {
                $quotas[$candidate]++;
            }
        }

        return $quotas;
    }
}
