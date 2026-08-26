<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'opportunities' => $request->user()->opportunities()
                ->with(['contentPost.creator', 'lifeMoment'])
                ->orderByDesc('relevance_score')
                ->get(),
        ]);
    }
}
