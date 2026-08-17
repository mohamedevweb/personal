<?php

namespace App\Http\Controllers;

use App\Models\Remix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemixController extends Controller
{
    public function show(Request $request, Remix $remix): JsonResponse
    {
        $this->ensureOwner($request, $remix);

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

    private function ensureOwner(Request $request, Remix $remix): void
    {
        abort_unless($remix->user_id === $request->user()->id, 404);
    }
}
