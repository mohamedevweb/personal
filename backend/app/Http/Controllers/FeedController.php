<?php

namespace App\Http\Controllers;

use App\Jobs\DiscoverNicheContent;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class FeedController extends Controller
{
    public function index(Request $request, RecommendationService $recommendations): JsonResponse
    {
        $items = $recommendations->forUser($request->user());

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

    /**
     * Queue a fresh discovery run for the creator's niche. The feed keeps serving
     * whatever it already has while the new posts are scraped in the background.
     */
    public function refresh(Request $request): JsonResponse
    {
        DiscoverNicheContent::dispatch($request->user()->id);

        return response()->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
