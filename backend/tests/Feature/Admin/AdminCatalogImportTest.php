<?php

namespace Tests\Feature\Admin;

use App\Jobs\Content\AnalyzeContentPost;
use App\Jobs\Content\TranscribeContentPost;
use App\Jobs\Discovery\AdminCatalogImport;
use App\Models\AdminCatalogImport as AdminCatalogImportRecord;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\User;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorMarketDetector;
use App\Services\Discovery\CreatorNicheCatalog;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use App\Services\Discovery\PostMetricsLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class AdminCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.discovery.driver' => 'mock',
            'services.discovery.safety.enabled' => false,
            'services.discovery.min_followers' => 1,
        ]);
    }

    public function test_a_post_import_measures_the_creator_and_stores_the_outlier(): void
    {
        Bus::fake();
        $user = User::factory()->create(['email' => 'mohamedchettah0208@gmail.com']);
        $import = AdminCatalogImportRecord::query()->create([
            'initiated_by' => $user->id,
            'type' => 'post',
            'url' => 'https://www.instagram.com/p/direct-post/',
            'creator_username' => 'studio.food',
            'vertical' => 'food-cooking',
            'country_code' => 'FR',
        ]);
        $profile = $this->profile();
        $post = new DiscoveredPost(
            sourceUrl: $import->url,
            username: 'studio.food',
            displayName: 'Studio Food',
            avatarUrl: null,
            followers: 100000,
            caption: 'A direct recipe post',
            thumbnailUrl: null,
            likes: 5000,
            comments: 500,
            views: 100000,
            publishedAt: CarbonImmutable::now()->subDay(),
            format: 'reel',
            hashtags: ['food'],
            externalId: 'direct-post',
        );
        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->with('studio.food', true)->once()->andReturn($profile);
        $provider->shouldReceive('getPost')->with($import->url, 'studio.food')->once()->andReturn($post);

        $this->app->instance(InstagramDataProvider::class, $provider);
        (new AdminCatalogImport($import->id))->handle(
            $provider,
            app(CreatorNicheService::class),
            app(CreatorNicheCatalog::class),
            app(OutlierScore::class),
            app(ContentSafetyPolicy::class),
            app(PostMetricsLifecycle::class),
            app(CreatorMarketDetector::class),
        );

        $import->refresh();
        $creator = Creator::query()->where('username', 'studio.food')->firstOrFail();
        $content = ContentPost::query()->where('instagram_media_id', 'direct-post')->firstOrFail();

        $this->assertSame('completed', $import->status);
        $this->assertSame($creator->id, $import->creator_id);
        $this->assertSame($content->id, $import->content_post_id);
        $this->assertSame('food-cooking', $creator->primary_vertical);
        $this->assertSame('FR', $creator->market);
        $this->assertSame('approved', $creator->curation_status);
        $this->assertGreaterThan(0, $content->outlier_score);
        Bus::assertChained([
            TranscribeContentPost::class,
            AnalyzeContentPost::class,
        ]);
    }

    public function test_a_post_import_updates_editorial_values_on_an_already_measured_creator(): void
    {
        Bus::fake();
        $user = User::factory()->create(['email' => 'mohamedchettah0208@gmail.com']);
        $creator = Creator::query()->create([
            'username' => 'studio.food',
            'display_name' => 'Studio Food',
            'niche' => 'Food',
            'followers' => 100000,
            'average_views' => 10000,
            'average_likes' => 1000,
            'performance_baselines' => ['views' => 10000, 'engagement' => 1100, 'posts' => 6],
            'last_measured_at' => now(),
            'safety_status' => 'allowed',
        ]);
        $import = AdminCatalogImportRecord::query()->create([
            'initiated_by' => $user->id,
            'creator_id' => $creator->id,
            'type' => 'post',
            'url' => 'https://www.instagram.com/p/direct-post/',
            'creator_username' => $creator->username,
            'vertical' => 'business',
            'country_code' => 'GB',
        ]);
        $post = new DiscoveredPost(
            sourceUrl: $import->url,
            username: $creator->username,
            displayName: $creator->display_name,
            avatarUrl: null,
            followers: $creator->followers,
            caption: 'A direct post',
            thumbnailUrl: null,
            likes: 2000,
            comments: 100,
            views: 20000,
            publishedAt: CarbonImmutable::now()->subDay(),
            format: 'image',
            hashtags: [],
            externalId: 'direct-post-2',
        );
        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getPost')->with($import->url, $creator->username)->once()->andReturn($post);

        (new AdminCatalogImport($import->id))->handle(
            $provider,
            app(CreatorNicheService::class),
            app(CreatorNicheCatalog::class),
            app(OutlierScore::class),
            app(ContentSafetyPolicy::class),
            app(PostMetricsLifecycle::class),
            app(CreatorMarketDetector::class),
        );

        $creator->refresh();
        $this->assertSame('business', $creator->primary_vertical);
        $this->assertSame('GB', $creator->market);
        $this->assertSame('approved', $creator->curation_status);
    }

    private function profile(): DiscoveredProfile
    {
        $posts = collect(range(1, 6))->map(fn (int $index): DiscoveredPost => new DiscoveredPost(
            sourceUrl: "https://www.instagram.com/p/profile-{$index}/",
            username: 'studio.food',
            displayName: 'Studio Food',
            avatarUrl: null,
            followers: 100000,
            caption: "Recipe {$index} for the week",
            thumbnailUrl: null,
            likes: 1000,
            comments: 100,
            views: 10000,
            publishedAt: CarbonImmutable::now()->subDays($index),
            format: 'reel',
            hashtags: ['food'],
            externalId: "profile-{$index}",
        ));

        return new DiscoveredProfile(
            username: 'studio.food',
            displayName: 'Studio Food',
            avatarUrl: null,
            followers: 100000,
            posts: $posts,
            bio: 'Food creator in France',
            externalId: 'creator-1',
        );
    }
}
