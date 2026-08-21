<?php

namespace App\Console\Commands;

use App\Exceptions\ContentDiscoveryException;
use App\Services\Discovery\CreatorCatalog;
use App\Services\Discovery\CreatorCatalogEligibility;
use App\Services\Discovery\CreatorCatalogReportWriter;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AuditCreatorCatalog extends Command
{
    protected $signature = 'personal:audit-creator-catalog
        {--market= : Audit one market}
        {--vertical= : Audit one canonical vertical}
        {--handle=* : Audit one or more exact handles}
        {--retry-report= : Retry only provider errors from a previous JSON report}
        {--provider=scrapecreators : Instagram provider}';

    protected $description = 'Audit the versioned creator catalog without writing to the database';

    public function handle(
        CreatorCatalog $catalog,
        InstagramDataProviderManager $providers,
        CreatorCatalogEligibility $eligibility,
        CreatorCatalogReportWriter $reports,
    ): int {
        $handles = collect((array) $this->option('handle'))
            ->filter(fn (mixed $handle): bool => is_string($handle) && trim($handle) !== '')
            ->map(fn (string $handle): string => strtolower(ltrim($handle, '@')))
            ->flip();
        $entries = collect($catalog->entries())
            ->when($this->option('market'), fn ($items) => $items->where('market', strtoupper((string) $this->option('market'))))
            ->when($this->option('vertical'), fn ($items) => $items->where('vertical', $this->option('vertical')))
            ->when($handles->isNotEmpty(), fn ($items) => $items->filter(
                fn (array $entry): bool => $handles->has(strtolower(ltrim((string) $entry['handle'], '@'))),
            ))
            ->values();

        try {
            $entries = $this->onlyPreviousProviderErrors($entries, $this->option('retry-report'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($entries->isEmpty()) {
            $this->info('No provider errors to retry.');

            return self::SUCCESS;
        }

        $provider = $providers->provider((string) $this->option('provider'));
        $rows = [];

        $this->withProgressBar($entries, function (array $entry) use ($provider, $eligibility, &$rows): void {
            try {
                $profile = $provider->getProfile((string) $entry['handle']);
                $rows[] = $profile
                    ? $eligibility->evaluate($this->withPosts($profile, $provider), $entry)
                    : $this->missing($entry);
            } catch (ContentDiscoveryException $exception) {
                $rows[] = $this->providerError($entry, $exception);
            }
        });
        $this->newLine(2);

        $summary = $this->summary($rows);
        $paths = $reports->write('creator-catalog-audit', $rows, $summary);
        $this->table(['Audited', 'Accepted', 'Rejected', 'Provider errors'], [[
            $summary['audited'], $summary['accepted'], $summary['rejected'], $summary['provider_errors'],
        ]]);
        $this->line("JSON: {$paths['json']}");
        $this->line("CSV: {$paths['csv']}");

        return self::SUCCESS;
    }

    private function withPosts(DiscoveredProfile $profile, InstagramDataProvider $provider): DiscoveredProfile
    {
        if ($profile->isPrivate || ! $this->needsMorePosts($profile)) {
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

    private function missing(array $entry, string $reason = 'profile_not_found'): array
    {
        return [
            'handle' => $entry['handle'], 'status' => $entry['status'],
            'provider_status' => 'not_found', 'provider_error' => null, 'accepted' => false,
            'reasons' => [$reason], 'warnings' => [], 'suggestions' => [], 'expected_market' => $entry['market'],
            'detected_market' => null, 'market_confidence' => 0, 'primary_language' => 'unknown',
            'vertical' => $entry['vertical'], 'expected_tier' => $entry['recognition_tier'] ?? null,
            'detected_tier' => null, 'followers' => null, 'recent_posts' => null,
            'latest_post_at' => null, 'metric_coverage' => null, 'median_engagement' => null,
            'instagram_user_id' => null, 'display_name' => null, 'bio' => null,
        ];
    }

    private function providerError(array $entry, ContentDiscoveryException $exception): array
    {
        return array_replace($this->missing($entry, 'provider_failure'), [
            'provider_status' => 'error',
            'provider_error' => $exception->getMessage(),
            'accepted' => null,
        ]);
    }

    private function summary(array $rows): array
    {
        $results = collect($rows);
        $accepted = $results->filter(fn (array $row): bool => $row['accepted'] === true);
        $errors = $results->where('provider_status', 'error');

        return [
            'audited' => count($rows),
            'accepted' => $accepted->count(),
            'rejected' => $results->filter(fn (array $row): bool => $row['accepted'] === false)->count(),
            'provider_errors' => $errors->count(),
            'quota_targets' => [
                'total' => (int) config('creator_catalog.target_total'),
                'per_vertical' => (int) config('creator_catalog.target_per_vertical'),
                'market' => 'FR',
            ],
            'accepted_quota_coverage' => $accepted->groupBy('vertical')->map(fn ($vertical): array => [
                'total' => $vertical->count(),
                'tiers' => $vertical->countBy('detected_tier')->all(),
            ])->all(),
        ];
    }

    private function needsMorePosts(DiscoveredProfile $profile): bool
    {
        return $profile->posts->count() < (int) config('creator_catalog.audit.min_posts');
    }

    private function onlyPreviousProviderErrors(Collection $entries, mixed $reportPath): Collection
    {
        if (! is_string($reportPath) || trim($reportPath) === '') {
            return $entries;
        }

        $contents = @file_get_contents($reportPath);
        $report = $contents === false ? null : json_decode($contents, true);
        if (! is_array($report) || ! is_array($report['entries'] ?? null)) {
            throw new InvalidArgumentException("Retry report [{$reportPath}] is not a readable catalog audit JSON file.");
        }

        $failedHandles = collect($report['entries'])
            ->filter(fn (mixed $row): bool => is_array($row) && (
                ($row['provider_status'] ?? null) === 'error'
                || in_array('provider_failure', (array) ($row['reasons'] ?? []), true)
            ))
            ->pluck('handle')
            ->map(fn (mixed $handle): string => strtolower(ltrim((string) $handle, '@')))
            ->flip();

        return $entries
            ->filter(fn (array $entry): bool => $failedHandles->has(strtolower(ltrim((string) $entry['handle'], '@'))))
            ->values();
    }
}
