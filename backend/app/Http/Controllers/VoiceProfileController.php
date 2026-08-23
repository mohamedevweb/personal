<?php

namespace App\Http\Controllers;

use App\Services\CreatorVoicePrompt;
use Illuminate\Http\JsonResponse;

class VoiceProfileController extends Controller
{
    public function __invoke(CreatorVoicePrompt $prompt): JsonResponse
    {
        return response()->json([
            'prompt' => $prompt->make(),
            'filename' => 'voice.md',
        ]);
    }
}
