<?php

namespace Tests\Feature\Discovery;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Jobs\Discovery\RefreshCreatorPostMetrics;
use App\Models\ContentPost;
use App\Models\ContentPostMetricSnapshot;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\CreatorScrapeSchedule;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use App\Services\Discovery\PostMetricsLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AdaptiveInstagramScrapingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_priority_drives_a_configured_dynamic_schedule(): void
    {
        $now = CarbonImmutable::parse('2026-08-22 12:00:00');
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'primary_vertical' => 'fitness',
        ]);
        $hot = $this->creator('priority-hot', [
            'niche' => 'fitness',
            'curation_status' => 'approved',
        ]);
        $user->inspirationCreators()->attach($hot->id);

        foreach (range(1, 8) as $index) {
            $this->contentPost($hot, "hot-{$index}", [
                'published_at' => $now->subHours($index),
                'outlier_score' => 1.5,
                'tracking_status' => $index === 1 ? 'hot' : 'active',
            ]);
        }

        $cold = $this->creator('priority-cold');
        $schedule = app(CreatorScrapeSchedule::class);
        $schedule->recordSuccess($hot, $now);
        $schedule->recordSuccess($cold, $now);

        $hot->refresh();
        $cold->refresh();

        $this->assertSame('hot', $hot->scrape_status);
        $this->assertGreaterThanOrEqual(70, $hot->scrape_priority);
        $this->assertBetweenHours($now, $hot->next_scrape_at, 6, 12);
        $this->assertSame('cold', $cold->scrape_status);
        $this->assertBetweenHours($now, $cold->next_scrape_at, 120, 168);
    }

    public function test_dispatcher_only_queues_due_creators_and_groups_due_posts_by_creator(): void
    {
        Queue::fake();

        $due = $this->creator('creator-due', ['next_scrape_at' => now()->subMinute()]);
        $this->creator('creator-future', ['next_scrape_at' => now()->addDay()]);
        $metrics = $this->creator('creator-metrics', [
            'next_scrape_at' => now()->addDay(),
            'last_measured_at' => now(),
        ]);
        $this->contentPost($metrics, 'metric-one', ['next_metrics_scrape_at' => now()->subMinute()]);
        $this->contentPost($metrics, 'metric-two', ['next_metrics_scrape_at' => now()->subMinute()]);

        $this->artisan('personal:dispatch-instagram-scrapes')->assertSuccessful();

        Queue::assertPushed(MeasureAccountEngagement::class, 1);
        Queue::assertPushed(
            MeasureAccountEngagement::class,
            fn (MeasureAccountEngagement $job): bool => $job->usernames === [$due->username],
        );
        Queue::assertPushed(RefreshCreatorPostMetrics::class, 1);
        Queue::assertPushed(
            RefreshCreatorPostMetrics::class,
            fn (RefreshCreatorPostMetrics $job): bool => $job->creatorId === $metrics->id,
        );
    }

    public function test_dispatcher_queues_obsolete_analysis_without_waiting_for_the_regular_schedule(): void
    {
        Queue::fake();

        $obsolete = $this->creator('creator-obsolete', [
            'next_scrape_at' => now()->addWeek(),
            'niche_analysis_version' => 0,
        ]);
        $this->creator('catalog-editorial', [
            'next_scrape_at' => now()->addWeek(),
            'niche_analysis_version' => 0,
            'is_catalog_seed' => true,
        ]);

        $this->artisan('personal:dispatch-instagram-scrapes')->assertSuccessful();

        Queue::assertPushed(MeasureAccountEngagement::class, 1);
        Queue::assertPushed(
            MeasureAccountEngagement::class,
            fn (MeasureAccountEngagement $job): bool => $job->usernames === [$obsolete->username],
        );
    }

    public function test_snapshots_calculate_velocity_and_slow_hot_posts_to_a_stop(): void
    {
        $publishedAt = CarbonImmutable::parse('2026-08-20 00:00:00');
        $creator = $this->creator('velocity', [
            'performance_baselines' => ['views' => 7200, 'engagement' => 100],
        ]);
        $post = $this->contentPost($creator, 'velocity-post', [
            'published_at' => $publishedAt,
            'views' => 1000,
        ])->load('creator');
        $lifecycle = app(PostMetricsLifecycle::class);

        $lifecycle->recordRefresh($post, $publishedAt);
        $post->update(['views' => 7000]);
        $lifecycle->recordRefresh($post, $publishedAt->addHours(6));
        $lifecycle->reschedule($post->fresh('creator'), $publishedAt->addHours(6));

        $post->refresh();
        $this->assertSame('hot', $post->tracking_status);
        $this->assertSame(1000.0, $post->views_velocity);
        $this->assertGreaterThan(0, $post->views_acceleration);

        foreach ([12 => 'warm', 36 => 'cold', 108 => 'stopped'] as $hours => $status) {
            $capturedAt = $publishedAt->addHours($hours);
            $lifecycle->recordRefresh($post->fresh(), $capturedAt);
            $lifecycle->reschedule($post->fresh('creator'), $capturedAt);
            $post->refresh();
            $this->assertSame($status, $post->tracking_status);
        }

        $this->assertNull($post->next_metrics_scrape_at);
        $this->assertCount(5, $post->metricSnapshots);
    }

    public function test_metric_refresh_makes_one_provider_call_for_all_due_posts(): void
    {
        $creator = $this->creator('grouped-refresh', [
            'instagram_user_id' => 'creator-1',
            'next_scrape_at' => now()->addDay(),
            'performance_baselines' => ['views' => 1000, 'engagement' => 100],
        ]);
        $first = $this->contentPost($creator, 'grouped-one', [
            'instagram_media_id' => 'post-1',
            'next_metrics_scrape_at' => now()->subMinute(),
        ]);
        $second = $this->contentPost($creator, 'grouped-two', [
            'instagram_media_id' => 'post-2',
            'next_metrics_scrape_at' => now()->subMinute(),
        ]);
        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getPosts')
            ->once()
            ->with('grouped-refresh', 12, 'creator-1')
            ->andReturn(collect([
                $this->discoveredPost('grouped-one', 'post-1', 2000),
                $this->discoveredPost('grouped-two', 'post-2', 3000),
            ]));

        (new RefreshCreatorPostMetrics($creator->id))->handle(
            $provider,
            app(OutlierScore::class),
            app(PostMetricsLifecycle::class),
        );

        $this->assertSame(2000, $first->fresh()->views);
        $this->assertSame(3000, $second->fresh()->views);
        $this->assertSame(1, $first->metricSnapshots()->count());
        $this->assertSame(1, $second->metricSnapshots()->count());
    }

    public function test_snapshot_retention_keeps_one_daily_point_after_the_raw_window(): void
    {
        $creator = $this->creator('snapshot-retention');
        $post = $this->contentPost($creator, 'snapshot-retention-post');
        $day = now()->subDays(40)->startOfDay();

        foreach ([$day->copy()->addHour(), $day->copy()->addHours(12), $day->copy()->addDay(), now()->subDays(400)] as $capturedAt) {
            ContentPostMetricSnapshot::query()->create([
                'content_post_id' => $post->id,
                'captured_at' => $capturedAt,
                'views' => 1000,
                'likes' => 100,
                'comments' => 10,
            ]);
        }

        $this->artisan('personal:prune-post-metric-snapshots')->assertSuccessful();

        $this->assertSame(2, $post->metricSnapshots()->count());
    }

    private function creator(string $username, array $overrides = []): Creator
    {
        return Creator::query()->create(array_merge([
            'username' => $username,
            'display_name' => $username,
            'niche' => 'business',
            'followers' => 50_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'safety_status' => 'allowed',
            'niche_analysis_version' => CreatorNicheService::ANALYSIS_VERSION,
        ], $overrides));
    }

    private function contentPost(Creator $creator, string $slug, array $overrides = []): ContentPost
    {
        return ContentPost::query()->create(array_merge([
            'creator_id' => $creator->id,
            'source_url' => "https://www.instagram.com/reel/{$slug}/",
            'platform' => 'instagram',
            'format' => 'reel',
            'hook' => $slug,
            'caption' => $slug,
            'views' => 1000,
            'likes' => 100,
            'comments' => 10,
            'published_at' => now()->subDay(),
            'outlier_score' => 1,
        ], $overrides));
    }

    private function discoveredPost(string $slug, string $externalId, int $views): DiscoveredPost
    {
        return new DiscoveredPost(
            sourceUrl: "https://www.instagram.com/reel/{$slug}/",
            username: 'grouped-refresh',
            displayName: 'Grouped Refresh',
            avatarUrl: null,
            followers: 50_000,
            caption: $slug,
            thumbnailUrl: null,
            likes: 200,
            comments: 20,
            views: $views,
            publishedAt: CarbonImmutable::now()->subDay(),
            format: 'reel',
            hashtags: [],
            externalId: $externalId,
        );
    }

    private function assertBetweenHours(CarbonImmutable $start, $end, int $minimum, int $maximum): void
    {
        $hours = $start->diffInHours($end);
        $this->assertGreaterThanOrEqual($minimum, $hours);
        $this->assertLessThanOrEqual($maximum, $hours);
    }
}
