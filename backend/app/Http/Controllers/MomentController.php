<?php

namespace App\Http\Controllers;

use App\Models\ContentOpportunity;
use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\Remix;
use App\Services\ContentGenerationService;
use App\Services\MomentIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MomentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'moments' => $request->user()->moments()->latest()->get(),
        ]);
    }

    public function store(Request $request, MomentIntelligenceService $intelligence): JsonResponse
    {
        $data = $this->validated($request);
        $analysis = $intelligence->analyze($data['content'], $data['category']);
        $moment = $request->user()->moments()->create([
            ...$data,
            'story_score' => $analysis['score'],
            'story_reasons' => $analysis['reasons'],
        ]);

        ContentOpportunity::query()->create([
            'user_id' => $request->user()->id,
            'life_moment_id' => $moment->id,
            'title' => 'Turn this moment into a story your audience can use',
            'explanation' => 'This moment has a personal transformation and a concrete lesson—two strong signals for an authentic founder post.',
            'relevance_score' => $analysis['score'] * 10,
            'origin' => 'life_moment',
        ]);

        return response()->json(['moment' => $moment], 201);
    }

    public function update(Request $request, LifeMoment $moment, MomentIntelligenceService $intelligence): JsonResponse
    {
        $this->ensureOwner($request, $moment);
        $data = $this->validated($request, partial: true);
        $analysis = $intelligence->analyze(
            $data['content'] ?? $moment->content,
            $data['category'] ?? $moment->category,
        );
        $moment->update([...$data, 'story_score' => $analysis['score'], 'story_reasons' => $analysis['reasons']]);

        return response()->json(['moment' => $moment->fresh()]);
    }

    public function destroy(Request $request, LifeMoment $moment): Response
    {
        $this->ensureOwner($request, $moment);
        $moment->delete();

        return response()->noContent();
    }

    public function createContent(Request $request, LifeMoment $moment, ContentGenerationService $generator): JsonResponse
    {
        $this->ensureOwner($request, $moment);
        $data = $request->validate(['format' => ['nullable', 'in:reel,carousel,caption']]);
        $source = ContentPost::query()->orderByDesc('performance_ratio')->firstOrFail();
        $format = $data['format'] ?? 'carousel';
        $remix = Remix::query()->create([
            'user_id' => $request->user()->id,
            'source_content_id' => $source->id,
            'life_moment_id' => $moment->id,
            'format' => $format,
            'generated_content' => $generator->generate($source, $request->user(), $format, $moment),
            'status' => 'draft',
        ]);

        return response()->json(['remix' => $remix], 201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'content' => [$partial ? 'sometimes' : 'required', 'string', 'max:3000'],
            'category' => [$partial ? 'sometimes' : 'required', 'in:Win,Failure,Lesson,Launch,Idea,Meeting,Milestone,Opinion,Upcoming event,Other'],
            'happened_at' => ['nullable', 'date'],
            'upcoming_at' => ['nullable', 'date'],
        ]);
    }

    private function ensureOwner(Request $request, LifeMoment $moment): void
    {
        abort_unless($moment->user_id === $request->user()->id, 404);
    }
}
