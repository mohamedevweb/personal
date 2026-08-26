<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Services\Feed\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FeedController extends Controller
{
    public function index(Request $request, RecommendationService $recommendations): JsonResponse
    {
        $data = $request->validate([
            'exclude' => ['sometimes', 'array', 'max:240'],
            'exclude.*' => ['integer', 'distinct'],
        ]);
        $items = $recommendations->forUser($request->user(), excludeIds: $data['exclude'] ?? []);

        return $this->response($request, $items);
    }

    public function global(Request $request, RecommendationService $recommendations): JsonResponse
    {
        $items = $recommendations->globalForUser($request->user());

        return $this->response($request, $items);
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function response(Request $request, Collection $items): JsonResponse
    {
        $profile = $request->user()->creatorProfile;

        return response()->json([
            'opportunity_count' => $items->count(),
            'personalization' => [
                'niche' => $profile?->niche,
                'primary_vertical' => $profile?->primary_vertical,
                'topics' => $profile?->topics ?? [],
                'tone' => $profile?->tone ?? [],
            ],
            'featured_opportunity' => $request->user()->opportunities()
                ->with(['contentPost.creator', 'lifeMoment'])
                ->orderByDesc('relevance_score')
                ->first(),
            'items' => $items,
        ]);
    }
}
