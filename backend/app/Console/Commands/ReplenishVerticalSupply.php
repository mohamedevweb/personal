<?php

namespace App\Console\Commands;

use App\Jobs\Discovery\DiscoverNicheContent;
use App\Models\ContentPost;
use App\Models\CreatorProfile;
use App\Services\Discovery\ContentSafetyDecision;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ReplenishVerticalSupply extends Command
{
    protected $signature = 'personal:replenish-vertical-supply
        {--vertical= : Only replenish one canonical vertical}
        {--force : Ignore the discovery cooldown}
        {--dry-run : Report gaps without dispatching discovery jobs}';

    protected $description = 'Queue Creator-DNA discovery for active verticals with too little feed inventory';

    public function handle(): int
    {
        if (! config('services.discovery.vertical_supply.enabled')) {
            $this->info('Vertical supply replenishment is disabled.');

            return self::SUCCESS;
        }

        $requestedVertical = $this->option('vertical');
        $verticals = array_keys((array) config('creator_catalog.verticals'));

        if ($requestedVertical !== null && ! in_array($requestedVertical, $verticals, true)) {
            $this->error("Unknown canonical vertical [{$requestedVertical}].");

            return self::FAILURE;
        }

        $profiles = CreatorProfile::query()
            ->whereNotNull('primary_vertical')
            ->whereIn('primary_vertical', $requestedVertical ? [$requestedVertical] : $verticals)
            ->orderByRaw('CASE WHEN discovery_refreshed_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('discovery_refreshed_at')
            ->limit(max(1, (int) config('services.discovery.vertical_supply.batch')))
            ->get(['user_id', 'primary_vertical', 'discovery_refreshed_at'])
            ->groupBy('primary_vertical')
            ->map(fn ($profiles) => $profiles->first())
            ->values();

        $queued = 0;
        $skippedCooldown = 0;
        $rows = [];

        foreach ($profiles as $profile) {
            $supply = $this->supply($profile->primary_vertical);
            $needsSupply = $supply['posts'] < (int) config('services.discovery.vertical_supply.minimum_posts')
                || $supply['creators'] < (int) config('services.discovery.vertical_supply.minimum_creators');
            $cooldownActive = $profile->discovery_refreshed_at?->isAfter(
                now()->subDays((int) config('services.discovery.vertical_supply.cooldown_days')),
            );

            if (! $needsSupply) {
                continue;
            }

            if ($cooldownActive && ! $this->option('force')) {
                $skippedCooldown++;
            } elseif (! $this->option('dry-run')) {
                DiscoverNicheContent::dispatch($profile->user_id, force: true);
                $queued++;
            }

            $action = $cooldownActive && ! $this->option('force')
                ? 'cooldown'
                : ($this->option('dry-run') ? 'dry-run' : 'queued');

            $rows[] = [
                $profile->primary_vertical,
                $supply['posts'],
                $supply['creators'],
                $action,
            ];
        }

        if ($rows !== []) {
            $this->table(['Vertical', 'Eligible posts', 'Creators', 'Action'], $rows);
        }

        $this->info("{$queued} discovery job(s) queued, {$skippedCooldown} gap(s) still on cooldown.");

        return self::SUCCESS;
    }

    /** @return array{posts: int, creators: int} */
    private function supply(string $vertical): array
    {
        $query = ContentPost::query()
            ->where('safety_status', ContentSafetyDecision::ALLOWED)
            ->whereNotNull('measured_at')
            ->where('published_at', '>=', now()->subDays((int) config('services.discovery.feed_window_days')))
            ->where('outlier_score', '>=', (float) config('services.discovery.fallback_min_outlier_score'))
            ->whereRaw('likes + comments >= ?', [(int) config('services.discovery.min_post_engagement')])
            ->whereHas('creator', function (Builder $creator) use ($vertical): void {
                $creator->where('primary_vertical', $vertical)
                    ->where('followers', '>=', (int) config('services.discovery.min_followers'))
                    ->whereIn('market', config('creator_catalog.markets'))
                    ->where('safety_status', ContentSafetyDecision::ALLOWED);

                if (config('creator_catalog.curated_only')) {
                    $creator->where('curation_status', 'approved');
                }
            });

        return [
            'posts' => (clone $query)->count(),
            'creators' => (clone $query)->distinct()->count('creator_id'),
        ];
    }
}
