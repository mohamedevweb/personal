<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\SavedContent;
use App\Services\View\ContentPostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedContentController extends Controller
{
    public function __invoke(Request $request, ContentPostView $view): JsonResponse
    {
        $saved = $request->user()->savedContent()
            ->with('contentPost.creator')
            ->latest()
            ->get()
            ->filter(fn (SavedContent $row) => $row->contentPost !== null);

        return response()->json([
            'items' => $saved
                ->map(fn (SavedContent $row) => $view->make($row->contentPost, $request->user(), isSaved: true))
                ->values(),
        ]);
    }
}
