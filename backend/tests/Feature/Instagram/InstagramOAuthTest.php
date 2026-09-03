<?php

namespace Tests\Feature\Instagram;

use App\Jobs\Instagram\SyncInstagramAccount;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\InstagramOauthState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InstagramOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.instagram', [
            'app_id' => 'instagram-app-id',
            'app_secret' => 'instagram-app-secret',
            'redirect_uri' => 'http://localhost/instagram/callback',
            'frontend_url' => 'http://localhost:3000',
            'api_version' => 'v25.0',
            'authorization_url' => 'https://www.instagram.com/oauth/authorize',
            'token_url' => 'https://api.instagram.com/oauth/access_token',
            'graph_url' => 'https://graph.instagram.com',
            'scopes' => ['instagram_business_basic', 'instagram_business_manage_insights'],
            'media_limit' => 25,
        ]);
    }

    public function test_authenticated_user_can_create_an_official_instagram_authorization_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('Accept-Language', 'fr')
            ->getJson('/api/integrations/instagram/authorize');

        $response->assertOk();
        parse_str(parse_url($response->json('authorization_url'), PHP_URL_QUERY), $query);

        $this->assertSame('instagram-app-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('instagram_business_basic,instagram_business_manage_insights', $query['scope']);
        $this->assertStringEndsWith('.fr', $query['state']);
        $this->assertDatabaseHas('instagram_oauth_states', [
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $query['state']),
        ]);
        $this->assertDatabaseMissing('instagram_oauth_states', ['state_hash' => $query['state']]);
    }

    public function test_callback_exchanges_code_encrypts_token_and_queues_initial_sync(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $state = 'one-time-state.fr';
        InstagramOauthState::query()->create([
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);

        Http::fake([
            'https://api.instagram.com/oauth/access_token' => Http::response([
                'access_token' => 'short-lived-secret',
                'user_id' => 123456,
            ]),
            'https://graph.instagram.com/access_token*' => Http::response([
                'access_token' => 'long-lived-secret',
                'token_type' => 'bearer',
                'expires_in' => 5184000,
            ]),
            'https://graph.instagram.com/v25.0/me*' => Http::response([
                'id' => '123456',
                'user_id' => '123456',
                'username' => 'real_creator',
                'name' => 'Real Creator',
                'account_type' => 'MEDIA_CREATOR',
                'followers_count' => 4200,
                'media_count' => 18,
            ]),
        ]);

        $response = $this->get('/instagram/callback?'.http_build_query([
            'code' => 'authorization-code',
            'state' => $state,
        ]));

        $response->assertRedirect('http://localhost:3000/onboarding?instagram=connected');
        $account = InstagramAccount::query()->firstOrFail();
        $this->assertSame('real_creator', $account->username);
        $this->assertSame('long-lived-secret', $account->access_token);
        $this->assertNotSame('long-lived-secret', DB::table('instagram_accounts')->value('access_token'));
        $this->assertNotNull(InstagramOauthState::query()->first()->consumed_at);
        Queue::assertPushed(SyncInstagramAccount::class, fn ($job) => $job->instagramAccountId === $account->id
            && $job->locale === 'fr');
    }

    public function test_callback_rejects_replayed_state_without_contacting_meta(): void
    {
        Http::fake();
        $user = User::factory()->create();
        InstagramOauthState::query()->create([
            'user_id' => $user->id,
            'state_hash' => hash('sha256', 'used-state'),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
        ]);

        $response = $this->get('/instagram/callback?code=anything&state=used-state');

        $response->assertRedirect();
        $this->assertStringContainsString('instagram=error', $response->headers->get('Location'));
        Http::assertNothingSent();
    }

    public function test_status_never_exposes_the_access_token(): void
    {
        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'primary_vertical' => 'tech-ai',
        ]);
        InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => '123',
            'username' => 'safe_creator',
            'access_token' => 'must-never-leak',
            'sync_status' => 'completed',
            'connected_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/integrations/instagram/status');

        $response->assertOk()
            ->assertJsonPath('inspiration_count', 0)
            ->assertJsonPath('onboarding_complete', true)
            ->assertJsonPath('media_enrichment.status', 'idle')
            ->assertJsonMissing(['access_token' => 'must-never-leak']);
        $this->assertStringNotContainsString('must-never-leak', $response->getContent());
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_completed_sync_does_not_complete_onboarding_without_a_primary_vertical(): void
    {
        $user = User::factory()->create();
        InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => '123',
            'username' => 'unclassified_creator',
            'access_token' => 'secret',
            'sync_status' => 'completed',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('onboarding_complete', false);

        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_progress_returns_only_the_fields_needed_by_polling(): void
    {
        $user = User::factory()->create();
        $user->creatorProfile()->create([
            'instagram_username' => 'progress.creator',
            'analysis_status' => 'reading_voice',
            'media_enrichment_status' => 'idle',
        ]);

        $this->actingAs($user)
            ->getJson('/api/integrations/instagram/progress')
            ->assertOk()
            ->assertJsonPath('analysis.status', 'reading_voice')
            ->assertJsonPath('media_enrichment.status', 'idle')
            ->assertJsonMissingPath('inspiration_count')
            ->assertJsonMissingPath('profile');
    }

    public function test_user_can_provide_a_handle_without_connecting_oauth(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/integrations/instagram/handle', [
            'username' => '@manual.creator',
        ])->assertOk()->assertJsonPath('instagram_username', 'manual.creator');

        $this->assertDatabaseHas('creator_profiles', [
            'user_id' => $user->id,
            'instagram_username' => 'manual.creator',
        ]);
        $this->assertDatabaseCount('instagram_accounts', 0);

        $this->actingAs($user)->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonPath('instagram_username', 'manual.creator')
            ->assertJsonPath('onboarding_complete', false);

        $user->creatorProfile()->update([
            'analysis_status' => 'completed',
            'primary_vertical' => 'personal-branding',
        ]);

        $this->actingAs($user)->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('inspiration_count', 0)
            ->assertJsonPath('onboarding_complete', true);
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_background_dna_refresh_does_not_reopen_completed_handle_onboarding(): void
    {
        $user = User::factory()->create();
        $user->creatorProfile()->create([
            'instagram_username' => 'manual.creator',
            'analysis_status' => 'queued',
            'dna_analyzed_at' => now()->subDay(),
            'primary_vertical' => 'personal-branding',
            'creator_dna' => [
                'analysis_method' => 'llm',
                'analysis_version' => 4,
            ],
        ]);

        $this->actingAs($user)->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonPath('analysis.status', 'queued')
            ->assertJsonPath('onboarding_complete', true);

        $this->assertNotNull($user->fresh()->onboarding_completed_at);

        $user->creatorProfile()->update([
            'analysis_status' => 'transcribing_reels',
            'dna_analyzed_at' => null,
        ]);

        $this->actingAs($user)->getJson('/api/integrations/instagram/status')
            ->assertOk()
            ->assertJsonPath('analysis.status', 'transcribing_reels')
            ->assertJsonPath('onboarding_complete', true);
    }

    public function test_manual_handle_must_be_a_valid_instagram_username(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/integrations/instagram/handle', [
            'username' => 'invalid handle',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->assertDatabaseCount('creator_profiles', 0);
    }

    public function test_user_can_disconnect_and_delete_stored_credentials(): void
    {
        $user = User::factory()->create();
        $account = InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => '123',
            'username' => 'disconnect_me',
            'access_token' => 'encrypted-secret',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)->deleteJson('/api/integrations/instagram')->assertNoContent();

        $this->assertModelMissing($account);
    }
}
