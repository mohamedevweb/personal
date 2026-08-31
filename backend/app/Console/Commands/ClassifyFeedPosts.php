<?php

namespace App\Console\Commands;

use App\Models\ContentPost;
use App\Services\Feed\ContentTopicClassifier;
use Illuminate\Console\Command;

class ClassifyFeedPosts extends Command
{
    protected $signature = 'personal:classify-feed-posts
        {--limit=1000 : Maximum posts to inspect in this pass}
        {--creator= : Restrict the pass to one creator handle}
        {--dry-run : Classify without writing metadata}';

    protected $description = 'Backfill structured feed classifications for stored posts';

    public function handle(ContentTopicClassifier $classifier): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $query = ContentPost::query()
            ->where(function ($query): void {
                $query->whereNull('safety_status')->orWhere('safety_status', '!=', 'blocked');
            })
            ->when($this->option('creator'), function ($query, string $username): void {
                $query->whereHas('creator', fn ($creator) => $creator->where('username', $username));
            })
            ->orderBy('id');

        $checked = 0;
        $classified = 0;
        $unclassified = 0;

        foreach ($query->cursor() as $post) {
            if (! $this->needsClassification($post->metadata)) {
                continue;
            }

            $classification = $classifier->post($post);
            $hasVertical = is_string($classification['vertical'] ?? null)
                && trim($classification['vertical']) !== '';
            $checked++;
            $classified += (int) $hasVertical;
            $unclassified += (int) ! $hasVertical;

            if (! $this->option('dry-run')) {
                $post->forceFill([
                    'metadata' => array_replace_recursive($post->metadata ?? [], [
                        'feed_classification' => [
                            'vertical' => $classification['vertical'],
                            'primary_niche' => $classification['primary_niche'],
                            'sub_niches' => $classification['sub_niches'],
                            'topics' => $classification['topics'],
                            'avoid_topics' => $classification['avoid_topics'],
                        ],
                    ]),
                ])->save();
            }

            if ($checked >= $limit) {
                break;
            }
        }

        $mode = $this->option('dry-run') ? 'Dry run' : 'Classification pass';
        $this->info("{$mode}: {$checked} checked, {$classified} classified, {$unclassified} unclassified.");

        return self::SUCCESS;
    }

    /** @param array<string, mixed>|null $metadata */
    private function needsClassification(?array $metadata): bool
    {
        $classification = data_get($metadata, 'feed_classification');

        return ! is_array($classification)
            || ! is_string($classification['vertical'] ?? null)
            || trim($classification['vertical']) === '';
    }
}
