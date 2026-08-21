<?php

namespace Tests\Feature;

use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class InstagramProviderComparisonCommandTest extends TestCase
{
    public function test_it_compares_both_providers_on_the_same_query(): void
    {
        $manager = Mockery::mock(InstagramDataProviderManager::class);

        foreach (['hiker', 'scrapecreators'] as $index => $driver) {
            $profile = $this->profile($driver, 10_000 + $index, 50_000 + $index);
            $provider = Mockery::mock(InstagramDataProvider::class);
            $provider->shouldReceive('searchAccounts')->once()->with('fitness coach', 1)->andReturn(collect([$profile]));
            $provider->shouldReceive('getProfile')->once()->with($profile->username)->andReturn($profile);
            $manager->shouldReceive('provider')->once()->with($driver)->andReturn($provider);
        }

        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:compare-instagram-providers', [
            'query' => 'fitness coach',
            '--creators' => 1,
            '--posts' => 1,
        ])
            ->expectsOutputToContain('Query: fitness coach')
            ->expectsOutputToContain('HikerAPI')
            ->expectsOutputToContain('ScrapeCreators')
            ->assertSuccessful();
    }

    private function profile(string $driver, int $followers, int $views): DiscoveredProfile
    {
        $username = $driver.'.creator';
        $post = new DiscoveredPost(
            sourceUrl: 'https://www.instagram.com/reel/'.$driver.'/',
            username: $username,
            displayName: ucfirst($driver).' Creator',
            avatarUrl: null,
            followers: $followers,
            caption: 'A relevant fitness coaching reel',
            thumbnailUrl: null,
            likes: 1000,
            comments: 50,
            views: $views,
            publishedAt: CarbonImmutable::now(),
            format: 'reel',
            hashtags: ['fitness'],
            externalId: $driver.'-post',
        );

        return new DiscoveredProfile(
            username: $username,
            displayName: ucfirst($driver).' Creator',
            avatarUrl: null,
            followers: $followers,
            posts: collect([$post]),
            bio: 'Fitness coach',
            externalId: $driver.'-creator',
        );
    }
}
