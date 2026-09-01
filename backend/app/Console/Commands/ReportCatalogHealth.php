<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportCatalogHealth extends Command
{
    protected $signature = 'personal:catalog-health
        {--vertical= : Report one canonical vertical only}';

    protected $description = 'Report curated creator and recent Reel/carousel coverage by vertical';

    public function handle(): int
    {
        $verticals = array_keys((array) config('creator_catalog.verticals'));
        $requestedVertical = $this->option('vertical');

        if ($requestedVertical !== null) {
            if (! in_array($requestedVertical, $verticals, true)) {
                $this->error("Unknown canonical vertical [{$requestedVertical}].");

                return self::FAILURE;
            }

            $verticals = [$requestedVertical];
        }

        $creatorCounts = $this->creatorCounts($verticals);
        $postCounts = $this->postCounts($verticals);
        $targetCreators = (int) config('creator_catalog.target_per_vertical');
        $targetPosts = (int) config('creator_catalog.coverage.target_posts_per_vertical');
        $targetReels = (int) config('creator_catalog.coverage.target_reels_per_vertical');
        $targetCarousels = (int) config('creator_catalog.coverage.target_carousels_per_vertical');

        $rows = [];
        foreach ($verticals as $vertical) {
            $posts = $postCounts->get($vertical, collect());
            $creators = (int) ($creatorCounts[$vertical] ?? 0);
            $eligiblePosts = (int) $posts->sum('posts');
            $reels = (int) data_get($posts->firstWhere('format', 'reel'), 'posts', 0);
            $carousels = (int) data_get($posts->firstWhere('format', 'carousel'), 'posts', 0);
            $ready = $creators >= $targetCreators
                && $eligiblePosts >= $targetPosts
                && $reels >= $targetReels
                && $carousels >= $targetCarousels;

            $rows[] = [
                $vertical,
                $creators,
                $eligiblePosts,
                $reels,
                $carousels,
                $ready ? 'ready' : 'gap',
            ];
        }

        $this->table(['Vertical', 'Approved creators', 'Eligible posts', 'Reels', 'Carousels', 'Status'], $rows);
        $this->line("Targets: {$targetCreators} creators, {$targetPosts} posts, {$targetReels} Reels, {$targetCarousels} carousels per vertical.");

        return self::SUCCESS;
    }

    /** @param list<string> $verticals @return array<string, int> */
    private function creatorCounts(array $verticals): array
    {
        return Creator::query()
            ->where('curation_status', 'approved')
            ->where('is_catalog_seed', true)
            ->where('safety_status', ContentSafetyDecision::ALLOWED)
            ->whereIn('market', config('creator_catalog.markets'))
            ->where('followers', '>=', (int) config('services.discovery.min_followers'))
            ->whereIn('primary_vertical', $verticals)
            ->groupBy('primary_vertical')
            ->selectRaw('primary_vertical, COUNT(*) AS total')
            ->pluck('total', 'primary_vertical')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /** @param list<string> $verticals */
    private function postCounts(array $verticals): Collection
    {
        return ContentPost::query()
            ->where('content_posts.safety_status', ContentSafetyDecision::ALLOWED)
            ->whereNotNull('content_posts.measured_at')
            ->where('content_posts.published_at', '>=', now()->subDays((int) config('services.discovery.feed_window_days')))
            ->where('content_posts.outlier_score', '>=', (float) config('services.discovery.fallback_min_outlier_score'))
            ->whereRaw('content_posts.likes + content_posts.comments >= ?', [(int) config('services.discovery.min_post_engagement')])
            ->whereIn('content_posts.format', ['reel', 'carousel'])
            ->whereHas('creator', function (Builder $creator) use ($verticals): void {
                $creator
                    ->where('curation_status', 'approved')
                    ->where('is_catalog_seed', true)
                    ->where('safety_status', ContentSafetyDecision::ALLOWED)
                    ->whereIn('market', config('creator_catalog.markets'))
                    ->where('followers', '>=', (int) config('services.discovery.min_followers'))
                    ->whereIn('primary_vertical', $verticals);
            })
            ->join('creators', 'creators.id', '=', 'content_posts.creator_id')
            ->groupBy('creators.primary_vertical', 'content_posts.format')
            ->selectRaw('creators.primary_vertical, content_posts.format, COUNT(*) AS posts')
            ->get()
            ->groupBy('primary_vertical');
    }
}
