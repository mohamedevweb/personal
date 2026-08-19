<?php

namespace App\Jobs;

use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramAuthService;
use App\Services\Instagram\NicheDetectionService;
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

    public function handle(InstagramAuthService $auth, InstagramApiService $api, NicheDetectionService $niche): void
    {
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

            CreatorProfile::query()->updateOrCreate(
                ['user_id' => $account->user_id],
                [
                    'instagram_username' => $account->username,
                    'display_name' => $account->display_name,
                    'bio' => $account->bio,
                    'niche' => $signals['niche'],
                    'positioning' => $account->bio,
                    'topics' => $signals['topics'],
                    'tone' => $signals['tone'],
                ],
            );

            $account->update(['sync_status' => 'finding_patterns']);

            // The niche is known now, so fill the feed with matching creators. It
            // runs as its own job so a scraper hiccup never fails the sync.
            DiscoverNicheContent::dispatch($account->user_id);

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
}
