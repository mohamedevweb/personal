<?php

namespace App\Services\Discovery;

use App\Services\Instagram\InstagramMediaProxy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls the video file of a reel down to a temporary path so it can be sent to a
 * transcription model. Nothing is kept: the script is what has value, the file is
 * a few megabytes we never want to store or serve.
 */
class ReelVideoFetcher
{
    private const CHUNK_BYTES = 262_144;

    public function __construct(
        private readonly InstagramMediaProxy $proxy,
    ) {}

    /**
     * Downloads the reel and hands the local path to the callback. The file is
     * always deleted afterwards, including when the callback throws.
     *
     * Returns null when the video cannot legitimately be read: a host outside the
     * Instagram CDN, an expired signed URL, a file over the caps. Those are
     * ordinary outcomes here, not errors, so nothing is thrown.
     *
     * @template TReturn
     *
     * @param  callable(string): TReturn  $callback
     * @return TReturn|null
     */
    public function withVideo(string $url, ?int $durationSeconds, callable $callback): mixed
    {
        if (! $this->proxy->supports($url) || $this->tooLong($durationSeconds)) {
            return null;
        }

        $path = $this->download($url);

        if ($path === null) {
            return null;
        }

        try {
            return $callback($path);
        } finally {
            @unlink($path);
        }
    }

    private function tooLong(?int $durationSeconds): bool
    {
        $max = max(1, (int) config('services.transcription.max_duration_seconds'));

        return $durationSeconds !== null && $durationSeconds > $max;
    }

    /** The local path of the downloaded file, or null when it could not be read. */
    private function download(string $url): ?string
    {
        $maxBytes = max(1, (int) config('services.transcription.max_bytes'));
        // Whisper reads the container, so the extension has to survive the trip.
        $path = sys_get_temp_dir().'/reel-'.Str::random(24).'.mp4';

        try {
            $response = Http::withHeaders([
                'Accept' => 'video/mp4,video/*',
                'User-Agent' => 'PersonalMediaProxy/1.0',
            ])
                ->timeout((int) config('services.transcription.download_timeout'))
                ->withOptions(['allow_redirects' => false, 'stream' => true])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $declared = filter_var($response->header('Content-Length'), FILTER_VALIDATE_INT);

            if ($declared !== false && $declared > $maxBytes) {
                return null;
            }

            $body = $response->toPsrResponse()->getBody();
            $handle = fopen($path, 'wb');

            if ($handle === false) {
                return null;
            }

            $written = 0;

            while (! $body->eof()) {
                $chunk = $body->read(self::CHUNK_BYTES);

                if ($chunk === '') {
                    break;
                }

                $written += strlen($chunk);

                if ($written > $maxBytes) {
                    fclose($handle);
                    @unlink($path);

                    return null;
                }

                fwrite($handle, $chunk);
            }

            fclose($handle);

            return $written > 0 ? $path : $this->discard($path);
        } catch (Throwable) {
            return $this->discard($path);
        }
    }

    private function discard(string $path): ?string
    {
        @unlink($path);

        return null;
    }
}
