<?php

namespace Tests\Feature\Creator;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreatorInspirationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $this->user->id,
            'market' => 'FR',
            'primary_vertical' => 'tech-ai',
        ]);
    }

    public function test_suggestions_prioritize_approved_safe_creators_in_the_users_vertical(): void
    {
        $tech = $this->creator('tech_creator', 'tech-ai', 'approved', 'allowed', 100000);
        $self = $this->creator('self_creator', 'tech-ai', 'approved', 'allowed', 2000000);
        $self->update(['user_id' => $this->user->id]);
        $this->creator('fitness_creator', 'sport-fitness', 'approved', 'allowed', 900000);
        $this->creator('pending_creator', 'tech-ai', 'discovered', 'pending', 800000);
        $this->creator('blocked_creator', 'tech-ai', 'approved', 'blocked', 700000);

        $response = $this->actingAs($this->user)->getJson('/api/creator-inspirations');

        $response->assertOk()
            ->assertJsonPath('minimum', 3)
            ->assertJsonPath('maximum', 6)
            ->assertJsonPath('suggestion_limit', 6)
            ->assertJsonPath('suggestions.0.username', $tech->username)
            ->assertJsonCount(2, 'suggestions');
    }

    public function test_suggestions_include_a_reserve_to_refill_six_visible_choices(): void
    {
        foreach (range(1, 12) as $position) {
            $this->creator("creator_{$position}", followers: 100000 - $position);
        }

        $this->actingAs($this->user)
            ->getJson('/api/creator-inspirations')
            ->assertOk()
            ->assertJsonPath('suggestion_limit', 6)
            ->assertJsonCount(12, 'suggestions');
    }

    public function test_suggestions_use_creator_dna_topics_inside_the_primary_vertical(): void
    {
        $this->user->creatorProfile()->update([
            'creator_dna' => [
                'primary_niche' => 'AI SaaS',
                'sub_niches' => ['B2B SaaS'],
                'topics' => ['product building', 'automation'],
                'content_pillars' => ['build in public'],
            ],
        ]);
        $broad = $this->creator('smartphone.news', followers: 2000000);
        $broad->update(['niche_topics' => ['smartphones', 'hardware reviews']]);
        $closest = $this->creator('saas.builder', followers: 50000);
        $closest->update(['niche_topics' => ['AI SaaS', 'product building', 'build in public']]);

        $this->actingAs($this->user)
            ->getJson('/api/creator-inspirations')
            ->assertOk()
            ->assertJsonPath('suggestions.0.username', $closest->username);
    }

    public function test_explicit_search_uses_the_provider_without_writing_to_the_database(): void
    {
        config(['app.url' => 'https://api.personal.test']);
        Storage::fake('local');
        Http::preventStrayRequests();
        $avatar = 'https://scontent-sea5-1.cdninstagram.com/remote-avatar.jpg';
        Http::fake([$avatar => Http::response('avatar-content', 200, ['Content-Type' => 'image/jpeg'])]);
        $this->creator('remote.creator.fan', followers: 900000);
        $this->creator('the_remote.creator', followers: 800000);
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with('remote.creator')->andReturn(
            new DiscoveredProfile(
                username: 'remote.creator',
                displayName: 'Remote Creator',
                avatarUrl: $avatar,
                followers: 42000,
                posts: collect(),
                externalId: 'instagram-remote',
            ),
        );
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $response = $this->actingAs($this->user)
            ->getJson('/api/creator-inspirations/search?q=remote.creator')
            ->assertOk()
            ->assertJsonPath('items.0.username', 'remote.creator')
            ->assertJsonCount(1, 'items');

        $avatarUrl = $response->json('items.0.avatar_url');
        $this->assertIsString($avatarUrl);
        $this->assertStringStartsWith('https://api.personal.test/api/media/creator-preview/remote.creator', $avatarUrl);

        $relativeAvatarUrl = parse_url($avatarUrl, PHP_URL_PATH).'?'.parse_url($avatarUrl, PHP_URL_QUERY);
        $this->get($relativeAvatarUrl)->assertOk()->assertContent('avatar-content');

        $this->assertDatabaseCount('creators', 2);
    }

    public function test_selection_is_private_idempotent_and_queues_new_creators_for_measurement(): void
    {
        Queue::fake();
        $this->creator('first_creator');
        $this->creator('second_creator');

        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with('new.creator')->andReturn(
            new DiscoveredProfile(
                username: 'new.creator',
                displayName: 'New Creator',
                avatarUrl: null,
                followers: 51000,
                posts: collect(),
                bio: 'Créatrice tech française',
                externalId: 'instagram-new',
            ),
        );
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        InstagramAccount::query()->create([
            'user_id' => $this->user->id,
            'instagram_user_id' => 'instagram-user',
            'username' => 'personal_user',
            'access_token' => 'secret',
            'sync_status' => 'completed',
            'connected_at' => now(),
        ]);

        $payload = ['handles' => [
            '@first_creator',
            'https://www.instagram.com/second_creator/',
            'new.creator',
        ]];

        $this->actingAs($this->user)->putJson('/api/creator-inspirations', $payload)
            ->assertOk()
            ->assertJsonPath('onboarding_complete', true)
            ->assertJsonCount(3, 'selected');
        $this->actingAs($this->user)->putJson('/api/creator-inspirations', $payload)->assertOk();

        $this->assertDatabaseCount('user_creator_inspirations', 3);
        $this->assertDatabaseHas('creators', [
            'username' => 'new.creator',
            'curation_status' => 'discovered',
            'safety_status' => 'pending',
            'niche' => 'unclassified',
        ]);
        Queue::assertPushed(MeasureAccountEngagement::class);

        $otherUser = User::factory()->create();
        $this->assertSame(0, $otherUser->inspirationCreators()->count());

        $this->actingAs($this->user)->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('inspiration_count', 3)
            ->assertJsonPath('onboarding_complete', true);
    }

    public function test_new_inspiration_without_a_canonical_vertical_stays_unclassified(): void
    {
        Queue::fake();
        $this->creator('first.creator');
        $this->creator('second.creator');

        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with('neutral.creator')->andReturn(
            new DiscoveredProfile(
                username: 'neutral.creator',
                displayName: 'Neutral Creator',
                avatarUrl: null,
                followers: 51000,
                posts: collect(),
                bio: 'Je partage mes découvertes du quotidien.',
                externalId: 'instagram-neutral',
            ),
        );
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->actingAs($this->user)->putJson('/api/creator-inspirations', [
            'handles' => ['first.creator', 'second.creator', 'neutral.creator'],
        ]);

        $this->assertDatabaseHas('creators', [
            'username' => 'neutral.creator',
            'niche' => 'unclassified',
            'primary_vertical' => null,
        ]);
    }

    public function test_selection_requires_three_distinct_valid_handles(): void
    {
        $this->actingAs($this->user)->putJson('/api/creator-inspirations', [
            'handles' => ['same_creator', '@same_creator', 'invalid handle'],
        ])->assertUnprocessable()->assertJsonValidationErrors('handles');

        $this->assertDatabaseCount('user_creator_inspirations', 0);
    }

    public function test_manual_handle_completes_onboarding_after_selecting_inspirations(): void
    {
        Queue::fake();
        $this->user->creatorProfile()->update(['instagram_username' => 'personal_user']);
        $this->creator('first_creator');
        $this->creator('second_creator');
        $this->creator('third_creator');

        $this->actingAs($this->user)->putJson('/api/creator-inspirations', [
            'handles' => ['first_creator', 'second_creator', 'third_creator'],
        ])->assertOk()->assertJsonPath('onboarding_complete', true);

        $this->actingAs($this->user)->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonPath('instagram_username', 'personal_user')
            ->assertJsonPath('onboarding_complete', true);
    }

    public function test_selection_rejects_more_than_six_handles(): void
    {
        $handles = ['one', 'two', 'three', 'four', 'five', 'six', 'seven'];

        $this->actingAs($this->user)->putJson('/api/creator-inspirations', ['handles' => $handles])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('handles');

        $this->assertDatabaseCount('user_creator_inspirations', 0);
    }

    private function creator(
        string $username,
        string $niche = 'tech-ai',
        string $curation = 'approved',
        string $safety = 'allowed',
        int $followers = 100000,
    ): Creator {
        return Creator::query()->create([
            'username' => $username,
            'display_name' => str($username)->headline(),
            'niche' => $niche,
            'market' => 'FR',
            'curation_status' => $curation,
            'safety_status' => $safety,
            'followers' => $followers,
            'average_views' => 10000,
            'average_likes' => 1000,
            'baseline_engagement' => 700,
        ]);
    }
}
