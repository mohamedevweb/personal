<?php

namespace App\Services;

use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Services\Discovery\ContentSafetyDecision;
use Illuminate\Support\Str;

class RegisteredCreatorService
{
    public function sync(InstagramAccount $account, CreatorProfile $profile): Creator
    {
        $linked = Creator::query()->where('user_id', $account->user_id)->first();
        $matched = Creator::query()
            ->where(function ($query) use ($account): void {
                $query->where('instagram_user_id', $account->instagram_user_id)
                    ->orWhereRaw('LOWER(username) = ?', [Str::lower($account->username)]);
            })
            ->first();

        if ($linked && $matched && ! $linked->is($matched)) {
            $linked->update(['user_id' => null]);
        }

        $creator = $matched ?? $linked ?? new Creator;
        $isNew = ! $creator->exists;
        $media = $account->media;
        $attributes = [
            'user_id' => $account->user_id,
            'instagram_user_id' => $account->instagram_user_id,
            'username' => $account->username,
            'display_name' => $account->display_name ?: $account->username,
            'avatar_url' => $account->profile_picture_url,
            'bio' => $account->bio,
            'followers' => max(0, (int) $account->followers_count),
            'metadata' => array_replace_recursive($creator->metadata ?? [], [
                'personal_member' => [
                    'joined_at' => data_get($creator->metadata, 'personal_member.joined_at') ?? now()->toIso8601String(),
                    'last_synced_at' => now()->toIso8601String(),
                ],
            ]),
            'last_fetched_at' => now(),
            'metrics_updated_at' => now(),
            'discovered_at' => $creator->discovered_at ?: now(),
        ];

        if ($isNew) {
            $language = data_get($profile->creator_dna, 'language');
            $attributes += [
                'niche' => $profile->primary_vertical ?: 'unclassified',
                'niche_topics' => $profile->topics ?? [],
                'market' => $profile->market,
                'primary_language' => in_array($language, ['fr', 'en', 'mixed'], true) ? $language : 'unknown',
                'curation_status' => 'discovered',
                'is_catalog_seed' => false,
                'average_views' => (int) $media->avg(fn ($item): int => (int) data_get($item->metrics, 'views', 0)),
                'average_likes' => (int) $media->avg(fn ($item): int => (int) $item->like_count),
                'baseline_engagement' => 0,
                'safety_status' => ContentSafetyDecision::PENDING,
                'safety_reasons' => [],
            ];
        }

        $creator->fill($attributes)->save();

        return $creator;
    }
}
