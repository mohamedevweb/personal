<?php

namespace Tests\Feature\Instagram;

use App\Exceptions\ContentDiscoveryException;
use App\Services\Discovery\ScrapeCreatorsInstagramProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeCreatorsInstagramProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discovery.scrapecreators.api_key' => 'scrapecreators-test-key',
            'services.discovery.scrapecreators.base_url' => 'https://api.scrapecreators.test',
            'services.discovery.scrapecreators.timeout' => 10,
            'services.discovery.scrapecreators.retries' => 1,
            'services.discovery.scrapecreators.retry_delay_ms' => 1,
            'services.discovery.scrapecreators.cache_max_age' => '3d',
        ]);
    }

    public function test_it_recovers_the_slides_of_a_carousel_from_the_single_post_endpoint(): void
    {
        Http::fake(['*/v1/instagram/post*' => Http::response([
            'success' => true,
            'data' => ['xdt_shortcode_media' => [
                'id' => '3931485019532992411',
                'shortcode' => 'DaPcMudCJ-b',
                'display_url' => 'https://scontent.cdninstagram.com/cover.jpg',
                'video_url' => null,
                'edge_sidecar_to_children' => ['edges' => [
                    ['node' => ['display_url' => 'https://scontent.cdninstagram.com/slide-1.jpg']],
                    ['node' => ['display_url' => 'https://scontent.cdninstagram.com/slide-2.jpg']],
                    ['node' => ['display_url' => 'https://scontent.cdninstagram.com/slide-3.jpg']],
                ]],
            ]],
        ])]);

        $media = (new ScrapeCreatorsInstagramProvider)->getPostMedia('https://www.instagram.com/p/DaPcMudCJ-b/');

        $this->assertSame([
            'https://scontent.cdninstagram.com/slide-1.jpg',
            'https://scontent.cdninstagram.com/slide-2.jpg',
            'https://scontent.cdninstagram.com/slide-3.jpg',
        ], $media?->mediaUrls);
        $this->assertNull($media?->videoUrl);
    }

    public function test_it_recovers_a_playable_video_url_and_duration_for_a_reel(): void
    {
        Http::fake(['*/v1/instagram/post*' => Http::response([
            'success' => true,
            'data' => ['xdt_shortcode_media' => [
                'id' => '3937',
                'shortcode' => 'DaYJLnrI-ny',
                'is_video' => true,
                'video_url' => 'https://instagram.fcdg1-1.fna.fbcdn.net/o1/v/t2/f2/reel.mp4',
                'video_duration' => 52.8,
                'thumbnail_src' => 'https://scontent.cdninstagram.com/reel-cover.jpg',
            ]],
        ])]);

        $media = (new ScrapeCreatorsInstagramProvider)->getPostMedia('https://www.instagram.com/reel/DaYJLnrI-ny/');

        $this->assertSame('https://instagram.fcdg1-1.fna.fbcdn.net/o1/v/t2/f2/reel.mp4', $media?->videoUrl);
        $this->assertSame(53, $media?->videoDurationSeconds);
    }

    public function test_a_post_the_provider_no_longer_serves_is_null_rather_than_an_error(): void
    {
        Http::fake(['*/v1/instagram/post*' => Http::response('', 404)]);

        $this->assertNull((new ScrapeCreatorsInstagramProvider)->getPostMedia('https://www.instagram.com/p/gone/'));
    }

    public function test_it_normalizes_profile_timeline_posts_search_posts_and_related_accounts(): void
    {
        Http::fake(function ($request) {
            $this->assertSame('scrapecreators-test-key', $request->header('x-api-key')[0]);

            if (str_contains($request->url(), '/v1/instagram/search/profiles')) {
                return Http::response([
                    'success' => true,
                    'profiles' => [[
                        'id' => 'creator-1',
                        'username' => 'saas.builder',
                        'full_name' => 'SaaS Builder',
                        'biography' => 'Building AI SaaS in public',
                        'follower_count' => 25_000,
                        'following_count' => 400,
                        'media_count' => 210,
                        'is_private' => false,
                        'is_verified' => true,
                        'matched_from' => 'profile',
                    ]],
                ]);
            }

            if (str_contains($request->url(), '/v2/instagram/user/posts')) {
                return Http::response([
                    'success' => true,
                    'user' => [
                        'id' => 'creator-1',
                        'username' => 'saas.builder',
                        'full_name' => 'SaaS Builder',
                    ],
                    'items' => [[
                        'id' => 'media-2_creator-1',
                        'code' => 'CAROUSEL2',
                        'taken_at' => 1_787_130_000,
                        'media_type' => 8,
                        'user' => ['username' => 'saas.builder'],
                        'caption' => ['text' => 'The launch checklist #buildinpublic'],
                        'like_count' => 900,
                        'comment_count' => 50,
                        'display_uri' => 'https://cdn.example.test/carousel.jpg',
                        'carousel_media' => [[
                            'display_uri' => 'https://cdn.example.test/carousel-1.jpg',
                        ], [
                            'display_uri' => 'https://cdn.example.test/carousel-2.jpg',
                        ]],
                    ]],
                ]);
            }

            return Http::response([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => 'creator-1',
                        'username' => 'saas.builder',
                        'full_name' => 'SaaS Builder',
                        'biography' => 'Building AI SaaS in public',
                        'profile_pic_url_hd' => 'https://cdn.example.test/avatar.jpg',
                        'edge_followed_by' => ['count' => 25_000],
                        'edge_follow' => ['count' => 400],
                        'is_private' => false,
                        'is_verified' => true,
                        'category_name' => 'Entrepreneur',
                        'edge_owner_to_timeline_media' => [
                            'count' => 210,
                            'edges' => [[
                                'node' => [
                                    'id' => 'media-1',
                                    'shortcode' => 'REEL1',
                                    '__typename' => 'GraphVideo',
                                    'product_type' => 'clips',
                                    'taken_at_timestamp' => 1_787_126_400,
                                    'edge_media_to_caption' => ['edges' => [[
                                        'node' => ['text' => 'How I built this #aisaas product'],
                                    ]]],
                                    'edge_media_preview_like' => ['count' => 1200],
                                    'edge_media_to_comment' => ['count' => 80],
                                    'video_view_count' => 45_000,
                                    'thumbnail_src' => 'https://cdn.example.test/reel.jpg',
                                    'video_url' => 'https://cdn.example.test/reel.mp4',
                                ],
                            ]],
                        ],
                        'edge_related_profiles' => ['edges' => [[
                            'node' => [
                                'id' => 'related-1',
                                'username' => 'indie.hacker',
                                'full_name' => 'Indie Hacker',
                                'is_private' => false,
                            ],
                        ]]],
                    ],
                ],
            ]);
        });

        $provider = app(ScrapeCreatorsInstagramProvider::class);
        $profile = $provider->getProfile('@saas.builder');
        $posts = $provider->getPosts('saas.builder', 12, $profile?->externalId);
        $search = $provider->searchAccounts('AI founder', 5);
        $related = $provider->getRelatedAccounts('creator-1', 5, 'saas.builder');

        $this->assertSame('creator-1', $profile?->externalId);
        $this->assertSame(25_000, $profile?->followers);
        $this->assertSame('media-1', $profile?->posts->first()?->externalId);
        $this->assertSame('reel', $profile?->posts->first()?->format);
        $this->assertSame(45_000, $profile?->posts->first()?->views);
        $this->assertSame('https://cdn.example.test/reel.mp4', $profile?->posts->first()?->videoUrl);
        $this->assertSame(['aisaas'], $profile?->posts->first()?->hashtags);
        $this->assertSame('media-2', $posts->first()?->externalId);
        $this->assertSame('carousel', $posts->first()?->format);
        $this->assertSame(['buildinpublic'], $posts->first()?->hashtags);
        $this->assertSame([
            'https://cdn.example.test/carousel-1.jpg',
            'https://cdn.example.test/carousel-2.jpg',
        ], $posts->first()?->mediaUrls);
        $this->assertSame('saas.builder', $search->first()?->username);
        $this->assertSame(25_000, $search->first()?->followers);
        $this->assertSame('indie.hacker', $related->first()?->username);
        $this->assertSame(
            'profile',
            data_get($search->first()?->metadata, 'providers.scrapecreators.raw_data.matched_from'),
        );

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1/instagram/profile')
            && $request['handle'] === 'saas.builder'
            && $request['cache_max_age'] === '3d');
    }

    public function test_profile_not_found_returns_null(): void
    {
        Http::fake(['*' => Http::response([], 404)]);

        $this->assertNull(app(ScrapeCreatorsInstagramProvider::class)->getProfile('missing'));
    }

    public function test_scheduled_profile_refresh_bypasses_provider_cache(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => ['user' => ['id' => 'creator-1', 'username' => 'fresh.creator']],
        ])]);

        app(ScrapeCreatorsInstagramProvider::class)->getProfile('fresh.creator', fresh: true);

        Http::assertSent(fn ($request): bool => $request['handle'] === 'fresh.creator'
            && ! isset($request['cache_max_age']));
    }

    public function test_provider_failures_become_domain_exceptions(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 500)]);

        $this->expectException(ContentDiscoveryException::class);
        $this->expectExceptionMessage('ScrapeCreators failed (HTTP 500).');

        app(ScrapeCreatorsInstagramProvider::class)->searchAccounts('fitness', 5);
    }

    public function test_provider_failure_includes_safe_api_error_detail(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Insufficient credits'], 402)]);

        $this->expectException(ContentDiscoveryException::class);
        $this->expectExceptionMessage('ScrapeCreators failed (HTTP 402). Insufficient credits');

        app(ScrapeCreatorsInstagramProvider::class)->getProfile('fitness');
    }

    public function test_rate_limit_responses_are_retried(): void
    {
        config(['services.discovery.scrapecreators.retries' => 2]);
        Http::fakeSequence()
            ->push(['success' => false], 429)
            ->push(['success' => true, 'profiles' => []]);

        $this->assertCount(0, app(ScrapeCreatorsInstagramProvider::class)->searchAccounts('fitness', 5));
        Http::assertSentCount(2);
    }
}
