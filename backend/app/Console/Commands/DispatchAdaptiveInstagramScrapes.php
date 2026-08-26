<?php

namespace App\Console\Commands;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Jobs\Discovery\RefreshCreatorPostMetrics;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use Illuminate\Console\Command;

class DispatchAdaptiveInstagramScrapes extends Command
{
    protected $signature = 'personal:dispatch-instagram-scrapes';

    protected $description = 'Queue only creator and post metric scrapes that are currently due';

    public function handle(): int
    {
        $now = now();
        $dueCreators = Creator::query()
            ->when(config('creator_catalog.curated_only'), fn ($query) => $query->where('curation_status', 'approved'))
            ->where('safety_status', '!=', ContentSafetyDecision::BLOCKED)
            ->where('next_scrape_at', '<=', $now)
            ->orderByDesc('scrape_priority')
            ->orderBy('next_scrape_at')
            ->limit((int) config('instagram_scraping.creator_batch'))
            ->get(['id', 'username']);

        foreach ($dueCreators as $creator) {
            MeasureAccountEngagement::dispatch([$creator->username]);
        }

        $metricCreators = Creator::query()
            ->whereNotIn('id', $dueCreators->pluck('id'))
            ->where('safety_status', ContentSafetyDecision::ALLOWED)
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
}
