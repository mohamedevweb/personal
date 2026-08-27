<?php

namespace App\Services\Creator;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\InstagramMedia;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\OutlierScore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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

    /**
     * The same contract from the public-handle path, where there is no connected
     * account and everything comes from a scrape. The creator row is what lets a
     * member's own posts be stored, analysed and transcribed like any other.
     *
     * @param  Collection<int, DiscoveredPost>  $posts
     */
    public function syncScraped(DiscoveredProfile $scraped, CreatorProfile $profile, Collection $posts): Creator
    {
        $creator = Creator::query()->where('user_id', $profile->user_id)->first()
            ?? Creator::query()->whereRaw('LOWER(username) = ?', [Str::lower($scraped->username)])->first()
            ?? new Creator;

        $isNew = ! $creator->exists;

        // A handle another member already claimed stays theirs. Two accounts
        // cannot own the same creator row, and silently stealing it would move
        // their own posts out of their feed exclusion.
        if (! $isNew && $creator->user_id !== null && $creator->user_id !== $profile->user_id) {
            return $creator;
        }

        $attributes = [
            'user_id' => $profile->user_id,
            'instagram_user_id' => $scraped->externalId ?: $creator->instagram_user_id,
            'username' => $scraped->username,
            'display_name' => $scraped->displayName ?: $scraped->username,
            'avatar_url' => $scraped->avatarUrl,
            'bio' => $scraped->bio,
            'followers' => max(0, $scraped->followers),
            // The scraped profile metadata is kept because the DNA rebuild reads
            // the bio link and the account category back out of it.
            'metadata' => array_replace_recursive($creator->metadata ?? [], $scraped->metadata, [
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
                // Real values land in storePosts once the posts are scored.
                'average_views' => 0,
                'average_likes' => 0,
                'baseline_engagement' => 0,
                'safety_status' => ContentSafetyDecision::PENDING,
                'safety_reasons' => [],
            ];
        }

        $creator->fill($attributes)->save();

        $this->storePosts($creator, $posts);

        return $creator;
    }

    public function syncPosts(InstagramAccount $account, Creator $creator): void
    {
        $this->storePosts($creator, $account->media()->get()->map(
            fn (InstagramMedia $post): DiscoveredPost => $this->signal($post, $creator),
        ));
    }

    /**
     * Writes a member's own posts as ordinary content posts. They are excluded
     * from their own feed by RecommendationService, and holding them here means
     * the analysis, transcription and remix pipelines need no second code path.
     *
     * @param  Collection<int, DiscoveredPost>  $signals
     */
    public function storePosts(Creator $creator, Collection $signals): void
    {
        $signals = $signals->values();
        $followers = max(0, (int) $creator->followers);
        $baselines = $this->performance->baselines($signals);

        $creator->update([
            'average_views' => (int) round((float) ($baselines['views'] ?? 0)),
            'average_likes' => (int) round((float) $signals->avg(fn (DiscoveredPost $post): int => $post->likes)),
            'baseline_engagement' => max(0, (int) round((float) ($baselines['engagement'] ?? 0))),
            'performance_baselines' => $baselines,
        ]);

        foreach ($signals as $signal) {
            $score = $this->performance->score($signal, $baselines);
            $caption = trim($signal->caption);
            $contentPost = ContentPost::query()
                ->when($signal->externalId, fn ($query) => $query->where('instagram_media_id', $signal->externalId))
                ->when($signal->sourceUrl, fn ($query) => $query->orWhere('source_url', $signal->sourceUrl))
                ->first() ?? new ContentPost;
            $contentPost->fill([
                'creator_id' => $creator->id,
                'instagram_media_id' => $signal->externalId ?: $contentPost->instagram_media_id,
                'platform' => 'instagram',
                'source_url' => $signal->sourceUrl,
                'format' => $signal->format,
                'hook' => Str::limit(str($caption)->before("\n")->trim()->toString() ?: 'Instagram post', 250, ''),
                'caption' => $caption,
                'thumbnail_url' => $signal->thumbnailUrl,
                // A signed CDN link that expires; the last one we saw is kept when
                // this refresh did not return a new one.
                'video_url' => $signal->videoUrl ?: $contentPost->video_url,
                'media_urls' => $signal->mediaUrls,
                'views' => $signal->views,
                'likes' => $signal->likes,
                'comments' => $signal->comments,
                'shares' => $signal->shares,
                'published_at' => $signal->publishedAt,
                'performance_ratio' => $score,
                'outlier_score' => $score,
                'engagement_rate' => $this->performance->engagementRate($signal->engagement(), $followers),
                'tags' => $signal->hashtags,
                'why_it_works' => '',
                'hook_analysis' => '',
                'structure_analysis' => '',
                'measured_at' => now(),
                'metadata' => array_replace_recursive(
                    $contentPost->metadata ?? [],
                    $signal->metadata,
                    ['personal_account_media' => true],
                ),
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
            mediaUrls: $this->format($post) === 'reel'
                ? []
                : array_values(array_filter([$post->media_url ?: $post->thumbnail_url])),
            videoUrl: $this->format($post) === 'reel' ? $post->media_url : null,
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
