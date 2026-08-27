<?php

namespace Tests\Feature\Instagram;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\InstagramAccount;
use App\Models\Remix;
use App\Models\User;
use App\Services\View\ContentPostView;
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

    public function test_signed_media_uses_its_own_request_budget(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $source = 'https://scontent-sea5-1.cdninstagram.com/rate-limit.jpg';
        Http::fake([$source => Http::response('jpeg-content', 200, ['Content-Type' => 'image/jpeg'])]);
        $post = $this->createPost($source);
        $path = URL::temporarySignedRoute('media.content', now()->addHour(), ['content' => $post], absolute: false);

        foreach (range(1, 121) as $request) {
            $this->get($path)->assertOk();
        }

        Http::assertSentCount(1);
    }

    public function test_media_route_requires_a_valid_signature(): void
    {
        $post = $this->createPost('https://scontent-sea5-1.cdninstagram.com/photo.jpg');

        $this->get("/api/media/content/{$post->id}")->assertForbidden();
    }

    public function test_connected_user_avatar_uses_the_signed_media_proxy_across_api_responses(): void
    {
        config(['app.url' => 'https://api.personal.test']);
        Storage::fake('local');
        Http::preventStrayRequests();
        $source = 'https://scontent-sea5-1.cdninstagram.com/avatar.jpg';
        Http::fake([$source => Http::response('avatar-content', 200, ['Content-Type' => 'image/jpeg'])]);
        $user = User::factory()->create();
        InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => 'instagram-user',
            'username' => 'personal_creator',
            'profile_picture_url' => $source,
            'access_token' => 'encrypted-secret',
            'sync_status' => 'completed',
            'connected_at' => now(),
        ]);

        $authAvatar = $this->actingAs($user)->getJson('/api/auth/me')->assertOk()->json('user.avatar_url');
        $profileAvatar = $this->actingAs($user)->getJson('/api/me/profile')->assertOk()->json('instagram.profile_picture_url');
        $statusAvatar = $this->actingAs($user)->getJson('/api/integrations/instagram/status')->assertOk()->json('account.profile_picture_url');

        $this->assertIsString($authAvatar);
        $this->assertStringStartsWith('https://api.personal.test/api/media/instagram-account/', $authAvatar);
        $this->assertStringContainsString('signature=', $authAvatar);
        $this->assertSame($authAvatar, $profileAvatar);
        $this->assertSame($authAvatar, $statusAvatar);

        $relativeAvatar = parse_url($authAvatar, PHP_URL_PATH).'?'.parse_url($authAvatar, PHP_URL_QUERY);
        $this->get($relativeAvatar)
            ->assertOk()
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->assertContent('avatar-content');
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

    public function test_signed_reel_route_proxies_partial_video_content(): void
    {
        config(['services.instagram_media_proxy.max_video_bytes' => 1024]);
        Http::preventStrayRequests();
        $source = 'https://scontent-sea5-1.cdninstagram.com/reel.mp4';
        Http::fake([$source => Http::response('mp4-', 206, [
            'Accept-Ranges' => 'bytes',
            'Content-Length' => '4',
            'Content-Range' => 'bytes 0-3/12',
            'Content-Type' => 'video/mp4',
        ])]);
        $post = $this->createPost('https://scontent-sea5-1.cdninstagram.com/reel.jpg');
        $post->update(['video_url' => $source]);
        $path = URL::temporarySignedRoute('media.content.video', now()->addHour(), ['content' => $post], absolute: false);

        $this->get($path, ['Range' => 'bytes=0-3'])
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Content-Range', 'bytes 0-3/12')
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
            ->assertStreamedContent('mp4-');

        Http::assertSent(fn ($request): bool => $request->header('Range')[0] === 'bytes=0-3');
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
        ], 'video_url' => 'https://scontent-sea5-1.cdninstagram.com/reel.mp4']);

        $payload = app(ContentPostView::class)->make($post->fresh(), $user);

        $this->assertStringStartsWith('https://api.personal.test/api/media/content/', $payload['thumbnail_url']);
        $this->assertStringContainsString('signature=', $payload['thumbnail_url']);
        $this->assertStringStartsWith('https://api.personal.test/api/media/content/', $payload['video_url']);
        $this->assertStringContainsString('/video?', $payload['video_url']);
        $this->assertCount(2, $payload['media_urls']);
        $this->assertSame($payload['thumbnail_url'], $payload['media_urls'][0]);
        $this->assertStringStartsWith('https://api.personal.test/api/media/content/', $payload['media_urls'][1]);
        $this->assertStringContainsString('/1?', $payload['media_urls'][1]);
        $this->assertStringStartsWith('https://api.personal.test/api/media/creator/', $payload['creator']['avatar_url']);

        $post->update(['thumbnail_url' => 'https://images.unsplash.com/photo.jpg']);
        $payload = app(ContentPostView::class)->make($post->fresh(), $user);
        $this->assertSame('https://images.unsplash.com/photo.jpg', $payload['thumbnail_url']);
    }

    public function test_feed_media_urls_are_stable_within_the_signature_hour(): void
    {
        config(['app.url' => 'https://api.personal.test']);
        $this->travelTo('2026-08-23 10:05:00');
        $user = User::factory()->create();
        $post = $this->createPost('https://instagram.ftce2-1.fna.fbcdn.net/thumb.jpg');

        $firstUrl = app(ContentPostView::class)->make($post, $user)['thumbnail_url'];
        $this->travel(30)->minutes();
        $secondUrl = app(ContentPostView::class)->make($post, $user)['thumbnail_url'];
        $this->travelBack();

        $this->assertSame($firstUrl, $secondUrl);
    }

    public function test_remix_source_uses_signed_personal_urls_for_instagram_media(): void
    {
        config(['app.url' => 'https://api.personal.test']);
        $user = User::factory()->create();
        $post = $this->createPost('https://instagram.ftce2-1.fna.fbcdn.net/thumb.jpg');
        $post->creator->update(['avatar_url' => 'https://scontent-sea5-1.cdninstagram.com/avatar.jpg']);
        $remix = Remix::query()->create([
            'user_id' => $user->id,
            'source_content_id' => $post->id,
            'format' => 'reel',
            'generated_content' => [],
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/remixes/{$remix->id}")
            ->assertOk();

        $this->assertStringStartsWith(
            'https://api.personal.test/api/media/content/',
            $response->json('remix.source_content.thumbnail_url'),
        );
        $this->assertStringStartsWith(
            'https://api.personal.test/api/media/creator/',
            $response->json('remix.source_content.creator.avatar_url'),
        );
    }

    public function test_feed_deduplicates_repeated_carousel_images(): void
    {
        $user = User::factory()->create();
        $source = 'https://instagram.ftce2-1.fna.fbcdn.net/thumb.jpg';
        $post = $this->createPost($source);
        $post->update(['media_urls' => [$source, $source]]);

        $payload = app(ContentPostView::class)->make($post->fresh(), $user);

        $this->assertSame([$payload['thumbnail_url']], $payload['media_urls']);
    }

    public function test_reel_media_uses_the_thumbnail_route_when_no_carousel_rows_exist(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost('https://instagram.ftce2-1.fna.fbcdn.net/reel.jpg');

        $payload = app(ContentPostView::class)->make($post, $user);

        $this->assertSame([$payload['thumbnail_url']], $payload['media_urls']);
        $this->assertStringNotContainsString("/{$post->id}/0?", $payload['media_urls'][0]);
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
