<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Jobs\Discovery\AnalyzeCreatorHandle;
use App\Jobs\Instagram\SyncInstagramAccount;
use App\Models\CreatorProfile;
use App\Services\Creator\OnboardingCompletionService;
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

    public function status(
        Request $request,
        UserView $view,
        OnboardingCompletionService $onboarding,
    ): JsonResponse {
        $inspirationCount = $request->user()->inspirationCreators()->count();
        $profile = $request->user()->creatorProfile()->first();
        $account = $request->user()->instagramAccount()
            ->withCount(['media as imported_media_count'])
            ->first();
        $onboardingComplete = $onboarding->completeFor($request->user(), $account, $profile);
        $primaryVertical = $onboarding->primaryVerticalFor($request->user(), $profile);

        if (! $account) {
            return response()->json([
                'connected' => false,
                'instagram_username' => $profile?->instagram_username,
                'inspiration_count' => $inspirationCount,
                'onboarding_complete' => $onboardingComplete,
                'analysis' => $this->analysis($profile, $primaryVertical),
                'media_enrichment' => $this->mediaEnrichment($profile),
            ]);
        }

        return response()->json([
            'connected' => true,
            'instagram_username' => $account->username,
            'inspiration_count' => $inspirationCount,
            'onboarding_complete' => $onboardingComplete,
            'analysis' => $this->analysis($profile, $primaryVertical),
            'media_enrichment' => $this->mediaEnrichment($profile),
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
                'primary_vertical' => $primaryVertical,
                'topics' => $profile->topics,
                'tone' => $profile->tone,
                'creator_dna' => $profile->creator_dna,
                'dna_analyzed_at' => $profile->dna_analyzed_at,
            ] : null,
        ]);
    }

    public function progress(Request $request, OnboardingCompletionService $onboarding): JsonResponse
    {
        $profile = $request->user()->creatorProfile()->first([
            'id',
            'user_id',
            'instagram_username',
            'analysis_status',
            'analysis_error',
            'primary_vertical',
            'followers_count',
            'analyzed_posts_count',
            'bio',
            'niche',
            'tone',
            'audience_description',
            'creator_dna',
            'dna_analyzed_at',
            'media_enrichment_status',
            'media_enrichment_error',
            'media_enrichment_started_at',
            'media_enrichment_completed_at',
        ]);
        $account = $request->user()->instagramAccount()->first([
            'id',
            'user_id',
            'sync_status',
            'sync_error',
        ]);
        $primaryVertical = $onboarding->primaryVerticalFor($request->user(), $profile);

        return response()->json([
            'onboarding_complete' => $onboarding->completeFor($request->user(), $account, $profile),
            'analysis' => $this->analysis($profile, $primaryVertical),
            'media_enrichment' => $this->mediaEnrichment($profile),
            'account' => $account ? [
                'sync_status' => $account->sync_status,
                'sync_error' => $account->sync_error,
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
        $analysisUnavailable = data_get($profile?->creator_dna, 'analysis_status') === 'analysis_unavailable';
        $missingVertical = ! filled($profile?->primary_vertical)
            && $profile?->analysis_status === 'completed';
        $analyze = $changed
            || in_array($profile?->analysis_status, [null, 'failed'], true)
            || $missingVertical
            || (($outdated || $analysisUnavailable) && ! $analysisRunning);

        $analysis = $analyze ? ['analysis_status' => 'queued', 'analysis_error' => null] : [];

        if ($changed) {
            $analysis += [
                'analysis_started_at' => null,
                'analysis_stage_started_at' => null,
                'analysis_completed_at' => null,
                'analysis_timings' => null,
                'followers_count' => null,
                'analyzed_posts_count' => null,
                'avatar_url' => null,
                'bio' => null,
                'market' => null,
                'market_confidence' => null,
                'discovery_queries' => null,
                'discovery_hashtags' => null,
                'discovery_refreshed_at' => null,
                'media_enrichment_status' => 'idle',
                'media_enrichment_error' => null,
                'media_enrichment_started_at' => null,
                'media_enrichment_completed_at' => null,
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

    public function reanalyzeHandle(Request $request): JsonResponse
    {
        $profile = $request->user()->creatorProfile()->first();
        abort_unless($profile?->instagram_username, Response::HTTP_NOT_FOUND);

        $profile->forceFill([
            'analysis_status' => 'queued',
            'analysis_error' => null,
            'analysis_started_at' => null,
            'analysis_stage_started_at' => null,
            'analysis_completed_at' => null,
            'analysis_timings' => null,
        ])->save();

        AnalyzeCreatorHandle::dispatch($request->user()->id, $profile->instagram_username);

        return response()->json([
            'status' => 'queued',
            'analysis' => $this->analysis($profile),
        ], Response::HTTP_ACCEPTED);
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
    private function analysis(?CreatorProfile $profile, ?string $primaryVertical = null): array
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
            'primary_vertical' => $primaryVertical ?? $profile?->primary_vertical,
            'tone' => $profile?->tone,
            'audience_description' => $profile?->audience_description,
        ];
    }

    /** @return array<string, mixed> */
    private function mediaEnrichment(?CreatorProfile $profile): array
    {
        return [
            'status' => $profile?->media_enrichment_status ?? 'idle',
            'error' => $profile?->media_enrichment_error,
            'started_at' => $profile?->media_enrichment_started_at,
            'completed_at' => $profile?->media_enrichment_completed_at,
        ];
    }
}
