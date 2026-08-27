<?php

namespace App\Services\Creator;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use Illuminate\Support\Collection;

/**
 * Chooses which of a creator's own reels are worth transcribing.
 *
 * Not the most recent ones, and not the best performing ones: both answer a
 * different question. A voice is what repeats, so the sample deliberately mixes
 * the reels that worked, the ordinary ones and the latest ones.
 */
class CreatorReelSelection
{
    /** The window of recent posts the sample is drawn from. */
    private const WINDOW = 30;

    /** @return Collection<int, ContentPost> */
    public function representative(Creator $creator, ?int $limit = null): Collection
    {
        $limit = max(1, $limit ?? (int) config('services.transcription.creator_dna_reels'));
        $reels = ContentPost::query()
            ->where('creator_id', $creator->id)
            ->where('format', 'reel')
            ->whereNotNull('video_url')
            ->where('safety_status', '!=', ContentSafetyDecision::BLOCKED)
            ->orderByDesc('published_at')
            ->limit(self::WINDOW)
            ->get();

        if ($reels->count() <= $limit) {
            return $reels->values();
        }

        $share = (int) ceil($limit / 3);
        $byPerformance = $reels->sortByDesc('performance_ratio')->values();
        $middle = max(0, intdiv($byPerformance->count(), 2) - intdiv($share, 2));

        return $byPerformance->take($share)
            ->concat($byPerformance->slice($middle, $share))
            ->concat($reels->take($share))
            // The three bands overlap on a small grid. Whatever the overlap costs
            // is given back from the recent end, so the sample is always full.
            ->concat($reels)
            ->unique('id')
            ->take($limit)
            ->values();
    }
}
