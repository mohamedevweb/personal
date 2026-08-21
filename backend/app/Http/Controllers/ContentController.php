<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeContentPost;
use App\Models\ContentPost;
use App\Models\DismissedContent;
use App\Models\LifeMoment;
use App\Models\SavedContent;
use App\Services\ContentPostView;
use App\Services\Discovery\PostInsightService;
use App\Services\RemixDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function analyze(ContentPost $content, PostInsightService $insights): JsonResponse
    {
        if (! $insights->isAnalyzed($content)) {
            AnalyzeContentPost::dispatch($content->id, app()->getLocale());
        }

        return response()->json([], Response::HTTP_ACCEPTED);
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
            'life_moment_id' => ['nullable', 'integer'],
        ]);
        $moment = isset($data['life_moment_id'])
            ? LifeMoment::query()->where('user_id', $request->user()->id)->findOrFail($data['life_moment_id'])
            : $request->user()->moments()->orderByDesc('story_score')->first();
        $remix = $drafts->start($content, $request->user(), $data['format'], $moment, app()->getLocale());

        return response()->json(['remix' => $remix], Response::HTTP_ACCEPTED);
    }
}
