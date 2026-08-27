<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Jobs\Discovery\AnalyzeCreatorHandle;
use App\Jobs\Instagram\SyncInstagramAccount;
use App\Models\CreatorProfile;
use App\Services\Creator\CreatorInspirationService;
use App\Services\Instagram\InstagramAuthService;
use App\Services\Instagram\NicheDetectionService;
use App\Services\View\UserView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstagramConnectionController extends Controller
{
    public function authorize(Request $request, InstagramAuthService $auth): JsonResponse
    {
        return response()->json(['authorization_url' => $auth->authorizationUrl($request->user())]);
    }

    public function status(Request $request, UserView $view): JsonResponse
    {
        $inspirationCount = $request->user()->inspirationCreators()->count();
        $profile = $request->user()->creatorProfile()->first();
        $account = $request->user()->instagramAccount()
            ->withCount(['media as imported_media_count'])
            ->first();

        if (! $account) {
            return response()->json([
                'connected' => false,
                'instagram_username' => $profile?->instagram_username,
                'inspiration_count' => $inspirationCount,
                // Onboarding itself holds the creator on the loader until the
                // reading is done; the gate stays on what they chose, so a
                // profile that could not be read never locks them out.
                'onboarding_complete' => filled($profile?->instagram_username)
                    && $inspirationCount >= CreatorInspirationService::MINIMUM_SELECTION,
                'analysis' => $this->analysis($profile),
            ]);
        }

        return response()->json([
            'connected' => true,
            'instagram_username' => $account->username,
            'inspiration_count' => $inspirationCount,
            'onboarding_complete' => $account->sync_status === 'completed' && $inspirationCount >= CreatorInspirationService::MINIMUM_SELECTION,
            'analysis' => $this->analysis($profile),
            'account' => [
                'username' => $account->username,
                'display_name' => $account->display_name,
                'profile_picture_url' => $view->avatarUrl($request->user()),
                'account_type' => $account->account_type,
                'followers_count' => $account->followers_count,
                'media_count' => $account->media_count,
                'imported_media_count' => $account->imported_media_count,
                'sync_status' => $account->sync_status,
                'sync_error' => $account->sync_error,
                'connected_at' => $account->connected_at,
                'last_synced_at' => $account->last_synced_at,
            ],
            'profile' => $profile ? [
                'niche' => $profile->niche,
                'topics' => $profile->topics,
                'tone' => $profile->tone,
                'creator_dna' => $profile->creator_dna,
                'dna_analyzed_at' => $profile->dna_analyzed_at,
            ] : null,
        ]);
    }

    public function storeHandle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:31', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
        ]);

        $username = ltrim($data['username'], '@');
        $profile = $request->user()->creatorProfile()->first();
        $changed = $profile?->instagram_username !== $username;
        // A handle that has already been read is left alone; one whose reading
        // never got anywhere is retried, which is what the loader's retry does.
        $analysisRunning = in_array($profile?->analysis_status, [
            'queued',
            ...AnalyzeCreatorHandle::STAGES,
        ], true);
        $outdated = data_get($profile?->creator_dna, 'analysis_method') !== 'manual'
            && (int) data_get($profile?->creator_dna, 'analysis_version', 0) < NicheDetectionService::ANALYSIS_VERSION;
        $analyze = $changed
            || in_array($profile?->analysis_status, [null, 'failed'], true)
            || ($outdated && ! $analysisRunning);

        $analysis = $analyze ? ['analysis_status' => 'queued', 'analysis_error' => null] : [];

        if ($changed) {
            $analysis += [
                'analysis_started_at' => null,
                'followers_count' => null,
                'analyzed_posts_count' => null,
                'avatar_url' => null,
                'bio' => null,
                'market' => null,
                'market_confidence' => null,
                'discovery_queries' => null,
                'discovery_hashtags' => null,
                'discovery_refreshed_at' => null,
            ];

            if (data_get($profile?->creator_dna, 'analysis_method') !== 'manual') {
                $analysis += [
                    'display_name' => null,
                    'niche' => null,
                    'audience_description' => null,
                    'positioning' => null,
                    'topics' => null,
                    'tone' => null,
                    'current_projects' => null,
                    'goals' => null,
                    'content_strengths' => null,
                    'voice_profile' => null,
                    'creator_dna' => null,
                    'primary_vertical' => null,
                    'dna_analyzed_at' => null,
                ];
            }
        }

        $profile = $request->user()->creatorProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'instagram_username' => $username,
                ...$analysis,
            ],
        );

        // Reading the public profile is what makes the app personal, so it starts
        // here rather than waiting for the creator to find the memory page.
        if ($analyze) {
            AnalyzeCreatorHandle::dispatch($request->user()->id, $username);
        }

        return response()->json([
            'instagram_username' => $username,
            'analysis' => $this->analysis($profile),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $account = $request->user()->instagramAccount()->firstOrFail();
        $account->update(['sync_status' => 'importing_content', 'sync_error' => null]);
        SyncInstagramAccount::dispatch($account->id);

        return response()->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    public function disconnect(Request $request, InstagramAuthService $auth): Response
    {
        $account = $request->user()->instagramAccount()->first();
        if ($account) {
            $auth->disconnect($account);
        }

        return response()->noContent();
    }

    /**
     * Where the public-profile reading is, and what it has found so far.
     * Onboarding holds the creator on the loader until this says completed.
     *
     * @return array<string, mixed>
     */
    private function analysis(?CreatorProfile $profile): array
    {
        return [
            'status' => $profile?->analysis_status ?? 'idle',
            // A stable key, so onboarding says it in the creator's own language.
            'error' => $profile?->analysis_error,
            'stages' => AnalyzeCreatorHandle::STAGES,
            'posts_target' => AnalyzeCreatorHandle::POSTS_READ,
            'followers_count' => $profile?->followers_count,
            'analyzed_posts_count' => $profile?->analyzed_posts_count,
            'bio' => $profile?->bio,
            'niche' => $profile?->niche,
            'tone' => $profile?->tone,
            'audience_description' => $profile?->audience_description,
        ];
    }
}
