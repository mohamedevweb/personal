<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Jobs\Content\AnalyzeCarouselContentPost;
use App\Jobs\Content\AnalyzeContentPost;
use App\Jobs\Content\TranscribeContentPost;
use App\Models\ContentPost;
use App\Models\DismissedContent;
use App\Models\SavedContent;
use App\Services\Content\RemixDraftService;
use App\Services\Discovery\PostInsightService;
use App\Services\View\ContentPostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\HttpFoundation\Response;

class ContentController extends Controller
{
    public function show(Request $request, ContentPost $content, ContentPostView $view, PostInsightService $insights): JsonResponse
    {
        // Render a useful heuristic immediately. The richer model analysis is
        // requested separately so opening a post never waits on a provider.
        $analyzed = $insights->present($content);

        return response()->json([
            'content' => [
                ...$view->make($content, $request->user()),
                'analysis_status' => $analyzed ? 'complete' : 'pending',
            ],
        ]);
    }

    /**
     * Reading the post itself — the spoken script of a reel, the text on the
     * slides of a carousel — happens here, on first open, and the result is
     * cached on the post for every member after. The written analysis runs last
     * because it has to see that material, and it runs even when the media step
     * failed: a post analyzed from its caption alone is the behaviour we had.
     */
    public function analyze(ContentPost $content, PostInsightService $insights): JsonResponse
    {
        $analysis = new AnalyzeContentPost($content->id, app()->getLocale());
        $media = $this->mediaJobs($content);

        if ($media === []) {
            if (! $insights->isAnalyzed($content)) {
                AnalyzeContentPost::dispatch($content->id, app()->getLocale());
            }

            return response()->json([], Response::HTTP_ACCEPTED);
        }

        Bus::chain([...$media, $analysis])
            ->catch(fn () => AnalyzeContentPost::dispatch($content->id, app()->getLocale()))
            ->dispatch();

        return response()->json([], Response::HTTP_ACCEPTED);
    }

    /**
     * A post is read once. Beyond a finished reading, a post already declared
     * unreadable after a media refetch is left alone too: retrying it on every
     * open would spend a provider credit a day on media that is simply gone.
     *
     * @return list<object>
     */
    private function mediaJobs(ContentPost $content): array
    {
        if ($content->media_refreshed_at !== null) {
            $exhausted = match (mb_strtolower((string) $content->format)) {
                'reel' => $content->transcript_status === TranscribeContentPost::UNAVAILABLE,
                'carousel' => $content->carousel_analysis_status === AnalyzeCarouselContentPost::UNAVAILABLE,
                default => false,
            };

            if ($exhausted) {
                return [];
            }
        }

        return match (mb_strtolower((string) $content->format)) {
            'reel' => $content->transcript_status === TranscribeContentPost::DONE
                ? []
                : [new TranscribeContentPost($content->id)],
            'carousel' => $content->carousel_analysis_status === AnalyzeCarouselContentPost::DONE
                ? []
                : [new AnalyzeCarouselContentPost($content->id)],
            default => [],
        };
    }

    public function save(Request $request, ContentPost $content): JsonResponse
    {
        $saved = SavedContent::query()->where([
            'user_id' => $request->user()->id,
            'content_post_id' => $content->id,
        ])->first();

        if ($saved) {
            $saved->delete();
        } else {
            SavedContent::query()->create([
                'user_id' => $request->user()->id,
                'content_post_id' => $content->id,
            ]);
        }

        return response()->json(['saved' => ! $saved]);
    }

    public function dismiss(Request $request, ContentPost $content): JsonResponse
    {
        DismissedContent::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'content_post_id' => $content->id,
        ]);

        return response()->json(['dismissed' => true]);
    }

    public function remix(
        Request $request,
        ContentPost $content,
        RemixDraftService $drafts,
    ): JsonResponse {
        $data = $request->validate([
            'format' => ['required', 'in:reel,carousel,caption'],
            'life_moment_id' => ['required', 'integer'],
        ]);
        $moment = $request->user()->moments()->findOrFail($data['life_moment_id']);
        $remix = $drafts->start($content, $request->user(), $data['format'], $moment, app()->getLocale());

        return response()->json(['remix' => $remix], Response::HTTP_ACCEPTED);
    }
}
