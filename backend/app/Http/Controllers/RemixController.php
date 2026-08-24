<?php

namespace App\Http\Controllers;

use App\Models\Remix;
use App\Services\ContentPostView;
use App\Services\RemixBlockService;
use App\Services\RemixDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemixController extends Controller
{
    public function index(Request $request, RemixDraftService $drafts): JsonResponse
    {
        $remixes = $request->user()->remixes()
            ->where('status', '!=', 'archived')
            ->with(['sourceContent.creator'])
            ->latest('updated_at')
            ->get()
            ->map(function (Remix $remix) use ($drafts): array {
                $remix = $drafts->failIfStale($remix);

                return [
                    'id' => $remix->id,
                    'format' => $remix->format,
                    'generated_content' => $remix->generated_content,
                    'status' => $remix->status,
                    'updated_at' => $remix->updated_at,
                    'source_content' => [
                        'id' => $remix->sourceContent->id,
                        'hook' => $remix->sourceContent->hook,
                        'creator' => [
                            'username' => $remix->sourceContent->creator->username,
                        ],
                    ],
                ];
            });

        return response()->json(['remixes' => $remixes]);
    }

    public function show(
        Request $request,
        Remix $remix,
        RemixDraftService $drafts,
        ContentPostView $contentView,
    ): JsonResponse {
        $this->ensureOwner($request, $remix);
        $remix = $drafts->failIfStale($remix);
        $remix->load(['sourceContent.creator', 'lifeMoment']);
        $payload = $remix->toArray();
        $payload['source_content'] = $contentView->make($remix->sourceContent, $request->user());

        return response()->json([
            'remix' => $payload,
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

    public function copied(Request $request, Remix $remix): Response
    {
        $this->ensureOwner($request, $remix);
        $remix->increment('copy_count', 1, ['last_copied_at' => now()]);

        return response()->noContent();
    }

    /**
     * Rewrites the draft from scratch — the recovery from a failed generation,
     * and the way to ask for another take on one that succeeded. Only a
     * generation already in flight is refused, so two runs never race for the
     * same row.
     */
    public function retry(Request $request, Remix $remix, RemixDraftService $drafts): JsonResponse
    {
        $this->ensureOwner($request, $remix);
        $remix = $drafts->failIfStale($remix);
        abort_if($remix->status === 'generating', Response::HTTP_CONFLICT);

        return response()->json([
            'remix' => $drafts->retry($remix, app()->getLocale()),
        ], Response::HTTP_ACCEPTED);
    }

    public function regenerateBlock(
        Request $request,
        Remix $remix,
        RemixBlockService $blocks,
    ): JsonResponse {
        $this->ensureOwner($request, $remix);
        $data = $request->validate([
            'block' => ['required', 'string', 'in:hook,script,visual,ending,cta,caption,slide'],
            'slide_index' => ['nullable', 'required_if:block,slide', 'integer', 'min:0'],
        ]);
        abort_unless(in_array($remix->status, ['draft', 'ready'], true), Response::HTTP_CONFLICT);

        $block = $data['block'];
        $slideIndex = $data['slide_index'] ?? null;
        $content = $remix->generated_content;
        $exists = $block === 'slide'
            ? isset($content['slides'][$slideIndex]['text'])
            : array_key_exists($block, $content);
        abort_unless($exists, Response::HTTP_UNPROCESSABLE_ENTITY);

        $remix = $blocks->regenerate($remix, $block, $slideIndex);

        return response()->json([
            'generated_content' => $remix->generated_content,
            'status' => $remix->status,
        ]);
    }

    private function ensureOwner(Request $request, Remix $remix): void
    {
        abort_unless($remix->user_id === $request->user()->id, 404);
    }
}
