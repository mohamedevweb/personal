<?php

namespace App\Console\Commands;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Jobs\Discovery\RefreshCreatorPostMetrics;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\CreatorNicheService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class DispatchAdaptiveInstagramScrapes extends Command
{
    protected $signature = 'personal:dispatch-instagram-scrapes';

    protected $description = 'Queue only creator and post metric scrapes that are currently due';

    public function handle(): int
    {
        $now = now();
        $dueCreators = Creator::query()
            ->whereIn('market', config('creator_catalog.markets'))
            ->when(config('creator_catalog.curated_only'), fn ($query) => $query->where('curation_status', 'approved'))
            ->where('safety_status', '!=', ContentSafetyDecision::BLOCKED)
            ->where(fn (Builder $query): Builder => $this->performing($query, $now))
            ->where(function ($query) use ($now): void {
                $query->where('next_scrape_at', '<=', $now)
                    ->orWhere(function ($query): void {
                        $query->where('is_catalog_seed', false)
                            ->where('niche_analysis_version', '<', CreatorNicheService::ANALYSIS_VERSION);
                    });
            })
            ->orderByDesc('scrape_priority')
            ->orderBy('next_scrape_at')
            ->limit((int) config('instagram_scraping.creator_batch'))
            ->get(['id', 'username']);

        foreach ($dueCreators as $creator) {
            MeasureAccountEngagement::dispatch([$creator->username]);
        }

        $metricCreators = Creator::query()
            ->whereIn('market', config('creator_catalog.markets'))
            ->whereNotIn('id', $dueCreators->pluck('id'))
            ->where('safety_status', ContentSafetyDecision::ALLOWED)
            ->where(fn (Builder $query): Builder => $this->performing($query, $now))
            ->where('next_scrape_at', '>', $now)
            ->whereHas('posts', function ($query) use ($now): void {
                $query->where('tracking_status', '!=', 'stopped')
                    ->whereNotNull('next_metrics_scrape_at')
                    ->where('next_metrics_scrape_at', '<=', $now);
            })
            ->orderByDesc('scrape_priority')
            ->limit((int) config('instagram_scraping.metrics_creator_batch'))
            ->pluck('id');

        foreach ($metricCreators as $creatorId) {
            RefreshCreatorPostMetrics::dispatch($creatorId);
        }

        $this->info("Queued {$dueCreators->count()} creator refreshes and {$metricCreators->count()} grouped metric refreshes.");

        return self::SUCCESS;
    }

    private function performing(Builder $query, CarbonInterface $now): Builder
    {
        return $query->where(function (Builder $performance) use ($now): void {
            $performance->where('curation_status', 'approved')
                ->orWhereHas('posts', function (Builder $posts) use ($now): void {
                    $posts->whereNotNull('measured_at')
                        ->where('published_at', '>=', $now->copy()->subDays((int) config('services.discovery.feed_window_days')))
                        ->where('outlier_score', '>=', (float) config('services.discovery.min_outlier_score'))
                        ->whereRaw('likes + comments >= ?', [(int) config('services.discovery.min_post_engagement')]);
                });
        });
    }
}
