<?php

namespace App\Jobs;

use App\Exceptions\ContentDiscoveryException;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorNicheCatalog;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns seeded accounts into ranked, scored ones. Re-scraping a whole profile is
 * the only way to learn three things a hashtag page never exposes: the real
 * follower count, what the account is actually about, and the median engagement
 * its posts normally get.
 *
 * That median is the point. Every post of the account — including ones found
 * earlier through a hashtag — is (re)scored against it, so a post in the feed
 * means "this beat its own creator", not "this came from a big account".
 */
class MeasureAccountEngagement implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Room for every chunk to burn its full HTTP timeout without the job being killed. */
    public int $timeout = 900;

    /** @param list<string> $usernames Accounts to measure, bare handles. */
    public function __construct(public readonly array $usernames) {}

    public function handle(
        InstagramDataProvider $provider,
        CreatorNicheService $niches,
        CreatorNicheCatalog $catalog,
        OutlierScore $performance,
        ContentSafetyPolicy $safety,
    ): void {
        $due = $this->dueUsernames();

        if ($due === []) {
            return;
        }

        foreach ($due as $username) {
            try {
                $profile = $provider->getProfile($username);

                if (! $profile) {
                    continue;
                }

                $posts = $profile->posts->isNotEmpty()
                    ? $profile->posts->take((int) config('services.discovery.profile_posts'))->values()
                    : $provider->getPosts($profile->username, (int) config('services.discovery.profile_posts'), $profile->externalId);
            } catch (ContentDiscoveryException $exception) {
                Log::warning('Account engagement measurement skipped.', ['account' => $username, 'exception' => $exception]);

                continue;
            }

            $this->measure(new DiscoveredProfile(
                username: $profile->username,
                displayName: $profile->displayName,
                avatarUrl: $profile->avatarUrl,
                followers: $profile->followers,
                posts: $posts,
                bio: $profile->bio,
                externalId: $profile->externalId,
                isPrivate: $profile->isPrivate,
                metadata: $profile->metadata,
            ), $niches, $catalog, $performance, $safety);
        }
    }

    /**
     * The requested accounts whose measurement cooldown has lapsed. An account
     * never measured is always due; the batch cap keeps a large niche from
     * spending the whole Apify budget in one pass.
     *
     * @return list<string>
     */
    private function dueUsernames(): array
    {
        $usernames = array_values(array_unique(array_filter($this->usernames)));

        if ($usernames === []) {
            return [];
        }

        $blocked = Creator::query()
            ->whereIn('username', $usernames)
            ->where('safety_status', ContentSafetyDecision::BLOCKED)
            ->pluck('username')
            ->all();

        $fresh = Creator::query()
            ->whereIn('username', $usernames)
            ->where('safety_status', ContentSafetyDecision::ALLOWED)
            ->whereHas('posts')
            ->where('last_measured_at', '>', now()->subDays((int) config('services.discovery.measure_cooldown_days')))
            ->pluck('username')
            ->all();

        return array_slice(
            array_values(array_diff($usernames, $fresh, $blocked)),
            0,
            (int) config('services.discovery.measure_batch'),
        );
    }

    private function measure(
        DiscoveredProfile $profile,
        CreatorNicheService $niches,
        CreatorNicheCatalog $catalog,
        OutlierScore $performance,
        ContentSafetyPolicy $safety,
    ): void {
        if ($profile->posts->isEmpty()) {
            return;
        }

        $existing = Creator::query()
            ->when($profile->externalId, fn ($query) => $query->where('instagram_user_id', $profile->externalId))
            ->orWhere('username', $profile->username)
            ->first();
        $creatorSafety = $safety->creator($profile);

        if (! $creatorSafety->isAllowed()) {
            $this->blockCreator($profile, $existing, $creatorSafety);

            return;
        }

        $decisions = $profile->posts->mapWithKeys(
            fn (DiscoveredPost $post): array => [$post->sourceUrl => $safety->post($post)],
        );
        $safePosts = $profile->posts
            ->filter(fn (DiscoveredPost $post): bool => $decisions[$post->sourceUrl]->isAllowed())
            ->values();
        $safeProfile = new DiscoveredProfile(
            username: $profile->username,
            displayName: $profile->displayName,
            avatarUrl: $profile->avatarUrl,
            followers: $profile->followers,
            posts: $safePosts,
            bio: $profile->bio,
            externalId: $profile->externalId,
            isPrivate: $profile->isPrivate,
            metadata: $profile->metadata,
        );
        $baselines = $performance->baselines($safePosts);
        $baseline = max(0, (int) round($baselines['engagement'] ?? 0));
        $qualified = $profile->followers >= (int) config('services.discovery.min_followers');
        $hasPendingPosts = $decisions->contains(
            fn (ContentSafetyDecision $decision): bool => $decision->status === ContentSafetyDecision::PENDING,
        );

        $attributes = [
            'display_name' => $profile->displayName ?: $profile->username,
            'avatar_url' => $profile->avatarUrl,
            'instagram_user_id' => $profile->externalId ?: $existing?->instagram_user_id,
            'username' => $profile->username,
            'bio' => $profile->bio,
            'metadata' => array_replace_recursive($existing?->metadata ?? [], $profile->metadata),
            'followers' => $profile->followers,
            'average_views' => (int) $safePosts->map(fn (DiscoveredPost $p): int => $p->views)->avg(),
            'average_likes' => (int) $safePosts->map(fn (DiscoveredPost $p): int => $p->likes)->avg(),
            'baseline_engagement' => $baseline,
            'performance_baselines' => $baselines,
            'avg_engagement_rate' => $safeProfile->engagementRate(),
            'last_measured_at' => $hasPendingPosts ? null : now(),
            'last_fetched_at' => now(),
            'metrics_updated_at' => now(),
            'discovered_at' => $existing?->discovered_at ?: now(),
            'safety_status' => ContentSafetyDecision::ALLOWED,
            'safety_reasons' => [],
            'safety_checked_at' => now(),
        ];

        if ($qualified) {
            $attributes += $this->niche($safeProfile, $niches, $existing);
        } elseif (! $existing) {
            // Classifying costs a model call, and an account that cannot reach a feed
            // is not worth one. Its handle stands in until it clears the floor.
            $attributes['niche'] = $profile->username;
        }

        $creator = $existing ?: new Creator;
        $creator->fill($attributes)->save();

        if ($qualified && is_array($creator->niche_topics)) {
            $catalog->sync($creator, $creator->niche, $creator->niche_topics, $creator->is_catalog_seed ? 'catalog' : 'analysis');
        }

        foreach ($profile->posts as $post) {
            $decision = $decisions[$post->sourceUrl];

            $decision->isAllowed()
                ? $this->storePost($creator, $post, $decision)
                : $this->blockExistingPost($post, $decision);
        }

        // An account under the follower floor is measured — so the cooldown stops us
        // re-scraping it daily — but never scored. Its posts stay unmeasured, which
        // is what keeps them out of every feed. A ratio over a two-like median is
        // arithmetic, not evidence, and that is what was reaching creators.
        $qualified && $safePosts->isNotEmpty()
            ? $this->score($creator, $baselines, $performance)
            : $this->disqualify($creator);
    }

    private function blockCreator(
        DiscoveredProfile $profile,
        ?Creator $existing,
        ContentSafetyDecision $decision,
    ): void {
        $creator = $existing ?: new Creator;
        $creator->fill([
            'instagram_user_id' => $profile->externalId ?: $existing?->instagram_user_id,
            'username' => $profile->username,
            'display_name' => $profile->displayName ?: $profile->username,
            'avatar_url' => $profile->avatarUrl,
            'bio' => $profile->bio,
            'metadata' => array_replace_recursive($existing?->metadata ?? [], $profile->metadata),
            'followers' => $profile->followers,
            'niche' => $existing?->niche ?: $profile->username,
            'average_views' => $existing?->average_views ?: 0,
            'average_likes' => $existing?->average_likes ?: 0,
            'last_fetched_at' => now(),
            'last_measured_at' => $decision->status === ContentSafetyDecision::BLOCKED ? now() : null,
            'discovered_at' => $existing?->discovered_at ?: now(),
            'safety_status' => $decision->status,
            'safety_reasons' => $decision->reasons,
            'safety_checked_at' => now(),
        ])->save();

        $this->disqualify($creator);
    }

    /**
     * Strip any score the account may carry from an earlier measurement, so an
     * account that has since fallen under the floor cannot linger in a feed.
     */
    private function disqualify(Creator $creator): void
    {
        $creator->posts()->whereNotNull('measured_at')->update([
            'measured_at' => null,
            'outlier_score' => 0,
            'performance_ratio' => 0,
            'engagement_rate' => 0,
        ]);
    }

    /**
     * Classify the account once, then leave it alone. The bio and hashtags an
     * account publishes under barely move, so re-running the model on every
     * measurement would pay for the same answer.
     *
     * @return array{niche: string, niche_topics: list<string>}
     */
    private function niche(DiscoveredProfile $profile, CreatorNicheService $niches, ?Creator $existing): array
    {
        if ($existing && is_array($existing->niche_topics) && $existing->niche_topics !== []) {
            return ['niche' => $existing->niche, 'niche_topics' => $existing->niche_topics];
        }

        $detected = $niches->detect($profile);

        return ['niche' => $detected['niche'], 'niche_topics' => $detected['topics']];
    }

    private function storePost(
        Creator $creator,
        DiscoveredPost $post,
        ContentSafetyDecision $decision,
    ): void {
        $existing = ContentPost::query()
            ->when($post->externalId, fn ($query) => $query->where('instagram_media_id', $post->externalId))
            ->orWhere('source_url', $post->sourceUrl)
            ->first();

        $attributes = [
            'creator_id' => $creator->id,
            'instagram_media_id' => $post->externalId ?: $existing?->instagram_media_id,
            'source_url' => $post->sourceUrl,
            'platform' => 'instagram',
            'format' => $post->format,
            'hook' => $this->hook($post, $creator->niche),
            'caption' => $post->caption,
            'thumbnail_url' => $post->thumbnailUrl,
            'views' => $post->views,
            'likes' => $post->likes,
            'comments' => $post->comments,
            'shares' => $post->shares,
            'published_at' => $post->publishedAt,
            'tags' => $post->hashtags,
            'metadata' => array_replace_recursive($existing?->metadata ?? [], $post->metadata),
            'last_fetched_at' => now(),
            'metrics_updated_at' => now(),
            'safety_status' => $decision->status,
            'safety_reasons' => $decision->reasons,
            'safety_checked_at' => now(),
            // why_it_works is written by score() once the baseline is known;
            // the hook and structure breakdown is generated lazily the first
            // time a creator opens the post.
        ];

        $content = $existing ?: new ContentPost;
        $content->fill($attributes)->save();
    }

    private function blockExistingPost(DiscoveredPost $post, ContentSafetyDecision $decision): void
    {
        ContentPost::query()
            ->where(function ($query) use ($post): void {
                $query->when($post->externalId, fn ($query) => $query->where('instagram_media_id', $post->externalId))
                    ->orWhere('source_url', $post->sourceUrl);
            })
            ->update([
                'safety_status' => $decision->status,
                'safety_reasons' => $decision->reasons,
                'safety_checked_at' => now(),
                'measured_at' => null,
                'outlier_score' => 0,
                'performance_ratio' => 0,
                'engagement_rate' => 0,
            ]);
    }

    /**
     * Score every post this account has in the feed against the baseline just
     * measured — not only the ones in this scrape. Posts picked up earlier through
     * a hashtag were stored unscored, and posts scored against an older baseline
     * would no longer be comparable to the ones written a moment ago.
     */
    /** @param array{views: ?float, engagement: ?float} $baselines */
    private function score(Creator $creator, array $baselines, OutlierScore $performance): void
    {
        $creator->posts()
            ->where('safety_status', ContentSafetyDecision::ALLOWED)
            ->chunkById(200, function ($posts) use ($creator, $baselines, $performance): void {
                foreach ($posts as $post) {
                    $engagement = $post->likes + $post->comments + $post->shares;
                    $outlier = $performance->score($post, $baselines);

                    $post->forceFill([
                        'outlier_score' => $outlier,
                        // Kept in step for the clients still reading it. Its own column
                        // is narrower, so a runaway outlier is clamped here only.
                        'performance_ratio' => min(9999.99, $outlier),
                        'engagement_rate' => $performance->engagementRate($engagement, $creator->followers),
                        'why_it_works' => $this->whyItWorks($post, $outlier),
                        'measured_at' => now(),
                    ])->save();
                }
            });
    }

    private function hook(DiscoveredPost $post, ?string $niche): string
    {
        $firstLine = trim((string) Str::of($post->caption)->before("\n"));

        return Str::limit($firstLine !== '' ? $firstLine : 'New '.($niche ?: 'creator').' post', 120);
    }

    private function whyItWorks(ContentPost $post, float $outlier): string
    {
        if ($outlier < 1) {
            return 'A steady post for this account, below the engagement its audience usually gives it.';
        }

        return 'This one reached '.round($outlier, 1).'× the engagement this account normally gets, on '
            .number_format($post->likes).' likes and '.number_format($post->comments).' comments.';
    }
}
