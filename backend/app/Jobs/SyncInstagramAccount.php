<?php

namespace App\Jobs;

use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramAuthService;
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

    public function handle(InstagramAuthService $auth, InstagramApiService $api): void
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
            $signals = $this->profileSignals($account, $media);

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

    /** @param list<array<string, mixed>> $media
     * @return array{niche: ?string, topics: list<string>, tone: list<string>}
     */
    private function profileSignals(InstagramAccount $account, array $media): array
    {
        $captions = collect($media)->pluck('caption')->filter()->implode(' ');
        $source = Str::lower(trim(($account->bio ?? '').' '.$captions));
        $stopWords = ['about', 'after', 'again', 'also', 'been', 'from', 'have', 'here', 'into', 'just', 'more', 'that', 'their', 'there', 'these', 'they', 'this', 'what', 'when', 'where', 'which', 'with', 'your', 'youre'];

        preg_match_all('/[\pL\pN]{4,}/u', $source, $matches);
        $topics = collect($matches[0] ?? [])
            ->reject(fn (string $word) => in_array($word, $stopWords, true))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(5)
            ->map(fn (string $word) => Str::headline($word))
            ->values()
            ->all();

        $tone = [];
        if (preg_match('/\b(i|my|me|we|our)\b/i', $captions)) {
            $tone[] = 'Personal';
        }
        if (preg_match('/\b(how|why|lesson|learn|steps|tips)\b/i', $captions)) {
            $tone[] = 'Educational';
        }
        if ($captions !== '') {
            $tone[] = Str::length($captions) / max(count($media), 1) < 350 ? 'Concise' : 'Story-driven';
        }

        return [
            'niche' => $topics === [] ? null : implode(' / ', array_slice($topics, 0, 2)),
            'topics' => $topics,
            'tone' => array_values(array_unique($tone)),
        ];
    }
}
