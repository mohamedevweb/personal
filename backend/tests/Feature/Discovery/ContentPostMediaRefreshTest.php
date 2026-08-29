<?php

namespace Tests\Feature\Discovery;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentPostMediaRefresh;
use App\Services\Discovery\DiscoveredPostMedia;
use App\Services\Discovery\InstagramDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ContentPostMediaRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.discovery.media_refresh.enabled', true);
    }

    public function test_a_carousel_stored_with_only_its_cover_gets_its_slides_back(): void
    {
        $post = $this->contentPost(['media_urls' => ['https://cdn.example.test/cover.jpg']]);
        $this->fakeProvider(new DiscoveredPostMedia(mediaUrls: [
            'https://cdn.example.test/slide-1.jpg',
            'https://cdn.example.test/slide-2.jpg',
            'https://cdn.example.test/slide-3.jpg',
        ]));

        $this->assertTrue(app(ContentPostMediaRefresh::class)->ensure($post));

        $this->assertCount(3, $post->refresh()->media_urls);
        $this->assertNotNull($post->media_refreshed_at);
    }

    public function test_a_carousel_that_already_has_its_slides_costs_nothing(): void
    {
        $post = $this->contentPost(['media_urls' => [
            'https://cdn.example.test/slide-1.jpg',
            'https://cdn.example.test/slide-2.jpg',
        ]]);
        $this->neverCalledProvider();

        $this->assertTrue(app(ContentPostMediaRefresh::class)->ensure($post));
        $this->assertNull($post->refresh()->media_refreshed_at);
    }

    public function test_a_reel_is_given_a_playable_url_and_its_duration(): void
    {
        $post = $this->contentPost(['format' => 'reel', 'media_urls' => [], 'video_url' => null]);
        $this->fakeProvider(new DiscoveredPostMedia(
            videoUrl: 'https://scontent.cdninstagram.com/fresh.mp4',
            videoDurationSeconds: 53,
        ));

        $this->assertTrue(app(ContentPostMediaRefresh::class)->ensure($post));

        $post->refresh();
        $this->assertSame('https://scontent.cdninstagram.com/fresh.mp4', $post->video_url);
        $this->assertSame(53, data_get($post->metadata, 'video_duration'));
    }

    public function test_a_post_whose_media_is_gone_is_not_paid_for_again_within_the_cooldown(): void
    {
        $post = $this->contentPost([
            'media_urls' => ['https://cdn.example.test/cover.jpg'],
            'media_refreshed_at' => now()->subHour(),
        ]);
        $this->neverCalledProvider();

        $this->assertTrue(app(ContentPostMediaRefresh::class)->ensure($post));
    }

    public function test_the_flag_stops_every_call(): void
    {
        config()->set('services.discovery.media_refresh.enabled', false);
        $post = $this->contentPost(['media_urls' => ['https://cdn.example.test/cover.jpg']]);
        $this->neverCalledProvider();

        app(ContentPostMediaRefresh::class)->ensure($post);

        $this->assertNull($post->refresh()->media_refreshed_at);
    }

    public function test_a_provider_outage_leaves_the_post_untouched_instead_of_throwing(): void
    {
        $post = $this->contentPost(['media_urls' => ['https://cdn.example.test/cover.jpg']]);
        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getPostMedia')->once()->andThrow(new RuntimeException('provider down'));
        $this->app->instance(InstagramDataProvider::class, $provider);

        $this->assertTrue(app(ContentPostMediaRefresh::class)->ensure($post));

        $post->refresh();
        $this->assertSame(['https://cdn.example.test/cover.jpg'], $post->media_urls);
        $this->assertNotNull($post->media_refreshed_at);
    }

    private function fakeProvider(DiscoveredPostMedia $media): void
    {
        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getPostMedia')->once()->andReturn($media);
        $this->app->instance(InstagramDataProvider::class, $provider);
    }

    private function neverCalledProvider(): void
    {
        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldNotReceive('getPostMedia');
        $this->app->instance(InstagramDataProvider::class, $provider);
    }

    private function contentPost(array $overrides = []): ContentPost
    {
        $creator = Creator::query()->create([
            'username' => 'refresh.creator',
            'display_name' => 'Refresh Creator',
            'niche' => 'business',
            'followers' => 40_000,
            'average_views' => 8_000,
            'average_likes' => 800,
            'safety_status' => 'allowed',
        ]);

        return ContentPost::query()->create(array_merge([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/refresh/',
            'platform' => 'instagram',
            'format' => 'carousel',
            'hook' => 'A carousel',
            'caption' => 'Caption.',
            'thumbnail_url' => 'https://cdn.example.test/cover.jpg',
            'views' => 10_000,
            'likes' => 900,
            'comments' => 40,
            'published_at' => now()->subDays(3),
            'outlier_score' => 2,
            'safety_status' => 'allowed',
        ], $overrides));
    }
}
