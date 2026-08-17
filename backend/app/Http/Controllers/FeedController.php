<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __invoke(Request $request, RecommendationService $recommendations): JsonResponse
    {
        $items = $recommendations->forUser($request->user());

        return response()->json([
            'greeting_name' => str($request->user()->name)->before(' ')->toString(),
            'opportunity_count' => $items->count(),
            'featured_opportunity' => $request->user()->opportunities()
                ->with(['contentPost.creator', 'lifeMoment'])
                ->orderByDesc('relevance_score')
                ->first(),
            'items' => $items,
        ]);
    }
}
