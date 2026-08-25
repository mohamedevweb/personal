<?php

namespace App\Jobs;

use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\CreatorMarketDetector;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramAuthService;
use App\Services\Instagram\NicheDetectionService;
use App\Services\RegisteredCreatorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class SyncInstagramAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * One import is a profile call plus up to two calls per media item, each with
     * its own 15s HTTP timeout. This bounds the whole job so a slow or throttled
     * Meta response cannot hold a queue worker indefinitely.
     */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $instagramAccountId) {}

    public function handle(
        InstagramAuthService $auth,
        InstagramApiService $api,
        NicheDetectionService $niche,
        ?CreatorMarketDetector $markets = null,
        ?CanonicalCreatorVerticals $verticals = null,
        ?RegisteredCreatorService $registeredCreators = null,
    ): void {
        $markets ??= app(CreatorMarketDetector::class);
        $verticals ??= app(CanonicalCreatorVerticals::class);
        $registeredCreators ??= app(RegisteredCreatorService::class);
        $account = InstagramAccount::query()->findOrFail($this->instagramAccountId);

        try {
            $account->update(['sync_status' => 'importing_content', 'sync_error' => null]);
            $accessToken = $auth->accessToken($account);
            $profile = $api->fetchProfile($accessToken);
            $media = $api->fetchRecentMedia($accessToken);

            $account->update([
                ...$profile,
                'sync_status' => 'understanding_niche',
            ]);

            foreach ($media as $item) {
                $account->media()->updateOrCreate(
                    ['instagram_media_id' => $item['instagram_media_id']],
                    [...$item, 'synced_at' => now()],
                );
            }

            $account->update(['sync_status' => 'learning_style']);
            $signals = $niche->detect($account, $media);
            $market = $markets->detect(implode("\n", [
                $account->display_name ?? '',
                $account->bio ?? '',
                collect($media)->pluck('caption')->filter()->take(30)->implode("\n"),
            ]));
            $primaryVertical = $verticals->fromSignals([
                $signals['primary_niche'],
                ...$signals['sub_niches'],
                ...$signals['topics'],
            ]);

            $creatorProfile = CreatorProfile::query()->firstOrNew(['user_id' => $account->user_id]);
            $manualProfile = data_get($creatorProfile->creator_dna, 'analysis_method') === 'manual';
            $creatorProfile->fill([
                'instagram_username' => $account->username,
                'display_name' => $account->display_name,
                'bio' => $account->bio,
                'market' => $market['market'],
                'market_confidence' => $market['confidence'],
            ]);

            if (! $manualProfile) {
                $creatorProfile->fill([
                    'niche' => $signals['primary_niche'],
                    'positioning' => $signals['primary_niche'] ? $account->bio : null,
                    'topics' => $signals['topics'],
                    'tone' => $signals['tone'],
                    'voice_profile' => $signals['voice_profile'],
                    'audience_description' => $signals['audience'] === [] ? null : implode(', ', $signals['audience']),
                    'creator_dna' => $signals,
                    'primary_vertical' => $primaryVertical,
                    'dna_analyzed_at' => now(),
                ]);
            }

            if ($this->hasLegacyPlaceholderContext($creatorProfile)) {
                $creatorProfile->fill([
                    'current_projects' => [],
                    'goals' => [],
                    'content_strengths' => [],
                ]);
            }

            $creatorProfile->save();
            $registeredCreators->sync($account->fresh('media'), $creatorProfile);

            $account->update(['sync_status' => 'finding_patterns']);

            // The niche is known now, so fill the feed with matching creators. It
            // runs as its own job so a scraper hiccup never fails the sync.
            if ($creatorProfile->niche) {
                DiscoverNicheContent::dispatch($account->user_id);
            }

            $account->update([
                'sync_status' => 'completed',
                'last_synced_at' => now(),
                'sync_error' => null,
            ]);
        } catch (Throwable $exception) {
            $account->update([
                'sync_status' => 'failed',
                'sync_error' => Str::limit($exception->getMessage(), 500),
            ]);

            throw $exception;
        }
    }

    private function hasLegacyPlaceholderContext(CreatorProfile $profile): bool
    {
        return $profile->current_projects === ['Personal']
            && $profile->goals === ['Build a personal brand', 'Grow an audience', 'Launch Personal']
            && $profile->content_strengths === ['Founder stories', 'Personal lessons', 'Behind the scenes'];
    }
}
