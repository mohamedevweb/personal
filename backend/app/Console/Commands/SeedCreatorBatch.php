<?php

namespace App\Console\Commands;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\InstagramDataProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SplFileObject;
use Throwable;

class SeedCreatorBatch extends Command
{
    protected $signature = 'personal:seed-creator-batch
        {file : Absolute or project-relative path to the creator CSV}
        {--batch=1 : One-based batch number}
        {--size=20 : CSV rows per batch}
        {--provider=scrapecreators : Instagram provider}
        {--dry-run : Preview the selected rows without provider or database writes}';

    protected $description = 'Seed one CSV batch of creators and only their latest Instagram post';

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'Verticale',
        'Marché',
        'Handle',
        'Créateur',
        'Followers ≈',
        'Micro-niches',
        'Safety',
    ];

    public function handle(InstagramDataProviderManager $providers): int
    {
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT);
        $size = filter_var($this->option('size'), FILTER_VALIDATE_INT);

        if ($batch === false || $batch < 1 || $size === false || $size < 1 || $size > 100) {
            $this->error('Batch must be at least 1 and size must be between 1 and 100.');

            return self::FAILURE;
        }

        try {
            $path = $this->path((string) $this->argument('file'));
            $rows = $this->rows($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $selected = array_slice($rows, ($batch - 1) * $size, $size);

        if ($selected === []) {
            $this->error("Batch {$batch} is outside the {$this->batchCount(count($rows), $size)} available batches.");

            return self::FAILURE;
        }

        $this->table(
            ['CSV row', 'Handle', 'Creator', 'Vertical', 'Market', 'Safety'],
            array_map(fn (array $row): array => [
                $row['row'], $row['handle'], $row['creator'], $row['vertical'], $row['market'], $row['safety'],
            ], $selected),
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run only. Selected '.count($selected)." rows from batch {$batch} of {$this->batchCount(count($rows), $size)}.");

            return self::SUCCESS;
        }

        try {
            $provider = $providers->provider((string) $this->option('provider'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $handles = array_values(array_unique(array_column($selected, 'handle')));
        $marketHints = collect($selected)
            ->filter(fn (array $row): bool => $row['market'] === 'FR')
            ->mapWithKeys(fn (array $row): array => [$row['handle'] => 'FR'])
            ->all();
        $job = new MeasureAccountEngagement($handles, latestOnly: true, marketHints: $marketHints);

        app()->call([$job, 'handle'], ['provider' => $provider]);

        $ready = [];
        $skipped = [];

        foreach ($selected as $row) {
            $creator = Creator::query()->where('username', $row['handle'])->first();
            $hasSafePost = $creator?->posts()
                ->where('safety_status', ContentSafetyDecision::ALLOWED)
                ->exists() ?? false;

            if (! $creator || ! $hasSafePost || $creator->safety_status !== ContentSafetyDecision::ALLOWED
                || ! in_array($creator->market, config('creator_catalog.markets'), true)) {
                $skipped[] = $row['handle'];

                continue;
            }

            $creator->forceFill([
                'curation_status' => 'approved',
                'is_catalog_seed' => true,
                'metadata' => array_replace_recursive($creator->metadata ?? [], [
                    'seed' => [
                        'source_file' => basename($path),
                        'source_row' => $row['row'],
                        'batch' => $batch,
                        'vertical' => $row['vertical'],
                        'market_group' => $row['market'],
                        'micro_niches' => $row['micro_niches'],
                        'safety_label' => $row['safety'],
                    ],
                ]),
            ])->save();
            $ready[] = $creator->username;
        }

        $this->table(['Creators ready', 'Latest posts', 'Skipped'], [[
            count(array_unique($ready)),
            Creator::query()->whereIn('username', array_unique($ready))->whereHas('posts')->count(),
            count(array_unique($skipped)),
        ]]);

        if ($skipped !== []) {
            $this->warn('Skipped because the profile, market, or a safe latest post could not be verified: '.implode(', ', array_unique($skipped)));
        }

        return self::SUCCESS;
    }

    private function path(string $path): string
    {
        $resolved = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);

        if (! is_file($resolved) || ! is_readable($resolved)) {
            throw new \InvalidArgumentException("Creator CSV is not readable at [{$resolved}].");
        }

        return $resolved;
    }

    /** @return list<array{row: int, vertical: string, market: string, handle: string, creator: string, micro_niches: list<string>, safety: string}> */
    private function rows(string $path): array
    {
        $file = new SplFileObject($path, 'r');
        $header = $file->fgetcsv(',', '"', '');

        if (! is_array($header)) {
            throw new \InvalidArgumentException('Creator CSV has no header row.');
        }

        $header = array_map(fn (mixed $value): string => trim((string) $value), $header);
        $header[0] = Str::remove("\u{FEFF}", $header[0]);

        if ($header !== self::REQUIRED_HEADERS) {
            throw new \InvalidArgumentException('Creator CSV headers do not match the expected export.');
        }

        $rows = [];
        $line = 1;

        while (! $file->eof()) {
            $values = $file->fgetcsv(',', '"', '');
            $line++;

            if (! is_array($values) || $values === [null] || count($values) !== count($header)) {
                continue;
            }

            $row = array_combine($header, array_map(fn (mixed $value): string => trim((string) $value), $values));
            $handle = mb_strtolower(ltrim($row['Handle'], '@'));

            if (! preg_match('/^[a-z0-9._]{1,30}$/', $handle)) {
                throw new \InvalidArgumentException("Creator CSV row {$line} has an invalid Instagram handle.");
            }

            if (! in_array($row['Marché'], ['FR', 'UK/US'], true)) {
                throw new \InvalidArgumentException("Creator CSV row {$line} has an unsupported market group.");
            }

            $rows[] = [
                'row' => $line,
                'vertical' => $row['Verticale'],
                'market' => $row['Marché'],
                'handle' => $handle,
                'creator' => $row['Créateur'],
                'micro_niches' => array_values(array_filter(array_map('trim', explode('/', $row['Micro-niches'])))),
                'safety' => $row['Safety'],
            ];
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('Creator CSV contains no data rows.');
        }

        return $rows;
    }

    private function batchCount(int $rows, int $size): int
    {
        return (int) ceil($rows / $size);
    }
}
