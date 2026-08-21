<?php

namespace Tests\Feature;

use App\Jobs\MeasureAccountEngagement;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        $this->creator('fitness_creator', 'sport-fitness', 'approved', 'allowed', 900000);
        $this->creator('pending_creator', 'tech-ai', 'discovered', 'pending', 800000);
        $this->creator('blocked_creator', 'tech-ai', 'approved', 'blocked', 700000);

        $response = $this->actingAs($this->user)->getJson('/api/creator-inspirations');

        $response->assertOk()
            ->assertJsonPath('minimum', 3)
            ->assertJsonPath('maximum', 5)
            ->assertJsonPath('suggestions.0.username', $tech->username)
            ->assertJsonCount(2, 'suggestions');
    }

    public function test_explicit_search_uses_the_provider_without_writing_to_the_database(): void
    {
        $provider = \Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with('remote.creator')->andReturn(
            new DiscoveredProfile(
                username: 'remote.creator',
                displayName: 'Remote Creator',
                avatarUrl: 'https://instagram.test/avatar.jpg',
                followers: 42000,
                posts: collect(),
                externalId: 'instagram-remote',
            ),
        );
        $manager = \Mockery::mock(InstagramDataProviderManager::class);
        $manager->shouldReceive('provider')->once()->andReturn($provider);
        $this->app->instance(InstagramDataProviderManager::class, $manager);

        $this->actingAs($this->user)
            ->getJson('/api/creator-inspirations/search?q=%40remote.creator')
            ->assertOk()
            ->assertJsonPath('items.0.username', 'remote.creator')
            ->assertJsonPath('items.0.avatar_url', null);

        $this->assertDatabaseCount('creators', 0);
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
            'niche' => 'tech-ai',
        ]);
        Queue::assertPushed(MeasureAccountEngagement::class);

        $otherUser = User::factory()->create();
        $this->assertSame(0, $otherUser->inspirationCreators()->count());

        $this->actingAs($this->user)->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('inspiration_count', 3)
            ->assertJsonPath('onboarding_complete', true);
    }

    public function test_selection_requires_three_distinct_valid_handles(): void
    {
        $this->actingAs($this->user)->putJson('/api/creator-inspirations', [
            'handles' => ['same_creator', '@same_creator', 'invalid handle'],
        ])->assertUnprocessable()->assertJsonValidationErrors('handles');

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
