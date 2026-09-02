<?php

namespace App\Jobs\Discovery;

use App\Exceptions\ContentDiscoveryException;
use App\Jobs\Content\CacheContentMedia;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorMarketDetector;
use App\Services\Discovery\CreatorNicheCatalog;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\CreatorScrapeSchedule;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use App\Services\Discovery\PostMetricsLifecycle;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
class MeasureAccountEngagement implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Room for every chunk to burn its full HTTP timeout without the job being killed. */
    public int $timeout = 900;

    public int $uniqueFor = 3600;

    /**
     * @param  list<string>  $usernames  Accounts to measure, bare handles.
     * @param  array<string, string>  $marketHints  Authoritative market hints keyed by lowercase handle.
     * @param  array<string, string>  $verticalHints  Editorial verticals keyed by lowercase handle.
     */
    public function __construct(
        public readonly array $usernames,
        public readonly bool $latestOnly = false,
        public readonly array $marketHints = [],
        public readonly bool $force = false,
        public readonly bool $recentOnly = false,
        public readonly ?int $postsLimit = null,
        public readonly array $verticalHints = [],
    ) {}

    public function uniqueId(): string
    {
        $usernames = $this->usernames;
        sort($usernames);

        return sha1(implode('|', $usernames));
    }

    public function measureUsername(
        string $username,
        InstagramDataProvider $provider,
        CreatorNicheService $niches,
        CreatorNicheCatalog $catalog,
        OutlierScore $performance,
        ContentSafetyPolicy $safety,
        ?CreatorScrapeSchedule $schedule = null,
        ?PostMetricsLifecycle $lifecycle = null,
        ?CreatorMarketDetector $markets = null,
    ): ?Creator {
        return $this->refreshUsername(
            $username,
            $provider,
            $niches,
            $catalog,
            $performance,
            $safety,
            $schedule ?? app(CreatorScrapeSchedule::class),
            $lifecycle ?? app(PostMetricsLifecycle::class),
            $markets ?? app(CreatorMarketDetector::class),
        );
    }

    public function importPost(
        Creator $creator,
        DiscoveredPost $post,
        ContentSafetyPolicy $safety,
        PostMetricsLifecycle $lifecycle,
        OutlierScore $performance,
    ): ContentPost {
        $decision = $safety->post($post);

        if (! $decision->isAllowed()) {
            throw new ContentDiscoveryException('This Instagram post could not be added because it did not pass the content safety check.');
        }

        $content = $this->storePost($creator, $post, $decision, $lifecycle, now());
        $creator->refresh();
        $this->score($creator, $creator->performance_baselines ?? [], $performance);

        $content->refresh();
        $lifecycle->reschedule($content, now());
        CacheContentMedia::dispatch($content->id);

        return $content;
    }

    public function handle(
        InstagramDataProvider $provider,
        CreatorNicheService $niches,
        CreatorNicheCatalog $catalog,
        OutlierScore $performance,
        ContentSafetyPolicy $safety,
        ?CreatorScrapeSchedule $schedule = null,
        ?PostMetricsLifecycle $lifecycle = null,
        ?CreatorMarketDetector $markets = null,
    ): void {
        $schedule ??= app(CreatorScrapeSchedule::class);
        $lifecycle ??= app(PostMetricsLifecycle::class);
        $markets ??= app(CreatorMarketDetector::class);
        $due = $this->dueUsernames();

        if ($due === []) {
            return;
        }

        foreach ($due as $username) {
            $lock = Cache::lock('instagram-creator-scrape:'.mb_strtolower($username), $this->timeout + 60);

            if (! $lock->get()) {
                continue;
            }

            try {
                $this->refreshUsername(
                    $username,
                    $provider,
                    $niches,
                    $catalog,
                    $performance,
                    $safety,
                    $schedule,
                    $lifecycle,
                    $markets,
                );
            } finally {
                $lock->release();
            }
        }
    }

    private function refreshUsername(
        string $username,
        InstagramDataProvider $provider,
        CreatorNicheService $niches,
        CreatorNicheCatalog $catalog,
        OutlierScore $performance,
        ContentSafetyPolicy $safety,
        CreatorScrapeSchedule $schedule,
        PostMetricsLifecycle $lifecycle,
        CreatorMarketDetector $markets,
    ): ?Creator {
        try {
            $profile = $provider->getProfile($username, fresh: true);

            if (! $profile) {
                $creator = Creator::query()->where('username', $username)->first();

                if ($creator) {
                    $schedule->recordFailure($creator, now());
                }

                return null;
            }

            $postsLimit = max(1, $this->postsLimit ?? (int) config('services.discovery.profile_posts'));
            $posts = $profile->posts->isNotEmpty()
                ? $profile->posts->take($postsLimit)->values()
                : $provider->getPosts($profile->username, $postsLimit, $profile->externalId);

            if ($this->recentOnly) {
                $posts = $posts
                    ->filter(fn (DiscoveredPost $post): bool => $post->publishedAt->greaterThanOrEqualTo(
                        now()->subDays((int) config('services.discovery.feed_window_days')),
                    ))
                    ->values();
            }
        } catch (ContentDiscoveryException $exception) {
            $creator = Creator::query()->where('username', $username)->first();

            if ($creator) {
                $schedule->recordFailure($creator, now());
            }

            Log::warning('Account engagement measurement skipped.', ['account' => $username, 'exception' => $exception]);

            return null;
        }

        if ($posts->isEmpty()) {
            $creator = Creator::query()->where('username', $profile->username)->first();

            if ($creator) {
                $schedule->recordSuccess($creator, now());
            }

            return null;
        }

        $creator = $this->measure(new DiscoveredProfile(
            username: $profile->username,
            displayName: $profile->displayName,
            avatarUrl: $profile->avatarUrl,
            followers: $profile->followers,
            posts: $posts,
            bio: $profile->bio,
            externalId: $profile->externalId,
            isPrivate: $profile->isPrivate,
            metadata: $profile->metadata,
        ), $niches, $catalog, $performance, $safety, $lifecycle, $markets, $this->marketHint($username), $this->verticalHint($username));

        if ($creator) {
            if ($creator->safety_status === ContentSafetyDecision::PENDING || ! $creator->last_measured_at) {
                $schedule->recordFailure($creator, now());
            } else {
                $schedule->recordSuccess($creator, now());
            }
        }

        return $creator;
    }

    /**
     * The requested accounts whose measurement cooldown has lapsed. An account
     * never measured is always due; the batch cap keeps a large niche from
     * consuming the whole provider budget in one pass.
     *
     * @return list<string>
     */
    private function dueUsernames(): array
    {
        $usernames = array_values(array_unique(array_filter($this->usernames)));

        if ($usernames === []) {
            return [];
        }

        $known = Creator::query()->whereIn('username', $usernames)->get()->keyBy('username');

        $ignoreSchedule = $this->force
            || (int) config('services.discovery.measure_cooldown_days') === 0;

        return array_slice(array_values(array_filter($usernames, function (string $username) use ($known, $ignoreSchedule): bool {
            $creator = $known->get($username);

            return ! $creator
                || ($creator->curation_status !== 'inactive'
                    && $creator->safety_status !== ContentSafetyDecision::BLOCKED
                    && ($ignoreSchedule
                        || ! $creator->next_scrape_at
                        || $creator->next_scrape_at->isPast()
                        || (! $creator->is_catalog_seed
                            && $creator->niche_analysis_version < CreatorNicheService::ANALYSIS_VERSION)));
        })), 0, (int) config('services.discovery.measure_batch'));
    }

    private function measure(
        DiscoveredProfile $profile,
        CreatorNicheService $niches,
        CreatorNicheCatalog $catalog,
        OutlierScore $performance,
        ContentSafetyPolicy $safety,
        PostMetricsLifecycle $lifecycle,
        CreatorMarketDetector $markets,
        ?string $marketHint,
        ?string $verticalHint,
    ): ?Creator {
        if ($profile->posts->isEmpty()) {
            return null;
        }

        $existing = Creator::query()
            ->when($profile->externalId, fn ($query) => $query->where('instagram_user_id', $profile->externalId))
            ->orWhere('username', $profile->username)
            ->first();
        $market = in_array($marketHint, config('creator_catalog.markets'), true)
            ? ['market' => $marketHint, 'language' => $marketHint === 'FR' ? 'fr' : 'en']
            : ($existing?->is_catalog_seed
                ? ['market' => $existing->market, 'language' => $existing->primary_language]
                : $markets->detect(implode("\n", array_filter([
                    $profile->displayName,
                    $profile->bio,
                    json_encode($profile->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $profile->posts->pluck('caption')->filter()->take(20)->implode("\n"),
                ]))));

        if ($market['market'] === null) {
            if (in_array($existing?->market, config('creator_catalog.markets'), true)) {
                $market = ['market' => $existing->market, 'language' => $existing->primary_language];
            }
        }

        $creatorSafety = $safety->creator($profile);

        if (! $creatorSafety->isAllowed()) {
            $this->blockCreator($profile, $existing, $creatorSafety);

            return Creator::query()->where('username', $profile->username)->first();
        }

        if (! in_array($market['market'], config('creator_catalog.markets'), true)) {
            return $this->excludeUnsupportedMarket($profile, $existing, $market);
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
            'safety_policy_version' => ContentSafetyPolicy::VERSION,
            'market' => $market['market'],
            'primary_language' => $market['language'],
        ];

        if ($qualified) {
            $attributes += $this->niche($safeProfile, $niches, $existing);
        } elseif (! $existing) {
            // Classifying costs a model call, and an account that cannot reach a feed
            // is not worth one. Its handle stands in until it clears the floor.
            $attributes['niche'] = $profile->username;
        }

        if ($verticalHint !== null) {
            $attributes['primary_vertical'] = $verticalHint;
            $attributes['metadata'] = array_replace_recursive($attributes['metadata'], [
                'catalog_import' => [
                    'vertical_override' => $verticalHint,
                    'market_override' => $market['market'],
                ],
            ]);
            $attributes['curation_status'] = 'approved';
            $attributes['is_catalog_seed'] = true;
        }

        $creator = $existing ?: new Creator;
        $creator->fill($attributes)->save();

        if ($qualified && is_array($creator->niche_topics)) {
            $catalog->sync($creator, $creator->niche, $creator->niche_topics, $creator->is_catalog_seed ? 'catalog' : 'analysis');
        }

        $refreshedPostIds = [];
        $capturedAt = now();

        $postsToStore = $this->latestOnly
            ? $profile->posts->sortByDesc(fn (DiscoveredPost $post): int => $post->publishedAt->getTimestamp())->take(1)
            : $profile->posts;

        foreach ($postsToStore as $post) {
            $decision = $decisions[$post->sourceUrl];

            if ($decision->isAllowed()) {
                $refreshedPostIds[] = $this->storePost($creator, $post, $decision, $lifecycle, $capturedAt)->id;
            } else {
                $this->blockExistingPost($post, $decision);
            }
        }

        // An account under the follower floor is measured — so the cooldown stops us
        // re-scraping it daily — but never scored. Its posts stay unmeasured, which
        // is what keeps them out of every feed. A ratio over a two-like median is
        // arithmetic, not evidence, and that is what was reaching creators.
        $qualified && $safePosts->isNotEmpty()
            ? $this->score($creator, $baselines, $performance)
            : $this->disqualify($creator);

        ContentPost::query()
            ->whereIn('id', $refreshedPostIds)
            ->with('creator')
            ->get()
            ->each(function (ContentPost $post) use ($capturedAt, $lifecycle): void {
                $lifecycle->reschedule($post, $capturedAt);
                // The links we just stored expire in a few days. Copy what they
                // point at now, or the frames nobody has opened yet are lost.
                CacheContentMedia::dispatch($post->id);
            });

        return $creator;
    }

    private function marketHint(string $username): ?string
    {
        $hint = $this->marketHints[mb_strtolower($username)] ?? null;

        return is_string($hint) ? strtoupper($hint) : null;
    }

    private function verticalHint(string $username): ?string
    {
        $hint = $this->verticalHints[mb_strtolower($username)] ?? null;

        return is_string($hint) ? strtolower($hint) : null;
    }

    /** @param array{market: ?string, language: string} $market */
    private function excludeUnsupportedMarket(DiscoveredProfile $profile, ?Creator $existing, array $market): Creator
    {
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
            'market' => $market['market'],
            'primary_language' => $market['language'],
            'curation_status' => $existing?->user_id ? $existing->curation_status : 'inactive',
            'last_fetched_at' => now(),
            'last_measured_at' => now(),
            'discovered_at' => $existing?->discovered_at ?: now(),
        ])->save();

        $this->disqualify($creator);

        return $creator;
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
            'safety_policy_version' => ContentSafetyPolicy::VERSION,
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
     * Reuse a current classification, but refresh results produced by an older
     * analysis contract. Curated catalog entries keep their editorial labels.
     *
     * @return array{niche: string, niche_topics: list<string>, primary_vertical: ?string, niche_analysis_version: int}
     */
    private function niche(DiscoveredProfile $profile, CreatorNicheService $niches, ?Creator $existing): array
    {
        if ($existing && is_array($existing->niche_topics) && $existing->niche_topics !== []
            && ($existing->is_catalog_seed || $existing->niche_analysis_version >= CreatorNicheService::ANALYSIS_VERSION)) {
            return [
                'niche' => $existing->niche,
                'niche_topics' => $existing->niche_topics,
                'primary_vertical' => $existing->primary_vertical,
                'niche_analysis_version' => CreatorNicheService::ANALYSIS_VERSION,
            ];
        }

        $detected = $niches->detect($profile);

        return [
            'niche' => $detected['niche'],
            'niche_topics' => $detected['topics'],
            'primary_vertical' => $detected['primary_vertical'],
            'niche_analysis_version' => CreatorNicheService::ANALYSIS_VERSION,
        ];
    }

    private function storePost(
        Creator $creator,
        DiscoveredPost $post,
        ContentSafetyDecision $decision,
        PostMetricsLifecycle $lifecycle,
        CarbonInterface $capturedAt,
    ): ContentPost {
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
            'video_url' => $post->videoUrl ?: $existing?->video_url,
            'media_urls' => $post->mediaUrls,
            'views' => $post->views,
            'likes' => $post->likes,
            'comments' => $post->comments,
            'shares' => $post->shares,
            'published_at' => $post->publishedAt,
            'tags' => $post->hashtags,
            'metadata' => array_replace_recursive($existing?->metadata ?? [], $post->metadata),
            'last_fetched_at' => $capturedAt,
            'metrics_updated_at' => $capturedAt,
            'safety_status' => $decision->status,
            'safety_reasons' => $decision->reasons,
            'safety_checked_at' => $capturedAt,
            'safety_policy_version' => ContentSafetyPolicy::VERSION,
            // why_it_works is written by score() once the baseline is known;
            // the hook and structure breakdown is generated lazily the first
            // time a creator opens the post.
        ];

        return DB::transaction(function () use ($attributes, $capturedAt, $existing, $lifecycle, $post): ContentPost {
            if ($existing) {
                $content = $existing;
                $content->fill($attributes)->save();
            } else {
                $attributes['created_at'] = $capturedAt;
                $attributes['updated_at'] = $capturedAt;
                $identity = $post->externalId ? ['instagram_media_id'] : ['source_url'];
                $updates = array_values(array_diff(array_keys($attributes), ['created_at']));
                $upsertAttributes = $attributes;

                foreach (['media_urls', 'tags', 'metadata', 'safety_reasons'] as $jsonColumn) {
                    $upsertAttributes[$jsonColumn] = json_encode($upsertAttributes[$jsonColumn], JSON_THROW_ON_ERROR);
                }

                ContentPost::query()->upsert([$upsertAttributes], $identity, $updates);
                $content = ContentPost::query()
                    ->where($post->externalId ? 'instagram_media_id' : 'source_url', $post->externalId ?: $post->sourceUrl)
                    ->firstOrFail();
            }

            $lifecycle->recordRefresh($content, $capturedAt);

            return $content;
        });
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
                'safety_policy_version' => ContentSafetyPolicy::VERSION,
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
                        'why_it_works' => $this->whyItWorks(
                            $post,
                            $outlier,
                            $performance->against($baselines, $post->format)['format'],
                        ),
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

    private function whyItWorks(ContentPost $post, float $outlier, ?string $comparedFormat): string
    {
        // Naming the format matters: the number only means something once you know
        // it is this account's usual Reel, not its usual anything.
        $normal = $comparedFormat
            ? 'this account normally gets on a '.$comparedFormat
            : 'this account normally gets';

        if ($outlier < 1) {
            return 'A steady post for this account, below the engagement its audience usually gives it.';
        }

        return 'This one reached '.round($outlier, 1).'× the engagement '.$normal.', on '
            .number_format($post->likes).' likes and '.number_format($post->comments).' comments.';
    }
}
