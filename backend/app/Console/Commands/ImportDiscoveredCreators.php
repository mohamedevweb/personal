<?php

namespace App\Console\Commands;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use App\Services\Discovery\CreatorCandidateFinder;
use App\Services\Discovery\CreatorCatalog;
use App\Services\Discovery\CreatorCatalogEligibility;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\InstagramDataProviderManager;
use Illuminate\Console\Command;

class ImportDiscoveredCreators extends Command
{
    protected $signature = 'personal:import-discovered-creators
        {--provider=scrapecreators : Instagram provider}
        {--per-seed=3 : Related accounts requested per seed}
        {--max=25 : Maximum new creators to import per run}';

    protected $description = 'Import eligible, previously unseen creators discovered from approved catalog seeds as curation_status=discovered';

    public function handle(
        CreatorCatalog $catalog,
        InstagramDataProviderManager $providers,
        CreatorCatalogEligibility $eligibility,
        CreatorCandidateFinder $finder,
    ): int {
        $seeds = $catalog->approved();

        if ($seeds === []) {
            $this->warn('No approved seeds are available.');

            return self::SUCCESS;
        }

        $provider = $providers->provider((string) $this->option('provider'));
        $known = collect($catalog->entries())->pluck('handle')
            ->merge(Creator::query()->pluck('username'))
            ->map(fn (string $handle): string => strtolower(ltrim($handle, '@')))
            ->flip();

        $pairs = $finder->find(
            $provider,
            $eligibility,
            $seeds,
            $known,
            (int) $this->option('per-seed'),
            (int) $this->option('max'),
        );

        $allowedMarkets = config('creator_catalog.markets');
        $imported = [];
        $rejected = 0;

        foreach ($pairs as $pair) {
            $row = $pair['row'];
            $profile = $pair['profile'];

            if (! $row['accepted']) {
                $rejected++;

                continue;
            }

            $market = $row['market_confidence'] >= 0.70 && $row['detected_market']
                ? $row['detected_market']
                : $row['expected_market'];

            if (! in_array($market, $allowedMarkets, true)) {
                $rejected++;

                continue;
            }

            Creator::query()->firstOrCreate(
                ['username' => $row['handle']],
                [
                    'instagram_user_id' => $row['instagram_user_id'],
                    'display_name' => $row['display_name'] ?: $row['handle'],
                    'avatar_url' => $profile->avatarUrl,
                    'bio' => $row['bio'],
                    'followers' => $row['followers'],
                    'average_views' => (int) $profile->posts->avg(fn (DiscoveredPost $post): int => $post->views),
                    'average_likes' => (int) $profile->posts->avg(fn (DiscoveredPost $post): int => $post->likes),
                    'niche' => $row['vertical'],
                    'niche_topics' => [],
                    'market' => $market,
                    'curation_status' => 'discovered',
                    'is_catalog_seed' => false,
                    'discovered_at' => now(),
                    'last_fetched_at' => now(),
                    'metrics_updated_at' => now(),
                    'metadata' => [
                        'discovery' => [
                            'source_seed' => $row['source_seed'],
                            'candidate_score' => $row['candidate_score'],
                            'discovered_at' => now()->toIso8601String(),
                        ],
                    ],
                ],
            );

            $imported[] = $row['handle'];
        }

        $jobs = 0;
        foreach (array_chunk(array_values(array_unique($imported)), 10) as $handles) {
            MeasureAccountEngagement::dispatch($handles);
            $jobs++;
        }

        $this->table(['Candidates found', 'Imported', 'Rejected', 'Measurement jobs'], [[
            count($pairs), count($imported), $rejected, $jobs,
        ]]);

        return self::SUCCESS;
    }
}
