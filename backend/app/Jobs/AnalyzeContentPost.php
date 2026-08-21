<?php

namespace App\Jobs;

use App\Models\ContentPost;
use App\Services\Discovery\PostInsightService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeContentPost implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $contentPostId,
        public readonly string $locale,
    ) {}

    public function uniqueId(): string
    {
        return $this->contentPostId.':'.$this->locale;
    }

    public function handle(PostInsightService $insights): void
    {
        app()->setLocale($this->locale);

        $post = ContentPost::query()->findOrFail($this->contentPostId);
        $insights->ensureAnalyzed($post);
    }
}
