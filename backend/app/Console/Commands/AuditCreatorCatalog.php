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

class AuditCreatorCatalog extends Command
{
    protected $signature = 'personal:audit-creator-catalog
        {--market= : Audit one market}
        {--vertical= : Audit one canonical vertical}
        {--provider=scrapecreators : Instagram provider}';

    protected $description = 'Audit the versioned creator catalog without writing to the database';

    public function handle(
        CreatorCatalog $catalog,
        InstagramDataProviderManager $providers,
        CreatorCatalogEligibility $eligibility,
        CreatorCatalogReportWriter $reports,
    ): int {
        $entries = collect($catalog->entries())
            ->when($this->option('market'), fn ($items) => $items->where('market', strtoupper((string) $this->option('market'))))
            ->when($this->option('vertical'), fn ($items) => $items->where('vertical', $this->option('vertical')))
            ->values();
        $provider = $providers->provider((string) $this->option('provider'));
        $rows = [];

        $this->withProgressBar($entries, function (array $entry) use ($provider, $eligibility, &$rows): void {
            try {
                $profile = $provider->getProfile((string) $entry['handle']);
                $rows[] = $profile
                    ? $eligibility->evaluate($this->withPosts($profile, $provider), $entry)
                    : $this->missing($entry);
            } catch (ContentDiscoveryException) {
                $rows[] = $this->missing($entry, 'provider_failure');
            }
        });
        $this->newLine(2);

        $summary = $this->summary($rows);
        $paths = $reports->write('creator-catalog-audit', $rows, $summary);
        $this->table(['Audited', 'Accepted', 'Rejected'], [[$summary['audited'], $summary['accepted'], $summary['rejected']]]);
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

    private function missing(array $entry, string $reason = 'profile_not_found'): array
    {
        return [
            'handle' => $entry['handle'], 'status' => $entry['status'], 'accepted' => false,
            'reasons' => [$reason], 'expected_market' => $entry['market'],
            'detected_market' => null, 'market_confidence' => 0, 'primary_language' => 'unknown',
            'vertical' => $entry['vertical'], 'expected_tier' => $entry['recognition_tier'],
            'detected_tier' => null, 'followers' => 0, 'recent_posts' => 0,
            'latest_post_at' => null, 'metric_coverage' => 0, 'median_engagement' => 0,
            'instagram_user_id' => null, 'display_name' => null, 'bio' => null,
        ];
    }

    private function summary(array $rows): array
    {
        $accepted = collect($rows)->where('accepted', true);

        return [
            'audited' => count($rows),
            'accepted' => $accepted->count(),
            'rejected' => count($rows) - $accepted->count(),
            'quota_targets' => ['per_vertical' => 20, 'markets' => ['FR' => 10, 'GB' => 5, 'US' => 5], 'tiers' => ['leader' => 4, 'established' => 10, 'expert' => 6]],
            'accepted_quota_coverage' => $accepted->groupBy('vertical')->map(fn ($vertical): array => [
                'total' => $vertical->count(),
                'markets' => $vertical->countBy('expected_market')->all(),
                'tiers' => $vertical->countBy('expected_tier')->all(),
            ])->all(),
        ];
    }
}
