<?php

namespace App\Services\Creator;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\InstagramMedia;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\OutlierScore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RegisteredCreatorService
{
    public function __construct(
        private readonly OutlierScore $performance,
    ) {}

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

        $this->syncPosts($account, $creator);

        return $creator;
    }

    public function syncPosts(InstagramAccount $account, Creator $creator): void
    {
        $media = $account->media()->get();
        $followers = max(0, (int) $creator->followers);
        $signals = $media->map(fn (InstagramMedia $post): DiscoveredPost => $this->signal($post, $creator));
        $baselines = $this->performance->baselines($signals);

        $creator->update([
            'average_views' => (int) round((float) ($baselines['views'] ?? 0)),
            'average_likes' => (int) round((float) $media->avg(fn (InstagramMedia $post): int => (int) $post->like_count)),
            'baseline_engagement' => max(0, (int) round((float) ($baselines['engagement'] ?? 0))),
            'performance_baselines' => $baselines,
        ]);

        foreach ($media as $post) {
            $signal = $signals->firstWhere('externalId', $post->instagram_media_id);
            $score = $this->performance->score($signal, $baselines);
            $caption = trim((string) $post->caption);
            $contentPost = ContentPost::query()
                ->where('instagram_media_id', $post->instagram_media_id)
                ->when($post->permalink, fn ($query) => $query->orWhere('source_url', $post->permalink))
                ->first() ?? new ContentPost;
            $contentPost->fill([
                'creator_id' => $creator->id,
                'instagram_media_id' => $post->instagram_media_id,
                'platform' => 'instagram',
                'source_url' => $post->permalink,
                'format' => $signal->format,
                'hook' => Str::limit(str($caption)->before("\n")->trim()->toString() ?: 'Instagram post', 250, ''),
                'caption' => $caption,
                'thumbnail_url' => $post->thumbnail_url ?: $post->media_url,
                'media_urls' => array_values(array_filter([$post->media_url ?: $post->thumbnail_url])),
                'views' => $signal->views,
                'likes' => $signal->likes,
                'comments' => $signal->comments,
                'shares' => $signal->shares,
                'published_at' => $post->published_at ?: now(),
                'performance_ratio' => $score,
                'outlier_score' => $score,
                'engagement_rate' => $this->performance->engagementRate($signal->engagement(), $followers),
                'tags' => [],
                'why_it_works' => '',
                'hook_analysis' => '',
                'structure_analysis' => '',
                'measured_at' => now(),
                'metadata' => ['personal_account_media' => true],
                'last_fetched_at' => now(),
                'metrics_updated_at' => now(),
                'safety_status' => ContentSafetyDecision::PENDING,
                'safety_reasons' => [],
            ]);
            $contentPost->save();
        }
    }

    private function signal(InstagramMedia $post, Creator $creator): DiscoveredPost
    {
        return new DiscoveredPost(
            sourceUrl: (string) $post->permalink,
            username: $creator->username,
            displayName: $creator->display_name,
            avatarUrl: $creator->avatar_url,
            followers: max(0, (int) $creator->followers),
            caption: (string) $post->caption,
            thumbnailUrl: $post->thumbnail_url ?: $post->media_url,
            likes: max(0, (int) $post->like_count),
            comments: max(0, (int) $post->comments_count),
            views: $this->views($post),
            publishedAt: CarbonImmutable::instance($post->published_at ?: now()),
            format: $this->format($post),
            hashtags: [],
            externalId: $post->instagram_media_id,
            shares: max(0, (int) data_get($post->metrics, 'shares', 0)),
            mediaUrls: array_values(array_filter([$post->media_url ?: $post->thumbnail_url])),
        );
    }

    private function views(InstagramMedia $post): int
    {
        return max(0, (int) (data_get($post->metrics, 'views')
            ?? data_get($post->metrics, 'plays')
            ?? data_get($post->metrics, 'reach')
            ?? 0));
    }

    private function format(InstagramMedia $post): string
    {
        if ($post->media_type === 'CAROUSEL_ALBUM') {
            return 'carousel';
        }

        return $post->media_product_type === 'REELS' || $post->media_type === 'VIDEO'
            ? 'reel'
            : 'image';
    }
}
