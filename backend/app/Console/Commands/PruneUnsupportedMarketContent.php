<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\CreatorMarketDetector;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PruneUnsupportedMarketContent extends Command
{
    protected $signature = 'personal:prune-unsupported-markets
        {--dry-run : Count without changing data}
        {--including-protected : Also remove saved, remixed and inspired discovery content}
        {--including-unclassified : Also remove discovery creators whose market is unknown}
        {--redetect : Re-detect non-catalog creator markets from their stored profiles and posts}';

    protected $description = 'Remove unprotected discovery content outside the configured creator markets';

    public function handle(CreatorMarketDetector $marketDetector): int
    {
        $markets = array_values((array) config('creator_catalog.markets'));
        $dryRun = (bool) $this->option('dry-run');
        $includingProtected = (bool) $this->option('including-protected');
        $rollbackRedetection = $dryRun && (bool) $this->option('redetect');

        if ($rollbackRedetection) {
            DB::beginTransaction();
        }

        try {
            $redetected = $this->option('redetect') ? $this->redetectMarkets($marketDetector) : 0;
            $unsupportedCreators = $this->unsupportedCreators($markets);
            $unsupportedPosts = ContentPost::query()
                ->whereIn('creator_id', (clone $unsupportedCreators)->select('id'));
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

            if ($dryRun) {
                $protectedAction = $includingProtected ? 'deleted' : 'retained';
                $this->info("{$redetected} discovery creators were reclassified. {$creators} unsupported discovery creators found. {$posts} posts would be deleted, {$protected} protected posts would be {$protectedAction}, and {$inspirations} inspiration selections would be affected.");

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
            $this->info("{$redetected} discovery creators were reclassified. {$posts} unsupported posts and {$deletedCreators} creators deleted. {$protectedResult}.");

            return self::SUCCESS;
        } finally {
            if ($rollbackRedetection && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    private function unsupportedCreators(array $markets): Builder
    {
        return Creator::query()
            ->whereNull('user_id')
            ->where(function (Builder $query) use ($markets): void {
                $query->where(function (Builder $classified) use ($markets): void {
                    $classified->whereNotNull('market')->whereNotIn('market', $markets);
                });

                if ($this->option('including-unclassified')) {
                    $query->orWhereNull('market');

                    return;
                }

                $query->orWhere(function (Builder $unclassified): void {
                    $unclassified->whereNull('market')
                        ->where('curation_status', 'inactive')
                        ->whereDoesntHave('posts')
                        ->whereDoesntHave('inspiredByUsers');
                });
            });
    }

    private function redetectMarkets(CreatorMarketDetector $marketDetector): int
    {
        $count = 0;

        Creator::query()
            ->whereNull('user_id')
            ->where('is_catalog_seed', false)
            ->eachById(function (Creator $creator) use ($marketDetector, &$count): void {
                $captions = $creator->posts()
                    ->latest('published_at')
                    ->limit(20)
                    ->pluck('caption')
                    ->filter()
                    ->implode("\n");
                $metadata = json_encode($creator->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                $detection = $marketDetector->detect(implode("\n", array_filter([
                    $creator->display_name,
                    $creator->bio,
                    $metadata,
                    $captions,
                ])));

                $creator->update([
                    'market' => $detection['market'],
                    'primary_language' => $detection['language'],
                ]);
                $count++;
            });

        return $count;
    }
}
