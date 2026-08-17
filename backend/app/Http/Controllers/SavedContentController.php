<?php

namespace App\Http\Controllers;

use App\Models\ContentPost;
use App\Services\ContentPostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedContentController extends Controller
{
    public function __invoke(Request $request, ContentPostView $view): JsonResponse
    {
        $ids = $request->user()->savedContent()->latest()->pluck('content_post_id');
        $posts = ContentPost::query()->with('creator')->whereIn('id', $ids)->get()
            ->map(fn (ContentPost $post) => $view->make($post, $request->user()));

        return response()->json(['items' => $posts]);
    }
}
