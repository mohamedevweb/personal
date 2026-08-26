<?php

namespace App\Jobs\Discovery;

use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Services\Discovery\InstagramDataProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads nothing off a creator's public profile but the picture on it.
 * AnalyzeCreatorHandle already saves that while it reads the rest, so this is for
 * the profiles that were read before there was a column to keep it in, and for
 * the picture that goes missing later.
 */
class RefreshCreatorAvatar implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $userId) {}

    public function handle(InstagramDataProvider $instagram): void
    {
        $profile = CreatorProfile::query()->where('user_id', $this->userId)->first();
        $username = $profile?->instagram_username;

        // A connected account carries its own picture from the Meta API, which
        // outranks anything read off the public profile.
        if (! $profile || ! $username || InstagramAccount::query()->where('user_id', $this->userId)->exists()) {
            return;
        }

        $scraped = $instagram->getProfile($username);

        if (! $scraped || $scraped->isPrivate || ! $scraped->avatarUrl) {
            return;
        }

        $profile->forceFill(['avatar_url' => $scraped->avatarUrl])->save();
    }
}
