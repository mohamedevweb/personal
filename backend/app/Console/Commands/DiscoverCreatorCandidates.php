<?php

namespace App\Console\Commands;

use App\Models\Creator;
use App\Services\Discovery\CreatorCandidateFinder;
use App\Services\Discovery\CreatorCatalog;
use App\Services\Discovery\CreatorCatalogEligibility;
use App\Services\Discovery\CreatorCatalogReportWriter;
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
        CreatorCandidateFinder $finder,
        CreatorCatalogReportWriter $reports,
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

        $rows = collect($pairs)->pluck('row')->all();
        $paths = $reports->write('creator-candidates', $rows, ['candidates' => count($rows), 'database_writes' => 0]);
        $this->info(count($rows).' candidates exported. No creator or content row was written.');
        $this->line("JSON: {$paths['json']}");
        $this->line("CSV: {$paths['csv']}");

        return self::SUCCESS;
    }
}
