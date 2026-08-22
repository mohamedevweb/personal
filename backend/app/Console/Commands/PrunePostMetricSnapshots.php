<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Models\ContentPostMetricSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PrunePostMetricSnapshots extends Command
{
    protected $signature = 'personal:prune-post-metric-snapshots';

    protected $description = 'Downsample old Instagram metric snapshots and expire their retention window';

    public function handle(): int
    {
        $rawCutoff = now()->subDays((int) config('instagram_scraping.snapshots.raw_days'));
        $retentionCutoff = now()->subDays((int) config('instagram_scraping.snapshots.retention_days'));
        $deleted = ContentPostMetricSnapshot::query()
            ->where('captured_at', '<', $retentionCutoff)
            ->delete();

        ContentPost::query()
            ->whereHas('metricSnapshots', fn ($query) => $query->whereBetween('captured_at', [$retentionCutoff, $rawCutoff]))
            ->select('id')
            ->eachById(function (ContentPost $post) use ($rawCutoff, $retentionCutoff, &$deleted): void {
                $seenDays = [];
                $deleteIds = [];
                $snapshots = $post->metricSnapshots()
                    ->whereBetween('captured_at', [$retentionCutoff, $rawCutoff])
                    ->latest('captured_at')
                    ->latest('id')
                    ->get(['id', 'captured_at']);

                foreach ($snapshots as $snapshot) {
                    $day = CarbonImmutable::parse($snapshot->captured_at)->utc()->toDateString();

                    if (isset($seenDays[$day])) {
                        $deleteIds[] = $snapshot->id;
                    } else {
                        $seenDays[$day] = true;
                    }
                }

                foreach (array_chunk($deleteIds, 500) as $ids) {
                    $deleted += ContentPostMetricSnapshot::query()->whereIn('id', $ids)->delete();
                }
            }, 100);

        $this->info("Deleted {$deleted} metric snapshots.");

        return self::SUCCESS;
    }
}
