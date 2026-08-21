<?php

namespace App\Http\Controllers;

use App\Models\CreatorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email']),
            'profile' => $this->profile($request),
            'instagram' => $request->user()->instagramAccount?->only([
                'username', 'display_name', 'profile_picture_url', 'account_type', 'followers_count', 'media_count', 'sync_status',
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'niche' => ['sometimes', 'nullable', 'string', 'max:160'],
            'audience_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'positioning' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'topics' => ['sometimes', 'array', 'max:12'],
            'topics.*' => ['string', 'max:80'],
            'tone' => ['sometimes', 'array', 'max:12'],
            'tone.*' => ['string', 'max:80'],
            'current_projects' => ['sometimes', 'array', 'max:12'],
            'current_projects.*' => ['string', 'max:120'],
            'goals' => ['sometimes', 'array', 'max:12'],
            'goals.*' => ['string', 'max:120'],
            'content_strengths' => ['sometimes', 'array', 'max:12'],
            'content_strengths.*' => ['string', 'max:120'],
        ]);

        $profile = CreatorProfile::query()->firstOrNew(['user_id' => $request->user()->id]);
        $profile->fill($data);

        if (array_intersect(array_keys($data), ['niche', 'topics', 'audience_description', 'tone'])) {
            $dna = array_merge([
                'primary_niche' => null,
                'sub_niches' => [],
                'topics' => [],
                'audience' => [],
                'language' => 'und',
                'content_pillars' => [],
                'tone' => $profile->tone ?? [],
                'analysis_status' => 'complete',
                'analysis_method' => 'manual',
                'confidence' => 1.0,
            ], $profile->creator_dna ?? []);
            $dna['primary_niche'] = $profile->niche;
            $dna['topics'] = $profile->topics ?? [];
            $dna['audience'] = array_values(array_filter([$profile->audience_description]));
            $dna['tone'] = $profile->tone ?? [];
            $dna['analysis_status'] = 'complete';
            $dna['analysis_method'] = 'manual';
            $dna['confidence'] = 1.0;
            $profile->forceFill([
                'creator_dna' => $dna,
                'discovery_queries' => null,
                'discovery_hashtags' => null,
                'discovery_refreshed_at' => null,
            ]);
        }

        $profile->save();

        return response()->json(['profile' => $profile]);
    }

    private function profile(Request $request): CreatorProfile
    {
        return CreatorProfile::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'display_name' => $request->user()->name,
                'topics' => [],
                'tone' => [],
                'current_projects' => [],
                'goals' => [],
                'content_strengths' => [],
            ],
        );
    }
}
