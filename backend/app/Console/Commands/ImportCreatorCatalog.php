<?php

namespace App\Console\Commands;

use App\Services\Discovery\CreatorCatalog;
use App\Services\Discovery\CreatorCatalogImporter;
use App\Services\Discovery\InstagramDataProviderManager;
use Illuminate\Console\Command;

class ImportCreatorCatalog extends Command
{
    protected $signature = 'personal:import-creator-catalog {--provider=scrapecreators : Instagram provider}';

    protected $description = 'Import only human-approved entries from the versioned creator catalog';

    public function handle(CreatorCatalog $catalog, InstagramDataProviderManager $providers, CreatorCatalogImporter $importer): int
    {
        $entries = $catalog->approved();

        if ($entries === []) {
            $this->warn('No approved entries. Review the audit reports and update the manifest first.');

            return self::SUCCESS;
        }

        $result = $importer->import($entries, $providers->provider((string) $this->option('provider')));
        $this->table(['Imported', 'Skipped', 'Measurement jobs'], [[
            $result['imported'], count($result['skipped']), $result['jobs'],
        ]]);

        if ($result['skipped'] !== []) {
            $this->warn('Missing profiles: '.implode(', ', $result['skipped']));
        }

        return self::SUCCESS;
    }
}
