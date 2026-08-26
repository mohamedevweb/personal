<?php

namespace Tests\Feature\Instagram;

use App\Services\Discovery\HikerInstagramProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HikerInstagramProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discovery.hiker.api_key' => 'hiker-test-key',
            'services.discovery.hiker.base_url' => 'https://api.hikerapi.test',
            'services.discovery.hiker.timeout' => 10,
            'services.discovery.hiker.retries' => 1,
            'services.discovery.hiker.retry_delay_ms' => 1,
        ]);
    }

    public function test_it_normalizes_profiles_posts_search_and_related_accounts(): void
    {
        Http::fake(function ($request) {
            $this->assertSame('hiker-test-key', $request->header('x-access-key')[0]);

            if (str_contains($request->url(), '/v1/user/by/username')) {
                return Http::response([
                    'pk' => 'creator-1',
                    'username' => 'saas.builder',
                    'full_name' => 'SaaS Builder',
                    'biography' => 'Building AI SaaS in public',
                    'follower_count' => 25_000,
                    'is_private' => false,
                ]);
            }

            if (str_contains($request->url(), '/v1/user/medias/chunk')) {
                return Http::response([[[
                    'pk' => 'media-1',
                    'code' => 'ABC123',
                    'taken_at' => '2026-08-19T10:00:00Z',
                    'media_type' => 2,
                    'product_type' => 'clips',
                    'user' => ['username' => 'saas.builder'],
                    'caption_text' => 'How I built this #aisaas product',
                    'like_count' => 1200,
                    'comment_count' => 80,
                    'play_count' => 45_000,
                    'thumbnail_url' => 'https://cdn.example.test/reel.jpg',
                    'usertags' => [],
                ], [
                    'pk' => 'media-2',
                    'code' => 'CAROUSEL123',
                    'taken_at' => '2026-08-18T10:00:00Z',
                    'media_type' => 8,
                    'user' => ['username' => 'saas.builder'],
                    'caption_text' => 'A carousel #aisaas',
                    'like_count' => 900,
                    'comment_count' => 50,
                    'thumbnail_url' => 'https://cdn.example.test/carousel-cover.jpg',
                    'carousel_media' => [[
                        'image_versions2' => ['candidates' => [[
                            'url' => 'https://cdn.example.test/carousel-1.jpg',
                        ]]],
                    ], [
                        'image_versions2' => ['candidates' => [[
                            'url' => 'https://cdn.example.test/carousel-2.jpg',
                        ]]],
                    ]],
                ]], null]);
            }

            if (str_contains($request->url(), '/v2/fbsearch/accounts')) {
                return Http::response(['users' => [[
                    'pk' => 'seed-1',
                    'username' => 'ai.founder',
                    'full_name' => 'AI Founder',
                    'is_private' => false,
                ]]]);
            }

            return Http::response(['suggested_users' => [[
                'pk' => 'related-1',
                'username' => 'indie.hacker',
                'full_name' => 'Indie Hacker',
                'is_private' => false,
            ]]]);
        });

        $provider = app(HikerInstagramProvider::class);
        $profile = $provider->getProfile('saas.builder');
        $posts = $provider->getPosts('saas.builder', 12, $profile?->externalId);
        $search = $provider->searchAccounts('AI founder', 5);
        $related = $provider->getRelatedAccounts('seed-1', 5);

        $this->assertSame('creator-1', $profile?->externalId);
        $this->assertSame(25_000, $profile?->followers);
        $this->assertSame('media-1', $posts->first()?->externalId);
        $this->assertSame('reel', $posts->first()?->format);
        $this->assertSame(45_000, $posts->first()?->views);
        $this->assertSame(['aisaas'], $posts->first()?->hashtags);
        $this->assertSame([
            'https://cdn.example.test/carousel-1.jpg',
            'https://cdn.example.test/carousel-2.jpg',
        ], $posts->get(1)?->mediaUrls);
        $this->assertSame('ai.founder', $search->first()?->username);
        $this->assertSame('indie.hacker', $related->first()?->username);
    }
}
