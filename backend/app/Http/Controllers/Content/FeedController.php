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
        // The feed scrolls by exclusion rather than by offset, so the client sends
        // back everything already on screen. The cap bounds the bind parameters on
        // the pool query; past it the client drops its oldest ids.
        $data = $request->validate([
            'exclude' => ['sometimes', 'array', 'max:500'],
            'exclude.*' => ['integer', 'distinct'],
        ]);
        $sections = $recommendations->sectionsForUser($request->user(), excludeIds: $data['exclude'] ?? []);

        return $this->response($request, $sections['items'], $sections['explore_items']);
    }

    public function global(Request $request, RecommendationService $recommendations): JsonResponse
    {
        $items = $recommendations->globalForUser($request->user());

        return $this->response($request, $items);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, array<string, mixed>>  $exploreItems
     */
    private function response(Request $request, Collection $items, ?Collection $exploreItems = null): JsonResponse
    {
        $profile = $request->user()->creatorProfile;

        return response()->json([
            'opportunity_count' => $items->count(),
            'has_more' => $items->count() >= (int) config('services.discovery.feed_size'),
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
            'explore_items' => $exploreItems ?? collect(),
        ]);
    }
}
