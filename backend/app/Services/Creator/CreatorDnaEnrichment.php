<?php

namespace App\Services\Creator;

use App\Jobs\Content\TranscribeContentPost;
use App\Jobs\Creator\RebuildCreatorDna;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use Illuminate\Support\Facades\Bus;

/**
 * Onboarding stays fast: the DNA a creator sees at the end of the loader is read
 * from their bio, their link and their captions. This is the second pass that
 * runs behind them, transcribes a representative sample of their own reels and
 * rewrites the DNA from what they actually say out loud.
 */
class CreatorDnaEnrichment
{
    public function __construct(
        private readonly CreatorReelSelection $selection,
    ) {}

    public function queue(CreatorProfile $profile): bool
    {
        // A DNA the creator wrote themselves is never overwritten, so there is
        // nothing to spend a transcription on either.
        if (data_get($profile->creator_dna, 'analysis_method') === 'manual') {
            return false;
        }

        $creator = Creator::query()->where('user_id', $profile->user_id)->first();

        if (! $creator) {
            return false;
        }

        $transcriptions = config('services.transcription.enabled')
            ? $this->selection->representative($creator)
                ->reject(fn (ContentPost $post): bool => $post->transcript_status === TranscribeContentPost::DONE)
                ->map(fn (ContentPost $post): TranscribeContentPost => new TranscribeContentPost($post->id))
                ->all()
            : [];

        // Keep the public status running until the transcript-backed DNA lands.
        // Otherwise the UI can render a failed caption-only pass while this
        // successful second pass is still working behind it.
        $profile->forceFill([
            'analysis_status' => 'transcribing_reels',
            'analysis_error' => null,
        ])->save();

        // A chain, not a batch: the rebuild must read every script that made it,
        // and each transcription is harmless on its own if it does not.
        Bus::chain([...$transcriptions, new RebuildCreatorDna($profile->user_id)])->dispatch();

        return true;
    }
}
