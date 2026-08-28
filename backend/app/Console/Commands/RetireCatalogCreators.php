<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\CreatorCatalog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles imported catalog seeds with the manifest.
 *
 * The importer only ever writes approved entries, so an entry that is removed
 * from the manifest or switched to `inactive` stays behind in the database with
 * its original `approved` curation status. Retiring a creator editorially and
 * retiring it in production are two separate acts, and this command is the
 * second one.
 *
 * Deactivating alone is not enough: `curation_status` only filters the
 * personalised feed while `DISCOVERY_CURATED_CATALOG_ONLY` is on. Unprotected
 * posts are therefore deleted, so a retirement holds whatever that flag is set
 * to. Saved and remixed posts are kept and stopped instead, because a member's
 * own library outlives an editorial decision.
 */
class RetireCatalogCreators extends Command
{
    protected $signature = 'personal:retire-catalog-creators
        {--dry-run : Report what would change without writing}
        {--including-protected : Also remove saved and remixed posts and inspiration selections}';

    protected $description = 'Retire imported catalog seeds that the manifest no longer approves';

    public function handle(CreatorCatalog $catalog): int
    {
        $approved = collect($catalog->approved())
            ->map(fn (array $entry): string => strtolower(ltrim((string) $entry['handle'], '@')))
            ->all();

        if ($approved === []) {
            $this->error('The manifest approves no creator. Refusing to retire the whole catalog.');

            return self::FAILURE;
        }

        $retired = Creator::query()
            ->where('is_catalog_seed', true)
            ->whereNull('user_id')
            ->whereNotIn(DB::raw('lower(username)'), $approved);

        if ((clone $retired)->doesntExist()) {
            $this->info('Every catalog seed matches an approved manifest entry.');

            return self::SUCCESS;
        }

        $includingProtected = (bool) $this->option('including-protected');
        $posts = ContentPost::query()->whereIn('creator_id', (clone $retired)->select('id'));
        $deletablePosts = (clone $posts)
            ->when(! $includingProtected, function (Builder $query): void {
                $query->whereDoesntHave('savedContent')->whereDoesntHave('remixes');
            });
        $protected = (clone $posts)
            ->where(function (Builder $query): void {
                $query->whereHas('savedContent')->orWhereHas('remixes');
            })
            ->count();
        $inspirations = DB::table('user_creator_inspirations')
            ->whereIn('creator_id', (clone $retired)->select('id'))
            ->count();

        $this->table(
            ['Creator', 'Vertical', 'Curation', 'Posts', 'Protected'],
            (clone $retired)->orderBy('niche')->orderBy('username')->get()->map(fn (Creator $creator): array => [
                $creator->username,
                $creator->niche,
                $creator->curation_status,
                $creator->posts()->count(),
                $creator->posts()->where(function (Builder $query): void {
                    $query->whereHas('savedContent')->orWhereHas('remixes');
                })->count(),
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $protectedAction = $includingProtected ? 'deleted' : 'retained and stopped';
            $this->info((clone $deletablePosts)->count()." posts would be deleted, {$protected} protected posts would be {$protectedAction}, and {$inspirations} inspiration selections would be affected.");

            return self::SUCCESS;
        }

        $deleted = DB::transaction(function () use ($deletablePosts, $includingProtected, $retired, $posts): int {
            $count = (clone $deletablePosts)->count();
            $deletablePosts->eachById(fn (ContentPost $post) => $post->delete());

            if ($includingProtected) {
                DB::table('user_creator_inspirations')
                    ->whereIn('creator_id', (clone $retired)->select('id'))
                    ->delete();
            }

            // Anything left is protected. It keeps its place in the member's
            // library while losing every score that could put it back in a feed.
            (clone $posts)->update([
                'measured_at' => null,
                'outlier_score' => 0,
                'performance_ratio' => 0,
                'engagement_rate' => 0,
                'tracking_status' => 'stopped',
                'next_metrics_scrape_at' => null,
                'tracking_stopped_at' => now(),
            ]);

            (clone $retired)->update([
                'curation_status' => 'inactive',
                'scrape_status' => 'inactive',
            ]);

            $empty = (clone $retired)->whereDoesntHave('posts')->whereDoesntHave('inspiredByUsers');
            $empty->eachById(fn (Creator $creator) => $creator->delete());

            return $count;
        });

        $this->info("{$deleted} posts deleted. Remaining seeds are inactive and no longer scraped.");

        return self::SUCCESS;
    }
}
