<?php

namespace App\Http\Controllers;

use App\Jobs\SyncInstagramAccount;
use App\Services\Instagram\InstagramAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstagramConnectionController extends Controller
{
    public function authorize(Request $request, InstagramAuthService $auth): JsonResponse
    {
        return response()->json(['authorization_url' => $auth->authorizationUrl($request->user())]);
    }

    public function status(Request $request): JsonResponse
    {
        $account = $request->user()->instagramAccount()
            ->withCount(['media as imported_media_count'])
            ->first();

        if (! $account) {
            return response()->json(['connected' => false]);
        }

        return response()->json([
            'connected' => true,
            'account' => [
                'username' => $account->username,
                'display_name' => $account->display_name,
                'profile_picture_url' => $account->profile_picture_url,
                'account_type' => $account->account_type,
                'followers_count' => $account->followers_count,
                'media_count' => $account->media_count,
                'imported_media_count' => $account->imported_media_count,
                'sync_status' => $account->sync_status,
                'sync_error' => $account->sync_error,
                'connected_at' => $account->connected_at,
                'last_synced_at' => $account->last_synced_at,
            ],
            'profile' => $request->user()->creatorProfile()->first(),
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
}
