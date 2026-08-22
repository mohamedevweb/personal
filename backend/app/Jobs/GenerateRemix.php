<?php

namespace App\Jobs;

use App\Exceptions\ContentGenerationException;
use App\Models\Remix;
use App\Services\ContentGenerationService;
use App\Services\Discovery\PostInsightService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateRemix implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $remixId,
        public readonly string $locale,
    ) {
        $this->onQueue('interactive');
    }

    public function handle(ContentGenerationService $generator, PostInsightService $insights): void
    {
        app()->setLocale($this->locale);

        $remix = Remix::query()->with(['sourceContent.creator', 'lifeMoment', 'user.creatorProfile'])
            ->findOrFail($this->remixId);

        if ($remix->status !== 'generating') {
            return;
        }

        // Refresh the stale-generation clock once the worker actually starts.
        // A queued remix and a provider request are two distinct wait states.
        $remix->touch();
        $insights->present($remix->sourceContent);

        try {
            $generatedContent = $generator->generate(
                $remix->sourceContent,
                $remix->user,
                $remix->format,
                $remix->lifeMoment,
            );
        } catch (ContentGenerationException) {
            // Provider failures are already normalized and logged. Surface the
            // retry action promptly instead of making the creator wait through
            // queue retries that are unlikely to change the response.
            $remix->update(['status' => 'failed']);

            return;
        }

        $remix->update([
            'generated_content' => $generatedContent,
            'status' => 'draft',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Remix::query()
            ->whereKey($this->remixId)
            ->where('status', 'generating')
            ->update(['status' => 'failed']);
    }
}
