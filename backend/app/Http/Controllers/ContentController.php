<?php

namespace App\Http\Controllers;

use App\Models\ContentPost;
use App\Models\DismissedContent;
use App\Models\LifeMoment;
use App\Models\Remix;
use App\Models\SavedContent;
use App\Services\ContentGenerationService;
use App\Services\ContentPostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function show(Request $request, ContentPost $content, ContentPostView $view): JsonResponse
    {
        return response()->json(['content' => $view->make($content, $request->user())]);
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

    public function remix(Request $request, ContentPost $content, ContentGenerationService $generator): JsonResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:reel,carousel,caption'],
            'life_moment_id' => ['nullable', 'integer'],
        ]);
        $moment = isset($data['life_moment_id'])
            ? LifeMoment::query()->where('user_id', $request->user()->id)->findOrFail($data['life_moment_id'])
            : $request->user()->moments()->orderByDesc('story_score')->first();

        $remix = Remix::query()->create([
            'user_id' => $request->user()->id,
            'source_content_id' => $content->id,
            'life_moment_id' => $moment?->id,
            'format' => $data['format'],
            'generated_content' => $generator->generate($content, $request->user(), $data['format'], $moment),
            'status' => 'draft',
        ]);

        return response()->json(['remix' => $remix], 201);
    }
}
