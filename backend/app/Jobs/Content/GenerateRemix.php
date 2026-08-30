<?php

namespace App\Jobs\Content;

use App\Exceptions\ContentGenerationException;
use App\Models\ContentPost;
use App\Models\Remix;
use App\Services\Content\ContentGenerationService;
use App\Services\Discovery\ContentPostMediaRefresh;
use App\Services\Discovery\PostInsightService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

class GenerateRemix implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // The provider call is only the last wait: a carousel whose slides have
    // never been read is read here first, and that job allows itself 180s.
    public int $timeout = 330;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $remixId,
        public readonly string $locale,
    ) {
        $this->onQueue('remix');
    }

    public function handle(
        ContentGenerationService $generator,
        PostInsightService $insights,
        ContentPostMediaRefresh $media,
    ): void {
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

        if ($remix->format === 'carousel') {
            $this->readSource($remix->sourceContent, $media);
            // Reading the source is a third wait state, and it can take as long
            // as the drafting itself. Without this the poll would call the
            // draft stale while it is still being worked on.
            $remix->touch();
        }

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

    /**
     * A carousel draft is written against the slides of the source, so they
     * have to have been read. Opening the analysis page normally does it; a
     * post analyzed from its caption alone never had it done, and this is the
     * last place to notice. The reading job skips itself when it is already
     * done, and a source that stays unreadable still gets a draft.
     */
    private function readSource(ContentPost $source, ContentPostMediaRefresh $media): void
    {
        $media->ensure($source);

        if ($source->carousel_analysis_status !== AnalyzeCarouselContentPost::DONE) {
            Bus::dispatchSync(new AnalyzeCarouselContentPost($source->id));
            $source->refresh();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Remix::query()
            ->whereKey($this->remixId)
            ->where('status', 'generating')
            ->update(['status' => 'failed']);
    }
}
