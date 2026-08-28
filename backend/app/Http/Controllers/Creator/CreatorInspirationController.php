<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Services\Creator\CreatorInspirationService;
use App\Services\Creator\OnboardingCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorInspirationController extends Controller
{
    public function index(Request $request, CreatorInspirationService $inspirations): JsonResponse
    {
        return response()->json($inspirations->forUser($request->user()));
    }

    public function search(Request $request, CreatorInspirationService $inspirations): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        return response()->json(['items' => $inspirations->search($request->user(), $data['q'])]);
    }

    public function update(
        Request $request,
        CreatorInspirationService $inspirations,
        OnboardingCompletionService $onboarding,
    ): JsonResponse {
        $data = $request->validate([
            'handles' => [
                'required',
                'array',
                'min:'.CreatorInspirationService::MINIMUM_SELECTION,
                'max:'.CreatorInspirationService::MAXIMUM_SELECTION,
            ],
            'handles.*' => ['required', 'string', 'max:255'],
        ]);

        $selected = $inspirations->select($request->user(), $data['handles']);
        $account = $request->user()->instagramAccount()->first();
        $profile = $request->user()->creatorProfile()->first();

        return response()->json([
            'selected' => $selected,
            'onboarding_complete' => $onboarding->completeFor($request->user(), $account, $profile),
        ]);
    }
}
