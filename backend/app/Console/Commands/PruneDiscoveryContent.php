<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use Illuminate\Console\Command;

class PruneDiscoveryContent extends Command
{
    protected $signature = 'personal:prune-discovery-content {--days= : Retention period} {--dry-run : Count without deleting}';

    protected $description = 'Delete expired discovery content unless it is saved or used in a remix';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('creator_catalog.retention_days')));
        $query = ContentPost::query()
            ->where('published_at', '<', now()->subDays($days))
            ->whereDoesntHave('savedContent')
            ->whereDoesntHave('remixes');
        $count = (clone $query)->count();

        if (! $this->option('dry-run')) {
            $query->eachById(fn (ContentPost $post) => $post->delete());
        }

        $verb = $this->option('dry-run') ? 'would be deleted' : 'deleted';
        $this->info("{$count} expired content posts {$verb}; saved and remixed posts were protected.");

        return self::SUCCESS;
    }
}
