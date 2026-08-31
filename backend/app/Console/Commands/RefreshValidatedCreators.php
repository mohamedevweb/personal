<?php

namespace App\Console\Commands;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\ContentSafetyDecision;
use Illuminate\Console\Command;

class RefreshValidatedCreators extends Command
{
    protected $signature = 'personal:refresh-validated-creators
        {--vertical=* : Restrict the refresh to one or more canonical verticals}
        {--limit= : Maximum creators to refresh}
        {--posts= : Maximum recent posts to fetch per creator}
        {--dry-run : Show the selected creators without queueing refresh jobs}';

    protected $description = 'Queue a fresh recent-post measurement for validated creators';

    public function handle(CanonicalCreatorVerticals $verticals): int
    {
        $requestedVerticals = array_values(array_filter((array) $this->option('vertical')));
        $selectedVerticals = [];

        foreach ($requestedVerticals as $vertical) {
            $canonical = $verticals->canonical($vertical);

            if ($canonical === null) {
                $this->error("Unsupported vertical [{$vertical}].");

                return self::INVALID;
            }

            $selectedVerticals[] = $canonical;
        }

        $selectedVerticals = array_values(array_unique($selectedVerticals));
        $query = Creator::query()
            ->where('curation_status', 'approved')
            ->where('safety_status', ContentSafetyDecision::ALLOWED)
            ->whereIn('market', config('creator_catalog.markets'))
            ->whereNotNull('primary_vertical')
            ->whereNotNull('username')
            ->when($selectedVerticals !== [], fn ($query) => $query->whereIn('primary_vertical', $selectedVerticals))
            ->orderBy('primary_vertical')
            ->orderBy('id');

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $query->limit(max(1, (int) $limit));
        }

        $creators = $query->get(['username', 'market', 'primary_vertical']);

        if ($this->option('dry-run')) {
            $this->info("Would refresh {$creators->count()} validated creators.");

            return self::SUCCESS;
        }

        $jobs = 0;
        $posts = $this->option('posts') !== null && $this->option('posts') !== ''
            ? max(1, (int) $this->option('posts'))
            : max(1, (int) config('services.discovery.refresh_profile_posts'));
        $chunkSize = max(1, (int) config('services.discovery.measure_chunk'));

        $creators->chunk($chunkSize)->each(function ($chunk) use (&$jobs, $posts): void {
            $marketHints = $chunk->mapWithKeys(
                fn (Creator $creator): array => [mb_strtolower($creator->username) => $creator->market],
            )->all();

            MeasureAccountEngagement::dispatch(
                $chunk->pluck('username')->values()->all(),
                latestOnly: false,
                marketHints: $marketHints,
                force: true,
                recentOnly: true,
                postsLimit: $posts,
            );
            $jobs++;
        });

        $this->info("Queued {$creators->count()} validated creators in {$jobs} refresh jobs.");

        return self::SUCCESS;
    }
}
