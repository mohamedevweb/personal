<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * These accounts consistently publish startup building, product and founder
     * content. Keeping that distinction in the canonical taxonomy prevents the
     * feed from treating them as generic business creators.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $creatorIds = DB::table('creators')
                ->whereIn('username', ['swerikcodes', 'gregisenberg'])
                ->pluck('id');

            if ($creatorIds->isEmpty()) {
                return;
            }

            DB::table('creators')
                ->whereIn('id', $creatorIds)
                ->update([
                    'niche' => 'startup',
                    'niche_topics' => json_encode(['startup', 'founders', 'product building', 'build in public']),
                    'primary_vertical' => 'startup',
                    'niche_analysis_version' => 2,
                ]);

            DB::table('content_posts')
                ->whereIn('creator_id', $creatorIds)
                ->select(['id', 'metadata'])
                ->orderBy('id')
                ->chunk(500, function ($posts): void {
                    foreach ($posts as $post) {
                        $metadata = json_decode((string) $post->metadata, true);

                        if (! is_array($metadata) || ! is_array($metadata['feed_classification'] ?? null)) {
                            continue;
                        }

                        $classification = $metadata['feed_classification'];
                        $topics = is_array($classification['topics'] ?? null) ? $classification['topics'] : [];
                        $clusters = is_array($classification['clusters'] ?? null) ? $classification['clusters'] : [];
                        $classification['vertical'] = 'startup';
                        $classification['primary_niche'] = 'startup';
                        $classification['topics'] = array_values(array_unique([
                            ...$topics,
                            'startup',
                        ]));
                        $classification['clusters'] = array_values(array_unique([
                            ...$clusters,
                            'startup',
                        ]));
                        $metadata['feed_classification'] = $classification;

                        DB::table('content_posts')
                            ->where('id', $post->id)
                            ->update(['metadata' => json_encode($metadata)]);
                    }
                });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $creatorIds = DB::table('creators')
                ->whereIn('username', ['swerikcodes', 'gregisenberg'])
                ->pluck('id');

            if ($creatorIds->isEmpty()) {
                return;
            }

            DB::table('creators')
                ->whereIn('id', $creatorIds)
                ->update([
                    'niche' => 'business',
                    'niche_topics' => json_encode(['business', 'founders']),
                    'primary_vertical' => 'business',
                ]);

            DB::table('content_posts')
                ->whereIn('creator_id', $creatorIds)
                ->select(['id', 'metadata'])
                ->orderBy('id')
                ->chunk(500, function ($posts): void {
                    foreach ($posts as $post) {
                        $metadata = json_decode((string) $post->metadata, true);

                        if (! is_array($metadata) || ! is_array($metadata['feed_classification'] ?? null)) {
                            continue;
                        }

                        $classification = $metadata['feed_classification'];
                        $topics = is_array($classification['topics'] ?? null) ? $classification['topics'] : [];
                        $clusters = is_array($classification['clusters'] ?? null) ? $classification['clusters'] : [];
                        $classification['vertical'] = 'business';
                        $classification['primary_niche'] = 'business';
                        $classification['topics'] = array_values(array_diff(
                            $topics,
                            ['startup'],
                        ));
                        $classification['clusters'] = array_values(array_diff(
                            $clusters,
                            ['startup'],
                        ));
                        $metadata['feed_classification'] = $classification;

                        DB::table('content_posts')
                            ->where('id', $post->id)
                            ->update(['metadata' => json_encode($metadata)]);
                    }
                });
        });
    }
};
