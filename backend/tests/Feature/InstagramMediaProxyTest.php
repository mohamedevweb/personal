<?php

namespace Tests\Feature;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\User;
use App\Services\ContentPostView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InstagramMediaProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_media_route_proxies_and_caches_instagram_images(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $source = 'https://scontent-sea5-1.cdninstagram.com/photo.jpg?oe=future';
        Http::fake([$source => Http::response('jpeg-content', 200, [
            'Content-Type' => 'image/jpeg',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ])]);
        $post = $this->createPost($source);
        $path = URL::temporarySignedRoute('media.content', now()->addHour(), ['content' => $post], absolute: false);

        $this->get($path)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->assertContent('jpeg-content');
        $this->get($path)->assertOk()->assertContent('jpeg-content');

        Http::assertSentCount(1);
    }

    public function test_media_route_requires_a_valid_signature(): void
    {
        $post = $this->createPost('https://scontent-sea5-1.cdninstagram.com/photo.jpg');

        $this->get("/api/media/content/{$post->id}")->assertForbidden();
    }

    public function test_signed_carousel_media_route_proxies_the_requested_slide(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $source = 'https://scontent-sea5-1.cdninstagram.com/carousel-2.jpg';
        Http::fake([$source => Http::response('second-slide', 200, ['Content-Type' => 'image/jpeg'])]);
        $post = $this->createPost('https://scontent-sea5-1.cdninstagram.com/carousel-1.jpg');
        $post->update(['media_urls' => [$post->thumbnail_url, $source]]);
        $path = URL::temporarySignedRoute(
            'media.content.item',
            now()->addHour(),
            ['content' => $post, 'position' => 1],
            absolute: false,
        );

        $this->get($path)->assertOk()->assertContent('second-slide');
    }

    public function test_media_proxy_rejects_non_instagram_hosts_without_making_a_request(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $post = $this->createPost('https://127.0.0.1/private.jpg');
        $path = URL::temporarySignedRoute('media.content', now()->addHour(), ['content' => $post], absolute: false);

        $this->get($path)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_feed_uses_signed_personal_urls_only_for_instagram_cdn_media(): void
    {
        config(['app.url' => 'https://api.personal.test']);
        $user = User::factory()->create();
        $post = $this->createPost('https://instagram.ftce2-1.fna.fbcdn.net/thumb.jpg');
        $post->creator->update(['avatar_url' => 'https://scontent-sea5-1.cdninstagram.com/avatar.jpg']);
        $post->update(['media_urls' => [
            'https://instagram.ftce2-1.fna.fbcdn.net/thumb.jpg',
            'https://scontent-sea5-1.cdninstagram.com/second.jpg',
        ]]);

        $payload = app(ContentPostView::class)->make($post->fresh(), $user);

        $this->assertStringStartsWith('https://api.personal.test/api/media/content/', $payload['thumbnail_url']);
        $this->assertStringContainsString('signature=', $payload['thumbnail_url']);
        $this->assertCount(2, $payload['media_urls']);
        $this->assertStringStartsWith('https://api.personal.test/api/media/content/', $payload['media_urls'][1]);
        $this->assertStringContainsString('/1?', $payload['media_urls'][1]);
        $this->assertStringStartsWith('https://api.personal.test/api/media/creator/', $payload['creator']['avatar_url']);

        $post->update(['thumbnail_url' => 'https://images.unsplash.com/photo.jpg']);
        $payload = app(ContentPostView::class)->make($post->fresh(), $user);
        $this->assertSame('https://images.unsplash.com/photo.jpg', $payload['thumbnail_url']);
    }

    private function createPost(string $thumbnailUrl): ContentPost
    {
        $creator = Creator::query()->create([
            'username' => 'creator'.Creator::query()->count(),
            'display_name' => 'Creator',
            'niche' => 'tech-ai',
            'followers' => 100_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
        ]);

        return ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/example'.ContentPost::query()->count().'/',
            'thumbnail_url' => $thumbnailUrl,
            'platform' => 'instagram',
            'format' => 'reel',
            'hook' => 'Example',
            'caption' => 'Example',
            'views' => 10_000,
            'likes' => 1_000,
            'comments' => 100,
            'published_at' => now(),
        ]);
    }
}
