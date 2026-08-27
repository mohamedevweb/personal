<?php

namespace Tests\Feature\Discovery;

use App\Jobs\Discovery\AnalyzeCreatorHandle;
use App\Jobs\Discovery\DiscoverNicheContent;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\CreatorMarketDetector;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\MockInstagramDataProvider;
use App\Services\Instagram\NicheDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AnalyzeCreatorHandleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No key means the deterministic heuristic, not a language model call.
        config()->set('services.openai.api_key');
        config()->set('services.anthropic.api_key');
    }

    public function test_saving_a_handle_starts_reading_the_public_profile(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/integrations/instagram/handle', ['username' => '@founder.creator'])
            ->assertOk()
            ->assertJsonPath('instagram_username', 'founder.creator');

        Queue::assertPushed(AnalyzeCreatorHandle::class, fn (AnalyzeCreatorHandle $job): bool => $job->userId === $user->id);
    }

    public function test_saving_the_same_handle_again_does_not_scrape_again(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/integrations/instagram/handle', ['username' => 'founder.creator'])->assertOk();
        $this->actingAs($user)->putJson('/api/integrations/instagram/handle', ['username' => 'founder.creator'])->assertOk();

        Queue::assertPushed(AnalyzeCreatorHandle::class, 1);
    }

    public function test_opening_memory_reanalyzes_an_outdated_public_creator_dna(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
            'analysis_status' => 'completed',
            'creator_dna' => [
                'analysis_method' => 'llm',
                'analysis_version' => NicheDetectionService::ANALYSIS_VERSION - 1,
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('profile.analysis_status', 'queued');

        Queue::assertPushed(
            AnalyzeCreatorHandle::class,
            fn (AnalyzeCreatorHandle $job): bool => $job->userId === $user->id
                && $job->username === 'founder.creator',
        );
    }

    public function test_onboarding_is_told_the_reading_has_started(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/integrations/instagram/handle', ['username' => 'founder.creator'])
            ->assertOk()
            ->assertJsonPath('analysis.status', 'queued')
            ->assertJsonPath('analysis.posts_target', AnalyzeCreatorHandle::POSTS_READ);

        $this->actingAs($user)
            ->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('analysis.status', 'queued')
            ->assertJsonPath('analysis.stages', AnalyzeCreatorHandle::STAGES);
    }

    public function test_a_finished_reading_reports_what_it_found(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
            'analysis_status' => 'queued',
        ]);

        $this->runJob($user);

        $this->actingAs($user)
            ->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('analysis.status', 'completed')
            ->assertJsonPath('analysis.analyzed_posts_count', AnalyzeCreatorHandle::POSTS_READ)
            ->assertJsonPath('analysis.bio', 'Sample bio for founder.creator');

        $profile = $user->creatorProfile()->firstOrFail();
        $this->assertNotNull($profile->followers_count);
        $this->assertNotNull($profile->analysis_started_at);
    }

    public function test_a_profile_that_cannot_be_read_stops_with_a_reason(): void
    {
        $this->app->instance(InstagramDataProvider::class, new class extends MockInstagramDataProvider
        {
            public function __construct() {}

            public function getProfile(string $username, bool $fresh = false): ?DiscoveredProfile
            {
                return null;
            }
        });

        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
            'analysis_status' => 'queued',
        ]);

        $this->runJob($user);

        $this->actingAs($user)
            ->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('analysis.status', 'failed')
            ->assertJsonPath('analysis.error', 'profile_not_found');
    }

    public function test_a_private_account_stops_the_reading(): void
    {
        $this->app->instance(InstagramDataProvider::class, new class extends MockInstagramDataProvider
        {
            public function __construct() {}

            public function getProfile(string $username, bool $fresh = false): ?DiscoveredProfile
            {
                return new DiscoveredProfile(
                    username: $username,
                    displayName: 'Private Creator',
                    avatarUrl: null,
                    followers: 1200,
                    posts: collect(),
                    isPrivate: true,
                );
            }
        });

        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
            'analysis_status' => 'queued',
        ]);

        $this->runJob($user);

        $profile = $user->creatorProfile()->firstOrFail();
        $this->assertSame('failed', $profile->analysis_status);
        $this->assertSame('private_account', $profile->analysis_error);
    }

    public function test_a_reading_that_failed_is_retried_by_saving_the_handle_again(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
            'analysis_status' => 'failed',
            'analysis_error' => 'profile_not_found',
        ]);

        $this->actingAs($user)
            ->putJson('/api/integrations/instagram/handle', ['username' => 'founder.creator'])
            ->assertOk()
            ->assertJsonPath('analysis.status', 'queued')
            ->assertJsonPath('analysis.error', null);

        Queue::assertPushed(AnalyzeCreatorHandle::class, 1);
    }

    public function test_changing_handle_invalidates_the_previous_dna_and_discovery_cache(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'old.creator',
            'niche' => 'Old niche',
            'topics' => ['old topic'],
            'creator_dna' => ['primary_niche' => 'Old niche', 'analysis_method' => 'llm'],
            'discovery_queries' => ['old creator query'],
            'discovery_hashtags' => ['oldtopic'],
            'discovery_refreshed_at' => now(),
            'analysis_status' => 'completed',
        ]);

        $this->actingAs($user)
            ->putJson('/api/integrations/instagram/handle', ['username' => 'new.creator'])
            ->assertOk()
            ->assertJsonPath('analysis.status', 'queued');

        $profile = $user->creatorProfile()->firstOrFail();
        $this->assertNull($profile->creator_dna);
        $this->assertNull($profile->niche);
        $this->assertNull($profile->discovery_queries);
        $this->assertNull($profile->discovery_hashtags);
        Queue::assertPushed(
            AnalyzeCreatorHandle::class,
            fn (AnalyzeCreatorHandle $job): bool => $job->username === 'new.creator',
        );
    }

    public function test_an_old_queued_job_cannot_overwrite_a_new_handle(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'new.creator',
            'analysis_status' => 'queued',
        ]);

        (new AnalyzeCreatorHandle($user->id, 'old.creator'))->handle(
            app(InstagramDataProvider::class),
            app(NicheDetectionService::class),
            app(CreatorMarketDetector::class),
            app(CanonicalCreatorVerticals::class),
        );

        $profile = $user->creatorProfile()->firstOrFail();
        $this->assertSame('new.creator', $profile->instagram_username);
        $this->assertSame('queued', $profile->analysis_status);
        $this->assertNull($profile->creator_dna);
    }

    public function test_the_public_profile_fills_the_memory(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
        ]);

        $this->runJob($user);

        $profile = $user->creatorProfile()->firstOrFail();
        $this->assertSame('Sample bio for founder.creator', $profile->bio);
        $this->assertNotNull($profile->creator_dna);
        $this->assertNotNull($profile->dna_analyzed_at);
    }

    public function test_the_public_analysis_fills_every_supported_memory_field(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
        ]);
        $niche = Mockery::mock(NicheDetectionService::class);
        $niche->shouldReceive('detect')->once()->andReturn([
            'primary_niche' => 'Entrepreneurship',
            'sub_niches' => ['Business education'],
            'topics' => ['Starting a business', 'Funding'],
            'audience' => ['Aspiring founders'],
            'positioning' => 'Helps aspiring founders turn an idea into a sustainable business.',
            'language' => 'en',
            'content_pillars' => ['Business lessons'],
            'tone' => ['Motivational', 'Direct'],
            'current_projects' => ['Free founder course'],
            'goals' => ['Help one million people start a business'],
            'content_strengths' => ['Clear calls to action', 'Practical storytelling'],
            'voice_profile' => 'Uses direct hooks, short sentences and action-led conclusions.',
            'analysis_status' => 'complete',
            'analysis_method' => 'llm',
            'confidence' => 0.95,
            'evidence' => [
                'caption_count' => 30,
                'bio_available' => true,
                'link_preview_available' => false,
            ],
        ]);
        $this->app->instance(NicheDetectionService::class, $niche);

        $this->runJob($user);

        $profile = $user->creatorProfile()->firstOrFail();
        $this->assertSame('Entrepreneurship', $profile->niche);
        $this->assertSame('Helps aspiring founders turn an idea into a sustainable business.', $profile->positioning);
        $this->assertSame('Aspiring founders', $profile->audience_description);
        $this->assertSame(['Starting a business', 'Funding'], $profile->topics);
        $this->assertSame(['Motivational', 'Direct'], $profile->tone);
        $this->assertSame(['Free founder course'], $profile->current_projects);
        $this->assertSame(['Help one million people start a business'], $profile->goals);
        $this->assertSame(['Clear calls to action', 'Practical storytelling'], $profile->content_strengths);
        $this->assertSame('personal-branding', $profile->primary_vertical);
    }

    public function test_a_completed_handle_analysis_starts_the_personalized_feed_discovery(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'saas.founder',
        ]);

        $this->runJob($user);

        Queue::assertPushed(
            DiscoverNicheContent::class,
            fn (DiscoverNicheContent $job): bool => $job->userId === $user->id,
        );
    }

    public function test_the_public_profile_picture_is_kept(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
        ]);

        $this->runJob($user);

        $this->assertNotNull($user->creatorProfile()->firstOrFail()->avatar_url);
    }

    public function test_a_memory_written_by_hand_is_not_overwritten(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
            'niche' => 'Ceramics for restaurants',
            'topics' => ['glazing', 'kilns'],
            'creator_dna' => ['analysis_method' => 'manual'],
        ]);

        $this->runJob($user);

        $profile = $user->creatorProfile()->firstOrFail();
        $this->assertSame('Ceramics for restaurants', $profile->niche);
        $this->assertSame(['glazing', 'kilns'], $profile->topics);
        // What the scrape can add without contradicting the creator still lands.
        $this->assertSame('Sample bio for founder.creator', $profile->bio);
    }

    public function test_a_connected_account_keeps_the_authenticated_import(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'instagram_username' => 'founder.creator',
        ]);
        InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => '123',
            'username' => 'founder.creator',
            'access_token' => 'server-side-secret',
            'token_expires_at' => now()->addMonth(),
            'connected_at' => now(),
        ]);

        $this->runJob($user);

        $this->assertNull($user->creatorProfile()->firstOrFail()->bio);
    }

    public function test_a_user_without_a_handle_is_left_alone(): void
    {
        $user = User::factory()->create();

        $this->runJob($user);

        $this->assertNull($user->creatorProfile()->first());
    }

    private function runJob(User $user): void
    {
        (new AnalyzeCreatorHandle($user->id))->handle(
            app(InstagramDataProvider::class),
            app(NicheDetectionService::class),
            app(CreatorMarketDetector::class),
            app(CanonicalCreatorVerticals::class),
        );
    }
}
