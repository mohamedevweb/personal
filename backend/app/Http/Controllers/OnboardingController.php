<?php

namespace App\Http\Controllers;

use App\Services\UserView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __invoke(Request $request, UserView $view): JsonResponse
    {
        $user = $request->user();

        if (! $user->onboarding_completed_at) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return response()->json(['user' => $view->make($user->fresh())]);
    }
}
