<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Log;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use Throwable;

/**
 * Reads the spoken words out of a video file. Best-effort like LlmJsonService:
 * every failure returns null so the caller degrades to captions instead of
 * breaking. The language is never forced, the followed creators are FR and EN and
 * the model detects it on its own.
 */
class AudioTranscriptionService
{
    public function __construct(
        private readonly OpenAiClient $openai,
    ) {}

    /**
     * The spoken text, an empty string when the reel carries no speech at all, and
     * null when the call itself failed. The caller needs the three apart: only the
     * last one is worth retrying.
     */
    public function transcribe(string $path): ?string
    {
        if (! config('services.openai.api_key') || ! is_readable($path)) {
            return null;
        }

        $file = fopen($path, 'r');

        if ($file === false) {
            return null;
        }

        try {
            $response = $this->openai->audio()->transcribe([
                'model' => (string) config('services.openai.transcription_model'),
                'file' => $file,
                'response_format' => 'json',
            ]);

            return trim($response->text);
        } catch (Throwable $exception) {
            Log::warning('Reel transcription failed.', ['exception' => $exception]);

            return null;
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }
    }
}
