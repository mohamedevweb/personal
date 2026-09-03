<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MERGED_VERTICAL = 'local-culture-events';

    private const PREVIOUS_VERTICALS = ['events', 'local-culture'];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->updateCreatorProfiles(self::PREVIOUS_VERTICALS, self::MERGED_VERTICAL);

            DB::table('creators')
                ->whereIn('primary_vertical', self::PREVIOUS_VERTICALS)
                ->update(['primary_vertical' => self::MERGED_VERTICAL]);

            $this->updateContentPosts(self::PREVIOUS_VERTICALS, self::MERGED_VERTICAL);

            DB::table('admin_catalog_imports')
                ->whereIn('vertical', self::PREVIOUS_VERTICALS)
                ->update(['vertical' => self::MERGED_VERTICAL]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $this->updateCreatorProfiles([self::MERGED_VERTICAL], 'local-culture');

            DB::table('creators')
                ->where('primary_vertical', self::MERGED_VERTICAL)
                ->update(['primary_vertical' => 'local-culture']);

            $this->updateContentPosts([self::MERGED_VERTICAL], 'local-culture');

            DB::table('admin_catalog_imports')
                ->where('vertical', self::MERGED_VERTICAL)
                ->update(['vertical' => 'local-culture']);
        });
    }

    /** @param list<string> $from */
    private function updateCreatorProfiles(array $from, string $to): void
    {
        DB::table('creator_profiles')
            ->select(['id', 'primary_vertical', 'creator_dna'])
            ->orderBy('id')
            ->chunkById(500, function ($profiles) use ($from, $to): void {
                foreach ($profiles as $profile) {
                    $updates = [];

                    if (in_array($profile->primary_vertical, $from, true)) {
                        $updates['primary_vertical'] = $to;
                    }

                    $creatorDna = json_decode((string) $profile->creator_dna, true);
                    if (is_array($creatorDna) && in_array($creatorDna['primary_vertical'] ?? null, $from, true)) {
                        $creatorDna['primary_vertical'] = $to;
                        $updates['creator_dna'] = json_encode($creatorDna, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    }

                    if ($updates !== []) {
                        DB::table('creator_profiles')->where('id', $profile->id)->update($updates);
                    }
                }
            });
    }

    /** @param list<string> $from */
    private function updateContentPosts(array $from, string $to): void
    {
        DB::table('content_posts')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($posts) use ($from, $to): void {
                foreach ($posts as $post) {
                    $metadata = json_decode((string) $post->metadata, true);
                    $classification = is_array($metadata) ? ($metadata['feed_classification'] ?? null) : null;

                    if (! is_array($classification) || ! in_array($classification['vertical'] ?? null, $from, true)) {
                        continue;
                    }

                    $classification['vertical'] = $to;
                    $metadata['feed_classification'] = $classification;

                    DB::table('content_posts')
                        ->where('id', $post->id)
                        ->update(['metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
            });
    }
};
