<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Models\Creator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PruneUnsupportedMarketContent extends Command
{
    protected $signature = 'personal:prune-unsupported-markets {--dry-run : Count without changing data}';

    protected $description = 'Remove unprotected discovery content outside the configured creator markets';

    public function handle(): int
    {
        $markets = array_values((array) config('creator_catalog.markets'));
        $unsupportedCreators = Creator::query()
            ->whereNull('user_id')
            ->where(function (Builder $query) use ($markets): void {
                $query->whereNull('market')->orWhereNotIn('market', $markets);
            });
        $unsupportedPosts = ContentPost::query()
            ->whereIn('creator_id', (clone $unsupportedCreators)->select('id'));
        $deletablePosts = (clone $unsupportedPosts)
            ->whereDoesntHave('savedContent')
            ->whereDoesntHave('remixes');
        $protectedPosts = (clone $unsupportedPosts)
            ->where(function (Builder $query): void {
                $query->whereHas('savedContent')->orWhereHas('remixes');
            });
        $creators = (clone $unsupportedCreators)->count();
        $posts = (clone $deletablePosts)->count();
        $protected = (clone $protectedPosts)->count();

        if ($this->option('dry-run')) {
            $this->info("{$creators} unsupported discovery creators found. {$posts} unprotected posts would be deleted and {$protected} protected posts would be retained.");

            return self::SUCCESS;
        }

        $deletedCreators = DB::transaction(function () use ($deletablePosts, $unsupportedCreators): int {
            $deletablePosts->eachById(fn (ContentPost $post) => $post->delete());

            (clone $unsupportedCreators)->update([
                'curation_status' => 'inactive',
                'scrape_status' => 'inactive',
            ]);

            ContentPost::query()
                ->whereIn('creator_id', (clone $unsupportedCreators)->select('id'))
                ->update([
                    'measured_at' => null,
                    'outlier_score' => 0,
                    'performance_ratio' => 0,
                    'engagement_rate' => 0,
                    'tracking_status' => 'stopped',
                    'next_metrics_scrape_at' => null,
                    'tracking_stopped_at' => now(),
                ]);

            $emptyCreators = (clone $unsupportedCreators)
                ->where('is_catalog_seed', false)
                ->whereDoesntHave('posts')
                ->whereDoesntHave('inspiredByUsers');
            $count = (clone $emptyCreators)->count();
            $emptyCreators->eachById(fn (Creator $creator) => $creator->delete());

            return $count;
        });

        $this->info("{$posts} unsupported unprotected posts and {$deletedCreators} empty creators deleted. {$protected} saved or remixed posts were retained and disabled from feeds.");

        return self::SUCCESS;
    }
}
