<?php

namespace App\Services\View;

use App\Models\User;
use App\Services\Instagram\InstagramMediaProxy;
use Illuminate\Support\Facades\URL;

class UserView
{
    public function __construct(private readonly InstagramMediaProxy $media) {}

    /** @return array<string, mixed> */
    public function make(User $user): array
    {
        $user->loadMissing(['instagramAccount', 'creatorProfile']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $this->avatarUrl($user),
            'instagram_username' => $user->instagram_username,
            'email_verified_at' => $user->email_verified_at,
        ];
    }

    public function avatarUrl(User $user): ?string
    {
        $user->loadMissing('instagramAccount');
        $account = $user->instagramAccount;
        $sourceUrl = $account?->profile_picture_url;

        if (! $account || ! $sourceUrl || ! $this->media->supports($sourceUrl)) {
            return $sourceUrl;
        }

        $path = URL::temporarySignedRoute(
            'media.instagram-account',
            now()->addHours((int) config('services.instagram_media_proxy.signature_hours')),
            ['account' => $account->id],
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$path;
    }
}
