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

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

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
        Remix::query()->whereKey($this->remixId)->update(['status' => 'failed']);
    }
}
