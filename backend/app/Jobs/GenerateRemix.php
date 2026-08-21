<?php

namespace App\Jobs;

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
    ) {}

    public function handle(ContentGenerationService $generator, PostInsightService $insights): void
    {
        app()->setLocale($this->locale);

        $remix = Remix::query()->with(['sourceContent.creator', 'lifeMoment', 'user.creatorProfile'])
            ->findOrFail($this->remixId);
        $insights->present($remix->sourceContent);

        $remix->update([
            'generated_content' => $generator->generate(
                $remix->sourceContent,
                $remix->user,
                $remix->format,
                $remix->lifeMoment,
            ),
            'status' => 'draft',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Remix::query()->whereKey($this->remixId)->update(['status' => 'failed']);
    }
}
