<?php

namespace Tests\Feature\Discovery;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use App\Services\Discovery\CreatorCatalog;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportDiscoveredCreatorsTest extends TestCase
{
    use RefreshDatabase;

    private array $seedEntry = [
        'handle' => 'coach', 'market' => 'FR', 'vertical' => 'sport-fitness',
        'topics' => ['coaching'],
        'rationale' => 'Recognized coach.', 'status' => 'approved',
    ];

    public function test_import_writes_accepted_candidates_as_discovered_and_dispatches_measurement(): void
    {
        Queue::fake();
        $this->mockCatalog();

        $seed = $this->profile();
        $candidate = $this->profile(username: 'related_coach');
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->with('coach')->once()->andReturn($seed);
        $provider->shouldReceive('getRelatedAccounts')->with('instagram-1', 3, 'coach')->once()->andReturn(collect([$candidate]));
        $provider->shouldReceive('getProfile')->with('related_coach')->once()->andReturn($candidate);
        $provider->shouldReceive('getPosts')->with('related_coach', 30, 'instagram-1')->once()->andReturn(collect());
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->with('mock')->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:import-discovered-creators --provider=mock --max=1')->assertSuccessful();

        $this->assertDatabaseCount('creators', 1);
        $creator = Creator::query()->where('username', 'related_coach')->firstOrFail();
        $this->assertSame('discovered', $creator->curation_status);
        $this->assertFalse($creator->is_catalog_seed);
        $this->assertSame('FR', $creator->market);
        Queue::assertPushed(MeasureAccountEngagement::class, fn (MeasureAccountEngagement $job): bool => in_array('related_coach', $job->usernames, true));
    }

    public function test_import_skips_candidates_already_known_in_the_database(): void
    {
        Queue::fake();
        Creator::query()->create([
            'username' => 'related_coach',
            'display_name' => 'Related Coach',
            'niche' => 'sport-fitness',
            'followers' => 50000,
            'average_views' => 1000,
            'average_likes' => 100,
        ]);
        $this->mockCatalog();

        $seed = $this->profile();
        $candidate = $this->profile(username: 'related_coach');
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->with('coach')->once()->andReturn($seed);
        $provider->shouldReceive('getRelatedAccounts')->with('instagram-1', 3, 'coach')->once()->andReturn(collect([$candidate]));
        $provider->shouldReceive('getProfile')->with('related_coach')->never();
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->with('mock')->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:import-discovered-creators --provider=mock --max=1')->assertSuccessful();

        $this->assertDatabaseCount('creators', 1);
        Queue::assertNothingPushed();
    }

    public function test_import_rejects_candidates_that_fail_eligibility(): void
    {
        Queue::fake();
        $this->mockCatalog();

        $seed = $this->profile();
        $privateCandidate = $this->profile(username: 'related_coach', isPrivate: true);
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->with('coach')->once()->andReturn($seed);
        $provider->shouldReceive('getRelatedAccounts')->with('instagram-1', 3, 'coach')->once()->andReturn(collect([$privateCandidate]));
        $provider->shouldReceive('getProfile')->with('related_coach')->once()->andReturn($privateCandidate);
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->with('mock')->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:import-discovered-creators --provider=mock --max=1')->assertSuccessful();

        $this->assertDatabaseCount('creators', 0);
        Queue::assertNothingPushed();
    }

    private function mockCatalog(): void
    {
        $catalog = \Mockery::mock(CreatorCatalog::class);
        $catalog->shouldReceive('approved')->once()->andReturn([$this->seedEntry]);
        $catalog->shouldReceive('entries')->once()->andReturn([$this->seedEntry]);
        $this->app->instance(CreatorCatalog::class, $catalog);
    }

    private function profile(
        string $username = 'coach',
        ?string $bio = 'Coach en France avec des conseils pour le sport et la musculation à Paris',
        bool $isPrivate = false,
        int $metricPosts = 6,
        int $daysAgo = 1,
        int $postCount = 6,
    ): DiscoveredProfile {
        $posts = collect(range(1, $postCount))->map(fn (int $index): DiscoveredPost => new DiscoveredPost(
            sourceUrl: "https://instagram.test/p/{$username}-{$index}",
            username: $username,
            displayName: 'Coach',
            avatarUrl: null,
            followers: 100000,
            caption: $bio ?? '',
            thumbnailUrl: null,
            likes: $index <= $metricPosts ? 900 : 0,
            comments: $index <= $metricPosts ? 100 : 0,
            views: $index <= $metricPosts ? 10000 : 0,
            publishedAt: CarbonImmutable::now()->subDays($daysAgo + $index),
            format: 'reel',
            hashtags: ['fitness'],
            externalId: "post-{$index}",
            metadata: ['providers' => ['scrapecreators' => ['provider' => 'scrapecreators']]],
        ));

        return new DiscoveredProfile(
            username: $username,
            displayName: 'Coach',
            avatarUrl: null,
            followers: 100000,
            posts: $posts,
            bio: $bio,
            externalId: 'instagram-1',
            isPrivate: $isPrivate,
            metadata: ['providers' => ['scrapecreators' => ['provider' => 'scrapecreators']]],
        );
    }
}
