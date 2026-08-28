<?php

namespace App\Jobs\Creator;

use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Services\Creator\CreatorDnaEnrichment;
use App\Services\Creator\RegisteredCreatorService;
use App\Services\Discovery\InstagramDataProvider;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Imports the larger media window after the initial Creator DNA is usable. */
class CompleteCreatorDnaMediaImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const POSTS_READ = 30;

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [30, 180];

    public function __construct(
        public readonly int $userId,
        public readonly string $username,
    ) {
        $this->onQueue('analysis');
    }

    public function uniqueId(): string
    {
        return $this->userId.':'.mb_strtolower($this->username);
    }

    public function handle(
        InstagramDataProvider $instagram,
        RegisteredCreatorService $creators,
        CreatorDnaEnrichment $enrichment,
    ): void {
        $profile = CreatorProfile::query()->where('user_id', $this->userId)->first();
        $creator = Creator::query()->where('user_id', $this->userId)->first();

        if (! $profile || ! $creator || ! $this->isCurrent($profile)) {
            return;
        }

        if (data_get($profile->creator_dna, 'analysis_method') === 'manual') {
            $profile->forceFill(['media_enrichment_status' => 'idle'])->save();

            return;
        }

        $profile->forceFill([
            'media_enrichment_status' => 'importing_media',
            'media_enrichment_error' => null,
            'media_enrichment_started_at' => now(),
            'media_enrichment_completed_at' => null,
        ])->save();

        $posts = $instagram->getPosts($this->username, self::POSTS_READ);

        if (! $this->isCurrent($profile)) {
            return;
        }

        if ($posts->isNotEmpty()) {
            $creators->storePosts($creator, $posts);
        }

        $enrichment->queue($profile->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        CreatorProfile::query()
            ->where('user_id', $this->userId)
            ->where('instagram_username', $this->username)
            ->update([
                'media_enrichment_status' => 'failed',
                'media_enrichment_error' => 'media_import_unavailable',
                'media_enrichment_completed_at' => now(),
            ]);
    }

    private function isCurrent(CreatorProfile $profile): bool
    {
        return strcasecmp((string) $profile->fresh()?->instagram_username, $this->username) === 0;
    }
}
