<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PersonalVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_is_unverified_and_blocked_from_product_endpoints(): void
    {
        Notification::fake();

        $token = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@personal.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertCreated()->assertJsonPath('user.email_verified_at', null)->json('token');

        $this->withToken($token)->getJson('/api/feed')->assertForbidden();

        Notification::assertSentTo(User::query()->firstWhere('email', 'ada@personal.test'), PersonalVerifyEmail::class);
    }

    public function test_a_verified_account_reaches_product_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/feed')->assertOk();
    }

    public function test_the_signed_verification_link_marks_the_email_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirectContains('status=verified');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_tampered_verification_link_does_not_verify(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@personal.test'),
        ]);

        $this->get($url)->assertRedirectContains('status=invalid');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_creator_can_resend_the_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson('/api/email/verification-notification')->assertAccepted();

        Notification::assertSentTo($user, PersonalVerifyEmail::class);
    }

    public function test_a_creator_can_update_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);

        $this->actingAs($user)->patchJson('/api/me/account', ['name' => 'Ada Lovelace'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Ada Lovelace');

        $this->assertSame('Ada Lovelace', $user->fresh()->name);
    }

    public function test_changing_the_email_resets_verification_and_resends_the_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@personal.test']);

        $this->actingAs($user)->patchJson('/api/me/account', ['email' => 'new@personal.test'])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@personal.test')
            ->assertJsonPath('user.email_verified_at', null);

        $fresh = $user->fresh();
        $this->assertSame('new@personal.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);

        Notification::assertSentTo($fresh, PersonalVerifyEmail::class);
    }

    public function test_the_email_must_be_unique_when_updating(): void
    {
        User::factory()->create(['email' => 'taken@personal.test']);
        $user = User::factory()->create(['email' => 'mine@personal.test']);

        $this->actingAs($user)->patchJson('/api/me/account', ['email' => 'taken@personal.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_updating_the_password_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-secret')]);

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'wrong-secret',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'current-secret',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-secret', $user->fresh()->password));
    }

    public function test_updating_the_password_revokes_existing_api_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-secret')]);
        $currentToken = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($currentToken)->putJson('/api/me/password', [
            'current_password' => 'current-secret',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertOk();

        $this->withToken($currentToken)->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken($otherToken)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
