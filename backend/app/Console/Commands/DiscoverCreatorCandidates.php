<?php

namespace App\Console\Commands;

use App\Services\Discovery\CreatorCatalog;
use App\Services\Discovery\CreatorCatalogEligibility;
use App\Services\Discovery\CreatorCatalogReportWriter;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use Illuminate\Console\Command;

class DiscoverCreatorCandidates extends Command
{
    protected $signature = 'personal:discover-creator-candidates
        {--provider=scrapecreators : Instagram provider}
        {--per-seed=3 : Related accounts requested per seed}
        {--max=100 : Maximum unique candidates to enrich}';

    protected $description = 'Export similar creator candidates without writing discovery data to the database';

    public function handle(
        CreatorCatalog $catalog,
        InstagramDataProviderManager $providers,
        CreatorCatalogEligibility $eligibility,
        CreatorCatalogReportWriter $reports,
    ): int {
        $seeds = $catalog->approved();

        if ($seeds === []) {
            $this->warn('No approved seeds are available.');

            return self::SUCCESS;
        }

        $provider = $providers->provider((string) $this->option('provider'));
        $known = collect($catalog->entries())->pluck('handle')->map(fn (string $handle): string => strtolower(ltrim($handle, '@')))->flip();
        $candidates = [];

        foreach ($seeds as $seed) {
            $profile = $provider->getProfile((string) $seed['handle']);
            if (! $profile?->externalId) {
                continue;
            }

            foreach ($provider->getRelatedAccounts($profile->externalId, (int) $this->option('per-seed'), $profile->username) as $candidate) {
                $handle = strtolower(ltrim($candidate->username, '@'));
                if ($known->has($handle) || isset($candidates[$handle])) {
                    continue;
                }

                $full = $this->withPosts($provider->getProfile($handle) ?? $candidate, $provider);
                $expectation = array_replace($seed, ['recognition_tier' => $eligibility->tier($full->followers)]);
                $row = $eligibility->evaluate($full, $expectation);
                $row['handle'] = $handle;
                $row['source_seed'] = $seed['handle'];
                $row['status'] = 'candidate';
                $candidates[$handle] = $row;

                if (count($candidates) >= (int) $this->option('max')) {
                    break 2;
                }
            }
        }

        $rows = array_values($candidates);
        $paths = $reports->write('creator-candidates', $rows, ['candidates' => count($rows), 'database_writes' => 0]);
        $this->info(count($rows).' candidates exported. No creator or content row was written.');
        $this->line("JSON: {$paths['json']}");
        $this->line("CSV: {$paths['csv']}");

        return self::SUCCESS;
    }

    private function withPosts(DiscoveredProfile $profile, InstagramDataProvider $provider): DiscoveredProfile
    {
        if ($profile->isPrivate) {
            return $profile;
        }

        $posts = $profile->posts
            ->concat($provider->getPosts($profile->username, 30, $profile->externalId))
            ->unique(fn ($post): string => $post->externalId ?: $post->sourceUrl)
            ->values();

        return new DiscoveredProfile(
            username: $profile->username,
            displayName: $profile->displayName,
            avatarUrl: $profile->avatarUrl,
            followers: $profile->followers,
            posts: $posts,
            bio: $profile->bio,
            externalId: $profile->externalId,
            isPrivate: $profile->isPrivate,
            metadata: $profile->metadata,
        );
    }
}
