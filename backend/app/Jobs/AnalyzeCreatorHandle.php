<?php

namespace App\Jobs;

use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\CreatorMarketDetector;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Instagram\NicheDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads the public profile behind the handle a creator gives at onboarding, so
 * Personal already knows who they are the first time they open the app. The
 * connected-account path does the same work from the Meta API in
 * SyncInstagramAccount; this one only ever sees what any visitor can see.
 */
class AnalyzeCreatorHandle implements ShouldQueue
{
    use Queueable;

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
        // insights this scrape cannot.
        if (InstagramAccount::query()->where('user_id', $this->userId)->exists()) {
            return;
        }

        $scraped = $instagram->getProfile($username);

        if (! $scraped || $scraped->isPrivate) {
            return;
        }

        // detect() reads an account's name, bio and website, so the scraped
        // profile stands in for one. It is never saved.
        $account = new InstagramAccount([
            'username' => $scraped->username,
            'display_name' => $scraped->displayName,
            'bio' => $scraped->bio,
        ]);
        $media = $scraped->posts
            ->map(fn (DiscoveredPost $post): array => ['caption' => $post->caption])
            ->all();

        $signals = $niche->detect($account, $media);
        $market = $markets->detect(implode("\n", [
            $scraped->displayName ?? '',
            $scraped->bio ?? '',
            $scraped->posts->pluck('caption')->filter()->take(30)->implode("\n"),
        ]));

        $profile->fill([
            'display_name' => $profile->display_name ?: $scraped->displayName,
            'bio' => $scraped->bio,
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

        $profile->save();

        // The niche is what the feed is built from, so fill it as soon as there
        // is one. Its own job, so a scraper hiccup never fails this one.
        if ($profile->niche) {
            DiscoverNicheContent::dispatch($this->userId);
        }
    }
}
