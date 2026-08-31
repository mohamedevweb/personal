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

        if (in_array($remix->format, ['carousel', 'reel'], true)) {
            $this->readSource($remix->sourceContent, $media);
            // Reading the source is a third wait state, and it can take as long
            // as the drafting itself. Without this the poll would call the
            // draft stale while it is still being worked on.
            $remix->touch();
        }

        // Present the source only after its media has been read, so a direct
        // Reel remix carries the current transcript into the source context.
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

    /**
     * A Reel draft needs the source transcript and a carousel draft needs the
     * source slides. Opening the analysis page normally does this first; a post
     * remixed directly from the feed may not have been read yet, so generation
     * completes the missing source read before asking for the draft.
     */
    private function readSource(ContentPost $source, ContentPostMediaRefresh $media): void
    {
        $media->ensure($source);

        if (mb_strtolower((string) $source->format) === 'reel') {
            if ($source->transcript_status !== TranscribeContentPost::DONE) {
                Bus::dispatchSync(new TranscribeContentPost($source->id));
                $source->refresh();
            }

            return;
        }

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
