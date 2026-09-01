<?php

namespace Tests\Feature\Discovery;

use App\Models\ContentPost;
use App\Models\Creator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCatalogHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'creator_catalog.target_per_vertical' => 2,
            'creator_catalog.coverage.target_posts_per_vertical' => 3,
            'creator_catalog.coverage.target_reels_per_vertical' => 2,
            'creator_catalog.coverage.target_carousels_per_vertical' => 1,
            'services.discovery.min_followers' => 5_000,
            'services.discovery.min_post_engagement' => 500,
            'services.discovery.feed_window_days' => 30,
            'services.discovery.fallback_min_outlier_score' => 1.0,
        ]);
    }

    public function test_it_reports_eligible_format_coverage_by_vertical(): void
    {
        $first = $this->creator('first', 'events');
        $second = $this->creator('second', 'events');
        $this->storePost($first, 'reel');
        $this->storePost($second, 'reel');
        $this->storePost($first, 'carousel');

        $this->artisan('personal:catalog-health', ['--vertical' => 'events'])
            ->assertSuccessful()
            ->expectsTable(
                ['Vertical', 'Approved creators', 'Eligible posts', 'Reels', 'Carousels', 'Status'],
                [['events', 2, 3, 2, 1, 'ready']],
            );
    }

    public function test_it_excludes_unapproved_or_stale_posts_from_coverage(): void
    {
        $creator = $this->creator('first', 'events');
        $this->storePost($creator, 'reel');
        $this->storePost($creator, 'carousel', daysAgo: 31);
        $creator->update(['curation_status' => 'discovered']);

        $this->artisan('personal:catalog-health', ['--vertical' => 'events'])
            ->assertSuccessful()
            ->expectsOutputToContain('gap');
    }

    private function creator(string $username, string $vertical): Creator
    {
        return Creator::query()->create([
            'username' => $username,
            'display_name' => $username,
            'niche' => $vertical,
            'primary_vertical' => $vertical,
            'market' => 'FR',
            'followers' => 10_000,
            'average_views' => 5_000,
            'average_likes' => 700,
            'baseline_engagement' => 700,
            'curation_status' => 'approved',
            'is_catalog_seed' => true,
            'safety_status' => 'allowed',
        ]);
    }

    private function storePost(Creator $creator, string $format, int $daysAgo = 1): ContentPost
    {
        return ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => "https://www.instagram.com/p/{$creator->username}-{$format}-{$daysAgo}/",
            'platform' => 'instagram',
            'format' => $format,
            'hook' => $format,
            'caption' => 'Useful content',
            'views' => 10_000,
            'likes' => 700,
            'comments' => 0,
            'published_at' => now()->subDays($daysAgo),
            'performance_ratio' => 1.3,
            'outlier_score' => 1.3,
            'engagement_rate' => 7.0,
            'measured_at' => now(),
            'safety_status' => 'allowed',
            'why_it_works' => '',
            'hook_analysis' => '',
            'structure_analysis' => '',
        ]);
    }
}
