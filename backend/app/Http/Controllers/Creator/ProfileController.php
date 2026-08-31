<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Jobs\Discovery\AnalyzeCreatorHandle;
use App\Jobs\Discovery\RefreshCreatorAvatar;
use App\Models\CreatorProfile;
use App\Services\Creator\RegisteredCreatorService;
use App\Services\Instagram\NicheDetectionService;
use App\Services\View\ContentPostView;
use App\Services\View\UserView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProfileController extends Controller
{
    public function show(Request $request, UserView $view): JsonResponse
    {
        $account = $request->user()->instagramAccount;
        $profile = $this->profile($request);
        $this->ensureAvatar($request, $account !== null, $profile);
        $this->ensureDnaIsCurrent($request, $account !== null, $profile);

        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email']),
            'profile' => $this->render($profile, $view->avatarUrl($request->user())),
            'instagram' => $account ? [
                ...$account->only(['username', 'display_name', 'account_type', 'followers_count', 'media_count', 'sync_status']),
                'profile_picture_url' => $view->avatarUrl($request->user()),
            ] : null,
        ]);
    }

    public function update(Request $request, UserView $view): JsonResponse
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
            'voice_profile' => ['sometimes', 'nullable', 'string', 'max:12000'],
        ]);

        $profile = CreatorProfile::query()->firstOrNew(['user_id' => $request->user()->id]);
        $profile->fill($data);

        if (array_intersect(array_keys($data), [
            'niche',
            'topics',
            'audience_description',
            'positioning',
            'tone',
            'current_projects',
            'goals',
            'content_strengths',
            'voice_profile',
        ])) {
            $dna = array_merge([
                'primary_niche' => null,
                'sub_niches' => [],
                'topics' => [],
                'audience' => [],
                'positioning' => null,
                'language' => 'und',
                'content_pillars' => [],
                'tone' => $profile->tone ?? [],
                'current_projects' => [],
                'goals' => [],
                'content_strengths' => [],
                'voice_profile' => null,
                'analysis_status' => 'complete',
                'analysis_method' => 'manual',
                'analysis_version' => NicheDetectionService::ANALYSIS_VERSION,
                'confidence' => 1.0,
            ], $profile->creator_dna ?? []);
            $dna['primary_niche'] = $profile->niche;
            $dna['topics'] = $profile->topics ?? [];
            $dna['audience'] = array_values(array_filter([$profile->audience_description]));
            $dna['positioning'] = $profile->positioning;
            $dna['tone'] = $profile->tone ?? [];
            $dna['current_projects'] = $profile->current_projects ?? [];
            $dna['goals'] = $profile->goals ?? [];
            $dna['content_strengths'] = $profile->content_strengths ?? [];
            $dna['voice_profile'] = $profile->voice_profile;
            $dna['analysis_status'] = 'complete';
            $dna['analysis_method'] = 'manual';
            $dna['analysis_version'] = NicheDetectionService::ANALYSIS_VERSION;
            $dna['confidence'] = 1.0;
            $profile->forceFill([
                'creator_dna' => $dna,
                'discovery_queries' => null,
                'discovery_hashtags' => null,
                'discovery_refreshed_at' => null,
            ]);
        }

        $profile->save();

        return response()->json(['profile' => $this->render($profile, $view->avatarUrl($request->user()))]);
    }

    public function posts(
        Request $request,
        RegisteredCreatorService $creators,
        ContentPostView $view,
    ): JsonResponse {
        $account = $request->user()->instagramAccount;
        $creator = $request->user()->creatorIdentity;

        if (! $account || ! $creator) {
            return response()->json(['posts' => []]);
        }

        if (! $creator->posts()->whereNotNull('instagram_media_id')->exists() && $account->media()->exists()) {
            $creators->syncPosts($account, $creator);
        }

        $posts = $creator->posts()
            ->whereNotNull('instagram_media_id')
            ->orderByDesc('outlier_score')
            ->orderByDesc('published_at')
            ->limit(24)
            ->get();
        $savedIds = $request->user()->savedContent()
            ->whereIn('content_post_id', $posts->pluck('id'))
            ->pluck('content_post_id')
            ->flip();
        $posts = $posts->map(fn ($post): array => $view->make(
            $post,
            $request->user(),
            isSaved: $savedIds->has($post->id),
        ));

        return response()->json(['posts' => $posts]);
    }

    /**
     * The stored avatar_url is an Instagram CDN link the browser cannot load, so
     * the payload carries the proxied one instead of the raw column.
     *
     * @return array<string, mixed>
     */
    private function render(CreatorProfile $profile, ?string $avatarUrl): array
    {
        return [...$profile->toArray(), 'avatar_url' => $avatarUrl];
    }

    /**
     * Handles read before the picture was kept, and links that stopped resolving,
     * are filled in behind the page. Once an hour at most, so opening the memory
     * page never turns into a scrape per view.
     */
    private function ensureAvatar(Request $request, bool $connected, CreatorProfile $profile): void
    {
        if ($connected || $profile->avatar_url || ! $profile->instagram_username) {
            return;
        }

        if (Cache::add("creator-avatar-refresh:{$request->user()->id}", true, now()->addHour())) {
            RefreshCreatorAvatar::dispatch($request->user()->id);
        }
    }

    /** Re-run older public-handle contracts when the creator opens their memory. */
    private function ensureDnaIsCurrent(Request $request, bool $connected, CreatorProfile $profile): void
    {
        $running = in_array($profile->analysis_status, [
            'queued',
            ...AnalyzeCreatorHandle::STAGES,
        ], true);

        if ($connected
            || $running
            || ! $profile->instagram_username
            || data_get($profile->creator_dna, 'analysis_method') === 'manual'
            || (int) data_get($profile->creator_dna, 'analysis_version', 0) >= NicheDetectionService::ANALYSIS_VERSION) {
            return;
        }

        $profile->forceFill(['analysis_status' => 'queued', 'analysis_error' => null])->save();
        AnalyzeCreatorHandle::dispatch($request->user()->id, $profile->instagram_username);
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
