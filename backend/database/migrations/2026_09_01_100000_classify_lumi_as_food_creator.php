<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Lumi publishes food content. Its former event classification came from
     * an isolated post signal rather than the account's recurring subject.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $creator = DB::table('creators')
                ->where('username', 'lumi.co.in')
                ->first(['id', 'metadata']);

            if ($creator === null) {
                return;
            }

            $metadata = json_decode((string) $creator->metadata, true);
            $metadata = is_array($metadata) ? $metadata : [];
            $metadata['country_code'] = 'GB';

            DB::table('creators')
                ->where('id', $creator->id)
                ->update([
                    'niche' => 'food-cooking',
                    'niche_topics' => json_encode(['food', 'cooking', 'desserts', 'ice cream']),
                    'primary_vertical' => 'food-cooking',
                    'niche_analysis_version' => 3,
                    'metadata' => json_encode($metadata),
                ]);

            DB::table('content_posts')
                ->where('creator_id', $creator->id)
                ->select(['id', 'metadata'])
                ->orderBy('id')
                ->chunk(500, function ($posts): void {
                    foreach ($posts as $post) {
                        $metadata = json_decode((string) $post->metadata, true);

                        if (! is_array($metadata) || ! is_array($metadata['feed_classification'] ?? null)) {
                            continue;
                        }

                        $classification = $metadata['feed_classification'];
                        $classification['vertical'] = 'food-cooking';
                        $classification['primary_niche'] = 'food-cooking';
                        $classification['topics'] = array_values(array_unique([
                            ...(is_array($classification['topics'] ?? null) ? $classification['topics'] : []),
                            'food',
                        ]));
                        $classification['clusters'] = array_values(array_unique([
                            ...(is_array($classification['clusters'] ?? null) ? $classification['clusters'] : []),
                            'food-cooking',
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
            $creator = DB::table('creators')
                ->where('username', 'lumi.co.in')
                ->first(['id', 'metadata']);

            if ($creator === null) {
                return;
            }

            $metadata = json_decode((string) $creator->metadata, true);
            $metadata = is_array($metadata) ? $metadata : [];
            unset($metadata['country_code']);

            DB::table('creators')
                ->where('id', $creator->id)
                ->update([
                    'niche' => 'events',
                    'niche_topics' => json_encode(['events']),
                    'primary_vertical' => 'events',
                    'metadata' => json_encode($metadata),
                ]);

            DB::table('content_posts')
                ->where('creator_id', $creator->id)
                ->select(['id', 'metadata'])
                ->orderBy('id')
                ->chunk(500, function ($posts): void {
                    foreach ($posts as $post) {
                        $metadata = json_decode((string) $post->metadata, true);

                        if (! is_array($metadata) || ! is_array($metadata['feed_classification'] ?? null)) {
                            continue;
                        }

                        $classification = $metadata['feed_classification'];
                        $classification['vertical'] = 'events';
                        $classification['primary_niche'] = 'events';
                        $classification['topics'] = array_values(array_diff(
                            is_array($classification['topics'] ?? null) ? $classification['topics'] : [],
                            ['food'],
                        ));
                        $classification['clusters'] = array_values(array_diff(
                            is_array($classification['clusters'] ?? null) ? $classification['clusters'] : [],
                            ['food-cooking'],
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
