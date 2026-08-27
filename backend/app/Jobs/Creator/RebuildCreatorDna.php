<?php

namespace App\Jobs\Creator;

use App\Jobs\Discovery\DiscoverNicheContent;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Services\Creator\CreatorProfileDnaWriter;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Instagram\NicheDetectionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rewrites a member's Creator DNA from everything Personal now holds on them:
 * their bio, their link, their captions and the spoken scripts of their reels.
 * Runs at the end of the enrichment chain, so it reads whichever transcriptions
 * made it through and never waits on the ones that did not.
 */
class RebuildCreatorDna implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** The window of own posts the DNA is read from. */
    public const POSTS_READ = 30;

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $userId,
    ) {
        $this->onQueue('analysis');
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(
        NicheDetectionService $niche,
        CanonicalCreatorVerticals $verticals,
        CreatorProfileDnaWriter $dnaWriter,
    ): void {
        $profile = CreatorProfile::query()->where('user_id', $this->userId)->first();
        $creator = Creator::query()->where('user_id', $this->userId)->first();

        if (! $profile || ! $creator) {
            return;
        }

        $media = ContentPost::query()
            ->where('creator_id', $creator->id)
            ->orderByDesc('published_at')
            ->limit(self::POSTS_READ)
            ->get(['caption', 'transcript'])
            ->map(fn (ContentPost $post): array => [
                'caption' => $post->caption,
                'transcript' => $post->transcript,
            ])
            ->all();

        if ($media === []) {
            return;
        }

        $signals = $niche->detect($this->account($creator), $media);

        // A failed model call falls back to word frequency, which is thinner than
        // the DNA onboarding already produced. Keeping the better one is the point
        // of running this pass at all.
        if ($signals['analysis_method'] !== 'llm'
            && data_get($profile->creator_dna, 'analysis_method') === 'llm') {
            return;
        }

        $before = $profile->niche;

        $dnaWriter->apply($profile, $signals, $verticals->fromSignals([
            $signals['primary_niche'],
            ...$signals['sub_niches'],
            ...$signals['topics'],
        ]));
        $profile->save();

        // Hearing the creator can move the niche. The feed is built from it, so it
        // is refilled rather than left pointing at the caption-only reading.
        if ($profile->niche && $profile->niche !== $before) {
            DiscoverNicheContent::dispatch($this->userId);
        }
    }

    /**
     * The niche reader takes an account for the name, bio, link and category. The
     * connected one when there is one, otherwise the scraped creator row stands in
     * for it and is never saved.
     */
    private function account(Creator $creator): InstagramAccount
    {
        return InstagramAccount::query()->where('user_id', $this->userId)->first()
            ?? new InstagramAccount([
                'username' => $creator->username,
                'display_name' => $creator->display_name,
                'bio' => $creator->bio,
                'website' => data_get($creator->metadata, 'external_url')
                    ?? data_get($creator->metadata, 'website'),
                'category' => data_get($creator->metadata, 'category')
                    ?? data_get($creator->metadata, 'business_category'),
                'account_type' => data_get($creator->metadata, 'account_type'),
            ]);
    }
}
