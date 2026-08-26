<?php

namespace App\Services\Feed;

use App\Models\ContentPost;
use Illuminate\Support\Collection;

class MarketFeedAllocator
{
    /**
     * @param  Collection<int, array{post: ContentPost, ranking: array<string, float>}>  $ranked
     * @return Collection<int, array{post: ContentPost, ranking: array<string, float>}>
     */
    public function allocate(Collection $ranked, ?string $market, int $limit, ?string $primaryVertical = null): Collection
    {
        $markets = ['FR', 'GB', 'US'];
        $quotas = $this->quotas(in_array($market, $markets, true) ? $market : null, $limit);
        $selected = collect();

        foreach ($quotas as $candidateMarket => $quota) {
            $marketCandidates = $ranked
                ->filter(fn (array $item): bool => $item['post']->creator->market === $candidateMarket);

            $selected->push(...$this->prioritizeVertical($marketCandidates, $primaryVertical)
                ->take($quota));
        }

        $selectedIds = $selected->pluck('post.id')->flip();
        $remaining = $ranked
            ->reject(fn (array $item): bool => $selectedIds->has($item['post']->id));
        $selected->push(...$this->prioritizeVertical($remaining, $primaryVertical)
            ->take(max(0, $limit - $selected->count())));

        return $this->prioritizeVertical($selected, $primaryVertical)->take($limit)->values();
    }

    /**
     * @param  Collection<int, array{post: ContentPost, ranking: array<string, float>}>  $ranked
     * @return Collection<int, array{post: ContentPost, ranking: array<string, float>}>
     */
    private function prioritizeVertical(Collection $ranked, ?string $primaryVertical): Collection
    {
        $sorted = $ranked->sortByDesc('ranking.score')->values();

        if ($primaryVertical === null) {
            return $sorted;
        }

        return $sorted
            ->filter(fn (array $item): bool => $item['post']->creator->niche === $primaryVertical)
            ->concat($sorted->reject(fn (array $item): bool => $item['post']->creator->niche === $primaryVertical))
            ->values();
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
