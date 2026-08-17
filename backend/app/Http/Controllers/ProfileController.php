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

        $profile = CreatorProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return response()->json(['profile' => $profile]);
    }

    private function profile(Request $request): CreatorProfile
    {
        return CreatorProfile::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'display_name' => $request->user()->name,
                'niche' => 'Entrepreneurship / SaaS',
                'audience_description' => 'Founders, creators and entrepreneurs',
                'positioning' => 'Building products at the intersection of SaaS and the creator economy.',
                'topics' => ['Building products', 'Creator economy', 'Entrepreneurship', 'Founder journey'],
                'tone' => ['Direct', 'Personal', 'Transparent', 'Educational'],
                'current_projects' => ['Personal'],
                'goals' => ['Build a personal brand', 'Grow an audience', 'Launch Personal'],
                'content_strengths' => ['Founder stories', 'Personal lessons', 'Behind the scenes'],
            ],
        );
    }
}
