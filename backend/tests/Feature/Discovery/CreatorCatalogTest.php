<?php

namespace Tests\Feature\Discovery;

use App\Exceptions\ContentDiscoveryException;
use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\Discovery\CreatorCatalog;
use App\Services\Discovery\CreatorCatalogEligibility;
use App\Services\Discovery\CreatorCatalogImporter;
use App\Services\Discovery\CreatorMarketDetector;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreatorCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_has_exact_golden_catalog_quotas_and_sources(): void
    {
        $entries = collect(app(CreatorCatalog::class)->entries());

        $this->assertCount(30, $entries);
        $this->assertCount(30, $entries->pluck('handle')->map(fn (string $handle): string => strtolower($handle))->unique());

        foreach (array_keys(config('creator_catalog.verticals')) as $vertical) {
            $group = $entries->where('vertical', $vertical);
            $this->assertCount(5, $group);
            $this->assertTrue($group->every(fn (array $entry): bool => $entry['market'] === 'FR'));
        }

        $this->assertCount(30, $entries->where('status', 'approved'));
        $this->assertCount(0, $entries->where('status', 'pending'));
        $this->assertEqualsCanonicalizing(
            ['jujufitcats', 'majormouvement', 'caroline.mignaux', 'leotechmaker', 'mrjojol67', 'paulinelaigneau', 'bprkt', 'jbaptisten'],
            $entries->pluck('handle')->intersect(['jujufitcats', 'majormouvement', 'caroline.mignaux', 'leotechmaker', 'mrjojol67', 'paulinelaigneau', 'bprkt', 'jbaptisten'])->all(),
        );
        $this->assertEmpty($entries->pluck('handle')->intersect(['juju_fitcats', 'major_mouvement', 'carolinemignaux', 'leo_techmaker', 'jojol', 'leoduff', 'matthieustefani', 'alexhitchens', 'elarch', 'stevenlathoud', 'delphine.py', 'thebraingutscientist']));
        $this->assertTrue($entries->every(function (array $entry): bool {
            $instagramUrl = "https://www.instagram.com/{$entry['handle']}/";

            return $entry['instagram_url'] === $instagramUrl
                && in_array($instagramUrl, $entry['source_urls'], true)
                && count($entry['source_urls']) >= 2
                && ! array_key_exists('recognition_tier', $entry);
        }));
    }

    public function test_market_detection_distinguishes_fr_gb_us_and_unknown(): void
    {
        $detector = app(CreatorMarketDetector::class);

        $this->assertSame('FR', $detector->detect('Je partage des conseils pour les créateurs en France à Paris')['market']);
        $this->assertSame('GB', $detector->detect('The creator coach with your weekly strategy from London UK')['market']);
        $this->assertSame('US', $detector->detect('The creator coach with your weekly strategy from New York USA')['market']);
        $this->assertNull($detector->detect('The creator sharing weekly productivity tips')['market']);
        $this->assertLessThan(0.70, $detector->detect('The creator sharing weekly productivity tips')['confidence']);
    }

    public function test_audit_eligibility_reports_every_required_rejection_reason(): void
    {
        $entry = [
            'handle' => 'coach', 'market' => 'FR', 'vertical' => 'sport-fitness',
            'status' => 'pending',
        ];
        $eligibility = app(CreatorCatalogEligibility::class);
        $accepted = $eligibility->evaluate($this->profile(), $entry);

        $this->assertTrue($accepted['accepted']);

        $private = $eligibility->evaluate($this->profile(isPrivate: true), $entry);
        $spam = $eligibility->evaluate($this->profile(username: 'fitness_repost'), $entry);
        $missingMetrics = $eligibility->evaluate($this->profile(metricPosts: 2), $entry);
        $wrongMarket = $eligibility->evaluate($this->profile(bio: 'The coach with your workout tips in New York USA'), $entry);
        $inactive = $eligibility->evaluate($this->profile(daysAgo: 45), $entry);

        $this->assertContains('private_account', $private['reasons']);
        $this->assertContains('impersonal_brand_or_aggregator', $spam['reasons']);
        $this->assertContains('metric_coverage_below_minimum', $missingMetrics['reasons']);
        $this->assertContains('median_engagement_below_minimum', $missingMetrics['warnings']);
        $this->assertTrue($wrongMarket['accepted']);
        $this->assertContains('market_signal_mismatch', $wrongMarket['warnings']);
        $this->assertContains('inactive', $inactive['reasons']);

        $unknownMarket = $eligibility->evaluate($this->profile(bio: 'Weekly training and creator tips'), $entry);
        $tierSuggestion = $eligibility->evaluate($this->profile(), array_replace($entry, ['recognition_tier' => 'leader']));

        $this->assertTrue($unknownMarket['accepted']);
        $this->assertContains('market_unverified', $unknownMarket['warnings']);
        $this->assertTrue($tierSuggestion['accepted']);
        $this->assertContains('recognition_tier_mismatch', $tierSuggestion['warnings']);
        $this->assertSame(['Set recognition_tier to established.'], $tierSuggestion['suggestions']);
    }

    public function test_repeated_import_upserts_creator_preserves_provenance_and_deduplicates_measurement(): void
    {
        Queue::fake();
        $profile = $this->profile();
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->twice()->andReturn($profile);
        $entry = [[
            'handle' => 'coach', 'market' => 'FR', 'vertical' => 'sport-fitness',
            'topics' => ['running', 'coaching'],
            'rationale' => 'Recognized coach.', 'status' => 'approved',
        ]];
        Creator::query()->create([
            'username' => 'old_coach', 'instagram_user_id' => 'instagram-1', 'display_name' => 'Old',
            'niche' => 'fitness', 'followers' => 1, 'average_views' => 0, 'average_likes' => 0,
            'metadata' => ['providers' => ['legacy' => ['seen' => true]]],
        ]);

        $importer = app(CreatorCatalogImporter::class);
        $first = $importer->import($entry, $provider);
        $second = $importer->import($entry, $provider);

        $this->assertSame(1, $first['imported']);
        $this->assertSame(1, $second['imported']);
        $this->assertDatabaseCount('creators', 1);
        $creator = Creator::query()->firstOrFail();
        $this->assertSame('coach', $creator->username);
        $this->assertSame('sport-fitness', $creator->niche);
        $this->assertSame('FR', $creator->market);
        $this->assertSame('approved', $creator->curation_status);
        $this->assertSame('established', $creator->recognition_tier);
        $this->assertTrue($creator->is_catalog_seed);
        $this->assertTrue(data_get($creator->metadata, 'providers.legacy.seen'));
        $this->assertSame('scrapecreators', data_get($creator->metadata, 'providers.scrapecreators.provider'));
        $this->assertEqualsCanonicalizing(['sport-fitness', 'running', 'coaching'], $creator->niches()->pluck('slug')->all());
        Queue::assertPushed(MeasureAccountEngagement::class, 1);
    }

    public function test_audit_command_uses_mocked_http_boundary_and_never_writes_database_rows(): void
    {
        Storage::fake('local');
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->times(30)->andReturn($this->profile());
        $provider->shouldNotReceive('getPosts');
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->with('mock')->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:audit-creator-catalog --provider=mock')->assertSuccessful();

        $this->assertDatabaseCount('creators', 0);
        $this->assertDatabaseCount('content_posts', 0);
        $this->assertCount(2, Storage::disk('local')->allFiles('catalog-reports'));
    }

    public function test_audit_fetches_posts_only_when_profile_has_fewer_than_six_posts(): void
    {
        Storage::fake('local');
        $entry = $this->catalogEntry('sparse_coach');
        $catalog = \Mockery::mock(CreatorCatalog::class);
        $catalog->shouldReceive('entries')->once()->andReturn([$entry]);
        $this->app->instance(CreatorCatalog::class, $catalog);

        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with('sparse_coach')->andReturn($this->profile(username: 'sparse_coach', postCount: 2));
        $provider->shouldReceive('getPosts')->once()->with('sparse_coach', 30, 'instagram-1')->andReturn($this->profile(username: 'sparse_coach')->posts);
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->with('mock')->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:audit-creator-catalog --provider=mock')->assertSuccessful();
    }

    public function test_audit_can_limit_provider_calls_to_exact_handles(): void
    {
        Storage::fake('local');
        $first = $this->catalogEntry('first_coach');
        $second = $this->catalogEntry('second_coach');
        $catalog = \Mockery::mock(CreatorCatalog::class);
        $catalog->shouldReceive('entries')->once()->andReturn([$first, $second]);
        $this->app->instance(CreatorCatalog::class, $catalog);

        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with('second_coach')->andReturn($this->profile(username: 'second_coach'));
        $provider->shouldNotReceive('getPosts');
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->with('mock')->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:audit-creator-catalog --provider=mock --handle=second_coach')
            ->assertSuccessful();
    }

    public function test_audit_fails_clearly_when_handles_are_missing_from_deployed_manifest(): void
    {
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldNotReceive('getProfile');
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldNotReceive('provider');
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:audit-creator-catalog --provider=mock --handle=missing_coach')
            ->expectsOutput('No catalog entries matched the supplied filters. Deploy the manifest containing these handles before auditing them.')
            ->assertFailed();
    }

    public function test_audit_reports_provider_error_details_with_null_metrics(): void
    {
        Storage::fake('local');
        $entry = $this->catalogEntry('failed_coach');
        $catalog = \Mockery::mock(CreatorCatalog::class);
        $catalog->shouldReceive('entries')->once()->andReturn([$entry]);
        $this->app->instance(CreatorCatalog::class, $catalog);

        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->andThrow(new ContentDiscoveryException('ScrapeCreators failed with HTTP 402: insufficient credits'));
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->with('mock')->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->artisan('personal:audit-creator-catalog --provider=mock')->assertSuccessful();

        $json = collect(Storage::disk('local')->allFiles('catalog-reports'))->first(fn (string $path): bool => str_ends_with($path, '.json'));
        $report = json_decode(Storage::disk('local')->get($json), true);
        $row = $report['entries'][0];

        $this->assertSame('error', $row['provider_status']);
        $this->assertSame('ScrapeCreators failed with HTTP 402: insufficient credits', $row['provider_error']);
        $this->assertNull($row['followers']);
        $this->assertNull($row['accepted']);
        $this->assertSame(1, $report['summary']['provider_errors']);
        $this->assertSame(0, $report['summary']['rejected']);
    }

    public function test_audit_retry_report_only_retries_provider_errors(): void
    {
        Storage::fake('local');
        $failed = $this->catalogEntry('failed_coach');
        $accepted = $this->catalogEntry('accepted_coach');
        $catalog = \Mockery::mock(CreatorCatalog::class);
        $catalog->shouldReceive('entries')->once()->andReturn([$failed, $accepted]);
        $this->app->instance(CreatorCatalog::class, $catalog);
        Storage::disk('local')->put('previous.json', json_encode(['entries' => [
            ['handle' => 'failed_coach', 'reasons' => ['provider_failure']],
            ['handle' => 'accepted_coach', 'provider_status' => 'ok'],
        ]]));

        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with('failed_coach')->andReturn($this->profile(username: 'failed_coach'));
        $provider->shouldNotReceive('getPosts');
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->with('mock')->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $path = Storage::disk('local')->path('previous.json');
        $this->artisan("personal:audit-creator-catalog --provider=mock --retry-report={$path}")
            ->expectsOutputToContain('1')
            ->assertSuccessful();
    }

    public function test_import_dispatches_measurement_in_chunks_of_ten(): void
    {
        Queue::fake();
        $entries = collect(range(1, 21))->map(fn (int $index): array => [
            'handle' => "coach{$index}", 'market' => 'FR', 'vertical' => 'sport-fitness',
            'topics' => ['coaching'],
            'rationale' => 'Recognized coach.', 'status' => 'approved',
        ])->all();
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->times(21)->andReturnUsing(function (string $handle): DiscoveredProfile {
            $profile = $this->profile(username: $handle);

            return new DiscoveredProfile(
                username: $handle,
                displayName: $profile->displayName,
                avatarUrl: $profile->avatarUrl,
                followers: $profile->followers,
                posts: $profile->posts,
                bio: $profile->bio,
                externalId: "instagram-{$handle}",
                metadata: $profile->metadata,
            );
        });

        app(CreatorCatalogImporter::class)->import($entries, $provider);

        $jobs = Queue::pushed(MeasureAccountEngagement::class);
        $this->assertCount(3, $jobs);
        $this->assertSame([10, 10, 1], $jobs->map(fn (MeasureAccountEngagement $job): int => count($job->usernames))->all());
    }

    public function test_profile_api_exposes_automatic_market_classification(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'market' => 'GB',
            'market_confidence' => 0.86,
            'primary_vertical' => 'tech-ai',
        ]);

        $this->actingAs($user)->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('profile.market', 'GB')
            ->assertJsonPath('profile.market_confidence', 0.86)
            ->assertJsonPath('profile.primary_vertical', 'tech-ai');
    }

    public function test_candidate_discovery_writes_reports_but_no_catalog_rows(): void
    {
        Storage::fake('local');
        $entry = [
            'handle' => 'coach', 'market' => 'FR', 'vertical' => 'sport-fitness',
            'topics' => ['coaching'],
            'rationale' => 'Recognized coach.', 'status' => 'approved',
        ];
        $catalog = \Mockery::mock(CreatorCatalog::class);
        $catalog->shouldReceive('approved')->once()->andReturn([$entry]);
        $catalog->shouldReceive('entries')->once()->andReturn([$entry]);
        $this->app->instance(CreatorCatalog::class, $catalog);

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

        $this->artisan('personal:discover-creator-candidates --provider=mock --max=1')->assertSuccessful();

        $this->assertDatabaseCount('creators', 0);
        $this->assertDatabaseCount('content_posts', 0);
        $this->assertCount(2, Storage::disk('local')->allFiles('catalog-reports'));
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

    private function catalogEntry(string $handle): array
    {
        return [
            'handle' => $handle,
            'instagram_url' => "https://www.instagram.com/{$handle}/",
            'market' => 'FR',
            'vertical' => 'sport-fitness',
            'topics' => ['coaching'],
            'rationale' => 'Recognized coach.',
            'source_urls' => ["https://www.instagram.com/{$handle}/", 'https://example.test/ranking'],
            'editorially_verified_at' => '2026-08-21',
            'status' => 'pending',
        ];
    }
}
