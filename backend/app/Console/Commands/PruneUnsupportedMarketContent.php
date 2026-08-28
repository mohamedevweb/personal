<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Models\Creator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PruneUnsupportedMarketContent extends Command
{
    protected $signature = 'personal:prune-unsupported-markets
        {--dry-run : Count without changing data}
        {--including-protected : Also remove saved, remixed and inspired discovery content}';

    protected $description = 'Remove unprotected discovery content outside the configured creator markets';

    public function handle(): int
    {
        $markets = array_values((array) config('creator_catalog.markets'));
        $unsupportedCreators = Creator::query()
            ->whereNull('user_id')
            ->whereNotNull('market')
            ->whereNotIn('market', $markets);
        $unsupportedPosts = ContentPost::query()
            ->whereIn('creator_id', (clone $unsupportedCreators)->select('id'));
        $includingProtected = (bool) $this->option('including-protected');
        $deletablePosts = (clone $unsupportedPosts)
            ->when(! $includingProtected, function (Builder $query): void {
                $query->whereDoesntHave('savedContent')->whereDoesntHave('remixes');
            });
        $protectedPosts = (clone $unsupportedPosts)
            ->where(function (Builder $query): void {
                $query->whereHas('savedContent')->orWhereHas('remixes');
            });
        $creators = (clone $unsupportedCreators)->count();
        $posts = (clone $deletablePosts)->count();
        $protected = (clone $protectedPosts)->count();
        $inspirations = DB::table('user_creator_inspirations')
            ->whereIn('creator_id', (clone $unsupportedCreators)->select('id'))
            ->count();

        if ($this->option('dry-run')) {
            $protectedAction = $includingProtected ? 'deleted' : 'retained';
            $this->info("{$creators} unsupported discovery creators found. {$posts} posts would be deleted, {$protected} protected posts would be {$protectedAction}, and {$inspirations} inspiration selections would be affected.");

            return self::SUCCESS;
        }

        $deletedCreators = DB::transaction(function () use ($deletablePosts, $includingProtected, $unsupportedCreators): int {
            $deletablePosts->eachById(fn (ContentPost $post) => $post->delete());

            if ($includingProtected) {
                DB::table('user_creator_inspirations')
                    ->whereIn('creator_id', (clone $unsupportedCreators)->select('id'))
                    ->delete();

                $count = (clone $unsupportedCreators)->count();
                $unsupportedCreators->eachById(fn (Creator $creator) => $creator->delete());

                return $count;
            }

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

        $protectedResult = $includingProtected
            ? "{$protected} protected posts and {$inspirations} inspiration selections were also removed"
            : "{$protected} saved or remixed posts were retained and disabled from feeds";
        $this->info("{$posts} unsupported posts and {$deletedCreators} creators deleted. {$protectedResult}.");

        return self::SUCCESS;
    }
}
