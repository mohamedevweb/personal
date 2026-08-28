<?php

namespace App\Jobs\Content;

use App\Models\ContentPost;
use App\Services\Instagram\ContentMedia;
use App\Services\Llm\CarouselVisualAnalysisService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Reads every available slide of one carousel without holding up the chain. */
class AnalyzeCarouselContentPost implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const DONE = 'done';

    public const UNAVAILABLE = 'unavailable';

    public const FAILED = 'failed';

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $contentPostId)
    {
        $this->onQueue('analysis');
    }

    public function uniqueId(): string
    {
        return (string) $this->contentPostId;
    }

    public function handle(CarouselVisualAnalysisService $analysis): void
    {
        if (! config('services.carousel_analysis.enabled')) {
            return;
        }

        $post = ContentPost::query()->find($this->contentPostId);

        if (! $post || ($post->carousel_analysis_status === self::DONE
            && (int) data_get($post->carousel_analysis, 'analysis_version') >= CarouselVisualAnalysisService::ANALYSIS_VERSION)) {
            return;
        }

        if ($post->format !== 'carousel' || ContentMedia::frames($post) === [] || ! config('services.openai.api_key')) {
            $this->store($post, null, self::UNAVAILABLE);

            return;
        }

        $result = $analysis->analyze($post);
        $this->store($post, $result, $result === null ? self::FAILED : self::DONE);
    }

    /** @param array<string, mixed>|null $analysis */
    private function store(ContentPost $post, ?array $analysis, string $status): void
    {
        $post->forceFill([
            'carousel_analysis' => $status === self::DONE ? $analysis : $post->carousel_analysis,
            'carousel_analysis_status' => $status,
            'carousel_analyzed_at' => $status === self::DONE ? now() : $post->carousel_analyzed_at,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        ContentPost::query()
            ->whereKey($this->contentPostId)
            ->update(['carousel_analysis_status' => self::FAILED]);
    }
}
