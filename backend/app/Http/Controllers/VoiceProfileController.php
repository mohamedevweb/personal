<?php

namespace App\Http\Controllers;

use App\Services\CreatorVoicePrompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceProfileController extends Controller
{
    public function __invoke(Request $request, CreatorVoicePrompt $prompt): JsonResponse
    {
        return response()->json([
            'prompt' => $prompt->make($request->user(), $request->user()->creatorProfile),
            'filename' => 'voice.md',
        ]);
    }
}
