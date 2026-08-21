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
                $row = $eligibility->evaluate($full, $seed);
                $row['handle'] = $handle;
                $row['source_seed'] = $seed['handle'];
                $row['status'] = 'candidate';
                $row['candidate_score'] = $this->candidateScore($row);
                $candidates[$handle] = $row;

                if (count($candidates) >= (int) $this->option('max')) {
                    break 2;
                }
            }
        }

        $rows = collect($candidates)
            ->sortByDesc('candidate_score')
            ->values()
            ->all();
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

    private function candidateScore(array $row): float
    {
        $activity = min(1, ((int) ($row['recent_posts'] ?? 0)) / 12);
        $metrics = (float) ($row['metric_coverage'] ?? 0);
        $engagement = min(1, log10(max(1, (int) ($row['median_engagement'] ?? 0))) / 5);

        return round(($activity * 0.35) + ($metrics * 0.25) + ($engagement * 0.30) + 0.10, 4);
    }
}
