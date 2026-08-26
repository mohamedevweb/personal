<?php

namespace App\Jobs\Discovery;

use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\CreatorMarketDetector;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Instagram\NicheDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Reads the public profile behind the handle a creator gives at onboarding, so
 * Personal already knows who they are the first time they open the app. The
 * connected-account path does the same work from the Meta API in
 * SyncInstagramAccount; this one only ever sees what any visitor can see.
 *
 * Onboarding waits on this job, so every step it goes through is written to the
 * profile as it happens: the creator watches their own profile, posts, voice and
 * audience being read instead of an unexplained spinner.
 */
class AnalyzeCreatorHandle implements ShouldQueue
{
    use Queueable;

    /** How many recent posts the analysis reads — and announces it is reading. */
    public const POSTS_READ = 30;

    /**
     * The steps the loader shows, in the order they run. 'queued' precedes them,
     * 'completed' and 'failed' end them.
     *
     * @var list<string>
     */
    public const STAGES = ['reading_profile', 'importing_posts', 'reading_voice', 'mapping_audience'];

    public int $tries = 2;

    /** One profile call, then a language model pass over the captions. */
    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [30, 180];

    public function __construct(public readonly int $userId) {}

    public function handle(
        InstagramDataProvider $instagram,
        NicheDetectionService $niche,
        CreatorMarketDetector $markets,
        CanonicalCreatorVerticals $verticals,
    ): void {
        $profile = CreatorProfile::query()->where('user_id', $this->userId)->first();
        $username = $profile?->instagram_username;

        if (! $profile || ! $username) {
            return;
        }

        // A connected account gets the authenticated import instead, which sees
        // insights this scrape cannot. Nothing is left to wait for here.
        if (InstagramAccount::query()->where('user_id', $this->userId)->exists()) {
            $this->stage($profile, 'completed');

            return;
        }

        $this->stage($profile, 'reading_profile', started: true);

        $scraped = $instagram->getProfile($username);

        if (! $scraped) {
            $this->stop($profile, 'profile_not_found');

            return;
        }

        if ($scraped->isPrivate) {
            $this->stop($profile, 'private_account');

            return;
        }

        // The counters land before the language model runs, so the loader can
        // show the bio and the follower count while it reads the captions.
        $profile->forceFill([
            'display_name' => $profile->display_name ?: $scraped->displayName,
            'bio' => $scraped->bio,
            'followers_count' => $scraped->followers,
        ])->save();

        $this->stage($profile, 'importing_posts');

        $posts = $this->recentPosts($instagram, $scraped->username, $scraped->posts);
        $profile->forceFill(['analyzed_posts_count' => $posts->count()])->save();

        $this->stage($profile, 'reading_voice');

        // detect() reads an account's name, bio and website, so the scraped
        // profile stands in for one. It is never saved.
        $account = new InstagramAccount([
            'username' => $scraped->username,
            'display_name' => $scraped->displayName,
            'bio' => $scraped->bio,
        ]);
        $media = $posts
            ->map(fn (DiscoveredPost $post): array => ['caption' => $post->caption])
            ->all();

        $signals = $niche->detect($account, $media);

        $this->stage($profile, 'mapping_audience');

        $market = $markets->detect(implode("\n", [
            $scraped->displayName ?? '',
            $scraped->bio ?? '',
            $posts->pluck('caption')->filter()->implode("\n"),
        ]));

        $profile->fill([
            'market' => $market['market'],
            'market_confidence' => $market['confidence'],
        ]);

        // A memory the creator wrote themselves outranks anything read off their
        // profile, so an edited one is only ever added to, never replaced.
        if (data_get($profile->creator_dna, 'analysis_method') !== 'manual') {
            $profile->fill([
                'niche' => $signals['primary_niche'],
                'positioning' => $signals['primary_niche'] ? $scraped->bio : null,
                'topics' => $signals['topics'],
                'tone' => $signals['tone'],
                'voice_profile' => $signals['voice_profile'],
                'audience_description' => $signals['audience'] === [] ? null : implode(', ', $signals['audience']),
                'creator_dna' => $signals,
                'primary_vertical' => $verticals->fromSignals([
                    $signals['primary_niche'],
                    ...$signals['sub_niches'],
                    ...$signals['topics'],
                ]),
                'dna_analyzed_at' => now(),
            ]);
        }

        $profile->fill(['analysis_status' => 'completed', 'analysis_error' => null])->save();

        // The niche is what the feed is built from, so fill it as soon as there
        // is one. Its own job, so a scraper hiccup never fails this one.
        if ($profile->niche) {
            DiscoverNicheContent::dispatch($this->userId);
        }
    }

    /**
     * The last posts the analysis reads. A profile call returns a shorter grid
     * than the window Personal reads, so the rest is asked for separately.
     *
     * @param  Collection<int, DiscoveredPost>  $fromProfile
     * @return Collection<int, DiscoveredPost>
     */
    private function recentPosts(
        InstagramDataProvider $instagram,
        string $username,
        Collection $fromProfile,
    ): Collection {
        $posts = $fromProfile->take(self::POSTS_READ);

        if ($posts->count() >= self::POSTS_READ) {
            return $posts->values();
        }

        try {
            $fetched = $instagram->getPosts($username, self::POSTS_READ);
        } catch (Throwable) {
            // The grid from the profile call is already enough to read a voice
            // from, so a missing second call never costs the creator the step.
            return $posts->values();
        }

        return $posts->concat($fetched)
            ->unique(fn (DiscoveredPost $post): string => $post->externalId ?: $post->sourceUrl)
            ->take(self::POSTS_READ)
            ->values();
    }

    /** Moves the loader to the next step. */
    private function stage(CreatorProfile $profile, string $status, bool $started = false): void
    {
        $profile->forceFill([
            'analysis_status' => $status,
            'analysis_error' => null,
            ...($started ? ['analysis_started_at' => now()] : []),
        ])->save();
    }

    /**
     * Ends the analysis on something the creator can act on. The reason is a
     * stable key, so onboarding can say it in the creator's own language.
     */
    private function stop(CreatorProfile $profile, string $reason): void
    {
        $profile->forceFill([
            'analysis_status' => 'failed',
            'analysis_error' => $reason,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        CreatorProfile::query()
            ->where('user_id', $this->userId)
            ->update(['analysis_status' => 'failed', 'analysis_error' => 'analysis_unavailable']);
    }
}
