<?php

namespace App\Http\Controllers;

use App\Models\Remix;
use App\Services\RemixDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemixController extends Controller
{
    public function show(Request $request, Remix $remix, RemixDraftService $drafts): JsonResponse
    {
        $this->ensureOwner($request, $remix);
        $remix = $drafts->failIfStale($remix);

        return response()->json([
            'remix' => $remix->load(['sourceContent.creator', 'lifeMoment']),
        ]);
    }

    public function update(Request $request, Remix $remix): JsonResponse
    {
        $this->ensureOwner($request, $remix);
        $data = $request->validate([
            'generated_content' => ['sometimes', 'array'],
            'status' => ['sometimes', 'in:draft,ready,archived'],
            'format' => ['sometimes', 'in:reel,carousel,caption'],
        ]);
        $remix->update($data);

        return response()->json(['remix' => $remix->fresh()]);
    }

    public function retry(Request $request, Remix $remix, RemixDraftService $drafts): JsonResponse
    {
        $this->ensureOwner($request, $remix);
        abort_unless($remix->status === 'failed', Response::HTTP_CONFLICT);

        return response()->json([
            'remix' => $drafts->retry($remix, app()->getLocale()),
        ], Response::HTTP_ACCEPTED);
    }

    private function ensureOwner(Request $request, Remix $remix): void
    {
        abort_unless($remix->user_id === $request->user()->id, 404);
    }
}
