<?php

namespace Tests\Feature\Auth;

use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_creator_can_register_and_receive_an_api_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@personal.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ada@personal.test');
        $this->assertIsString($response->json('token'));
        $this->assertDatabaseHas('users', ['email' => 'ada@personal.test']);
    }

    public function test_login_returns_a_token_and_rejects_a_wrong_password(): void
    {
        User::query()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@personal.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@personal.test',
            'password' => 'correct-horse-battery',
        ])->assertOk()->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->postJson('/api/auth/login', [
            'email' => 'ada@personal.test',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_password_hash_is_never_serialized(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@personal.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    public function test_authenticated_user_payload_includes_the_instagram_username(): void
    {
        $user = User::factory()->create();
        InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => '123',
            'username' => 'ada.creates',
            'access_token' => 'encrypted-at-rest',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.instagram_username', 'ada.creates');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::query()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@personal.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $keep = $user->createToken('other-device')->plainTextToken;
        $revoke = $user->createToken('this-device')->plainTextToken;

        $this->withToken($revoke)->postJson('/api/auth/logout')->assertNoContent();

        // Guards memoize the resolved user for the lifetime of the application
        // instance, which the test client reuses across requests.
        $this->app['auth']->forgetGuards();
        $this->withToken($revoke)->getJson('/api/auth/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($keep)->getJson('/api/auth/me')->assertOk();
    }

    public function test_the_http_only_token_cookie_authenticates_browser_requests(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('browser')->plainTextToken;

        $this->withCookie('personal_token', $token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_product_endpoints_require_authentication(): void
    {
        $this->getJson('/api/feed')->assertUnauthorized();
        $this->getJson('/api/moments')->assertUnauthorized();
        $this->getJson('/api/integrations/instagram/status')->assertUnauthorized();
    }

    public function test_the_development_session_endpoint_is_not_registered_outside_local(): void
    {
        $this->assertFalse($this->app->isLocal());
        $this->getJson('/api/development/session')->assertNotFound();
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/auth/login', [
                'email' => 'ada@personal.test',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'ada@personal.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
