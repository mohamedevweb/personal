<?php

namespace App\Services\Creator;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use Illuminate\Support\Collection;

/**
 * Chooses a bounded mix of strong, ordinary and recent carousels for the DNA.
 * Repetition across that mix is more representative than reading only hits.
 */
class CreatorCarouselSelection
{
    private const WINDOW = 30;

    /** @return Collection<int, ContentPost> */
    public function representative(Creator $creator, ?int $limit = null): Collection
    {
        $limit = max(1, $limit ?? (int) config('services.carousel_analysis.creator_dna_carousels'));
        $carousels = ContentPost::query()
            ->where('creator_id', $creator->id)
            ->where('format', 'carousel')
            ->whereNotNull('media_urls')
            ->where('safety_status', '!=', ContentSafetyDecision::BLOCKED)
            ->orderByDesc('published_at')
            ->limit(self::WINDOW)
            ->get()
            ->filter(fn (ContentPost $post): bool => $post->media_urls !== [])
            ->values();

        if ($carousels->count() <= $limit) {
            return $carousels;
        }

        $share = (int) ceil($limit / 3);
        $byPerformance = $carousels->sortByDesc('performance_ratio')->values();
        $middle = max(0, intdiv($byPerformance->count(), 2) - intdiv($share, 2));

        return $byPerformance->take($share)
            ->concat($byPerformance->slice($middle, $share))
            ->concat($carousels->take($share))
            ->concat($carousels)
            ->unique('id')
            ->take($limit)
            ->values();
    }
}
