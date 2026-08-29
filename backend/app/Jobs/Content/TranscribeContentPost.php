<?php

namespace App\Jobs\Content;

use App\Models\ContentPost;
use App\Services\Discovery\ContentPostMediaRefresh;
use App\Services\Discovery\ReelVideoFetcher;
use App\Services\Llm\AudioTranscriptionService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reads the spoken script of one reel. Never throws: a batch that transcribes
 * several reels must keep going when one video has expired, and an analysis that
 * follows it must still run on the caption alone.
 */
class TranscribeContentPost implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public const DONE = 'done';

    /** The post has nothing to transcribe: not a reel, no speech, video gone. */
    public const UNAVAILABLE = 'unavailable';

    /** The transcription call itself failed. Worth another attempt later. */
    public const FAILED = 'failed';

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $contentPostId,
    ) {
        $this->onQueue('analysis');
    }

    public function uniqueId(): string
    {
        return (string) $this->contentPostId;
    }

    public function handle(
        ReelVideoFetcher $videos,
        AudioTranscriptionService $transcription,
        ContentPostMediaRefresh $media,
    ): void {
        if (! config('services.transcription.enabled')) {
            return;
        }

        $post = ContentPost::query()->find($this->contentPostId);

        if (! $post || $post->transcript_status === self::DONE) {
            return;
        }

        if (mb_strtolower((string) $post->format) !== 'reel') {
            $this->store($post, null, self::UNAVAILABLE, 0);

            return;
        }

        // The url captured at discovery is signed and expires long before a
        // member opens the post, so the playable one is fetched here.
        if (! $media->ensure($post)) {
            $this->store($post, null, self::UNAVAILABLE, 0);

            return;
        }

        $startedAt = hrtime(true);
        $post->forceFill(['transcription_started_at' => now()])->save();
        $reached = false;
        $transcript = $videos->withVideo(
            $post->video_url,
            $this->duration($post),
            function (string $path) use ($transcription, &$reached): ?string {
                $reached = true;

                return $transcription->transcribe($path);
            },
        );

        $this->store($post, $transcript, match (true) {
            is_string($transcript) && $transcript !== '' => self::DONE,
            // The video was read and the model answered nothing, or was never
            // reachable at all. Neither is an error to retry.
            $transcript === '' || ! $reached => self::UNAVAILABLE,
            default => self::FAILED,
        }, $this->elapsedMilliseconds($startedAt));
    }

    private function store(ContentPost $post, ?string $transcript, string $status, int $durationMs): void
    {
        $post->forceFill([
            'transcript' => $status === self::DONE ? $transcript : $post->transcript,
            'transcript_status' => $status,
            'transcribed_at' => $status === self::DONE ? now() : $post->transcribed_at,
            'transcription_duration_ms' => $durationMs,
        ])->save();
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    /** Known before the download, so an over-long reel never costs bandwidth. */
    private function duration(ContentPost $post): ?int
    {
        $duration = data_get($post->metadata, 'video_duration');

        return is_numeric($duration) ? (int) round((float) $duration) : null;
    }

    public function failed(?Throwable $exception): void
    {
        ContentPost::query()
            ->whereKey($this->contentPostId)
            ->update(['transcript_status' => self::FAILED]);
    }
}
