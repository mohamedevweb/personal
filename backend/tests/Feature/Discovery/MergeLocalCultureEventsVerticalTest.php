<?php

namespace Tests\Feature\Discovery;

use App\Models\AdminCatalogImport;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeLocalCultureEventsVerticalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_merges_creator_profile_post_and_import_verticals(): void
    {
        $user = User::factory()->create();
        $profile = CreatorProfile::query()->create([
            'user_id' => $user->id,
            'niche' => 'Local discovery',
            'primary_vertical' => 'local-culture',
            'creator_dna' => [
                'primary_vertical' => 'events',
                'primary_niche' => 'Local events',
            ],
        ]);
        $creator = Creator::query()->create([
            'username' => 'local.events',
            'display_name' => 'Local Events',
            'niche' => 'Event planning',
            'primary_vertical' => 'events',
            'followers' => 10_000,
            'average_views' => 5_000,
            'average_likes' => 700,
        ]);
        $post = ContentPost::query()->create([
            'creator_id' => $creator->id,
            'format' => 'reel',
            'hook' => 'Discover this weekend',
            'caption' => 'Three local events worth visiting.',
            'views' => 10_000,
            'likes' => 700,
            'comments' => 20,
            'published_at' => now()->subDay(),
            'performance_ratio' => 1.4,
            'why_it_works' => 'Clear local promise',
            'hook_analysis' => 'Immediate specificity',
            'structure_analysis' => 'Curated list',
            'metadata' => [
                'feed_classification' => [
                    'vertical' => 'local-culture',
                    'primary_niche' => 'Local events',
                ],
            ],
        ]);
        $import = AdminCatalogImport::query()->create([
            'initiated_by' => $user->id,
            'type' => 'creator',
            'url' => 'https://www.instagram.com/local.events/',
            'creator_username' => 'local.events',
            'vertical' => 'events',
            'country_code' => 'FR',
        ]);

        $migration = require database_path('migrations/2026_09_03_120000_merge_events_and_local_culture_verticals.php');
        $migration->up();

        $this->assertSame('local-culture-events', $profile->fresh()->primary_vertical);
        $this->assertSame('local-culture-events', data_get($profile->fresh()->creator_dna, 'primary_vertical'));
        $this->assertSame('local-culture-events', $creator->fresh()->primary_vertical);
        $this->assertSame('local-culture-events', data_get($post->fresh()->metadata, 'feed_classification.vertical'));
        $this->assertSame('local-culture-events', $import->fresh()->vertical);
    }
}
