<?php

namespace Tests\Feature\Instagram;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorNicheCatalog;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InstagramProviderDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_measurement_of_the_same_instagram_ids_updates_existing_rows(): void
    {
        config([
            'services.discovery.measure_cooldown_days' => 0,
            'services.discovery.min_followers' => 1_000_000,
        ]);

        $this->measure($this->provider('scrapecreators', 100, 1000));
        $this->measure($this->provider('scrapecreators', 120, 1500));

        $this->assertSame(1, Creator::query()->count());
        $this->assertSame(1, ContentPost::query()->count());
        $this->assertSame(120, Creator::query()->firstOrFail()->followers);
        $this->assertSame(1500, ContentPost::query()->firstOrFail()->views);
        $this->assertSame(
            ['scrapecreators'],
            array_keys(Creator::query()->firstOrFail()->metadata['providers']),
        );
        $this->assertSame(
            ['scrapecreators'],
            array_keys(ContentPost::query()->firstOrFail()->metadata['providers']),
        );
    }

    private function measure(InstagramDataProvider $provider): void
    {
        (new MeasureAccountEngagement(['same.creator']))->handle(
            $provider,
            app(CreatorNicheService::class),
            app(CreatorNicheCatalog::class),
            app(OutlierScore::class),
            app(ContentSafetyPolicy::class),
        );
    }

    private function provider(string $name, int $followers, int $views): InstagramDataProvider
    {
        $post = new DiscoveredPost(
            sourceUrl: 'https://www.instagram.com/reel/SAMEPOST/',
            username: 'same.creator',
            displayName: 'Same Creator',
            avatarUrl: null,
            followers: $followers,
            caption: 'The same post',
            thumbnailUrl: null,
            likes: 50,
            comments: 5,
            views: $views,
            publishedAt: CarbonImmutable::now(),
            format: 'reel',
            hashtags: [],
            externalId: 'instagram-media-1',
            metadata: ['providers' => [$name => ['raw_data' => ['id' => 'instagram-media-1']]]],
        );
        $profile = new DiscoveredProfile(
            username: 'same.creator',
            displayName: 'Same Creator',
            avatarUrl: null,
            followers: $followers,
            posts: collect([$post]),
            externalId: 'instagram-user-1',
            metadata: [
                'country_code' => 'US',
                'providers' => [$name => ['raw_data' => ['id' => 'instagram-user-1']]],
            ],
        );
        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with('same.creator', true)->andReturn($profile);

        return $provider;
    }
}
