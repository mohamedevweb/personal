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

    /**
     * The picture Personal shows for a creator: the connected account's when
     * there is one, otherwise the one read off the public profile behind their
     * handle. Either way it is served through the signed media proxy, since the
     * Instagram CDN refuses to be embedded from another origin.
     */
    public function avatarUrl(User $user): ?string
    {
        $user->loadMissing(['instagramAccount', 'creatorProfile']);
        $account = $user->instagramAccount;

        if ($account && $account->profile_picture_url) {
            return $this->proxied(
                $account->profile_picture_url,
                'media.instagram-account',
                ['account' => $account->id],
            );
        }

        $profile = $user->creatorProfile;

        return $profile?->avatar_url
            ? $this->proxied($profile->avatar_url, 'media.creator-profile', ['profile' => $profile->id])
            : null;
    }

    /** @param array<string, mixed> $parameters */
    private function proxied(string $sourceUrl, string $route, array $parameters): string
    {
        if (! $this->media->supports($sourceUrl)) {
            return $sourceUrl;
        }

        $path = URL::temporarySignedRoute(
            $route,
            now()->addHours((int) config('services.instagram_media_proxy.signature_hours')),
            $parameters,
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$path;
    }
}
