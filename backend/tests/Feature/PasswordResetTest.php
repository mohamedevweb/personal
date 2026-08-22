<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PersonalResetPassword;
use App\Notifications\PersonalVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_creator_can_request_a_branded_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ada@personal.test']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk()->assertJsonStructure(['message']);

        Notification::assertSentTo(
            $user,
            PersonalResetPassword::class,
            function (PersonalResetPassword $notification) use ($user): bool {
                $message = $notification->toMail($user);

                return $message->subject === 'Reset your Personal password'
                    && str_starts_with(
                        $message->viewData['actionUrl'],
                        'http://localhost:3000/reset-password?',
                    )
                    && str_contains($message->viewData['actionUrl'], 'email=ada%40personal.test');
            },
        );
    }

    public function test_a_reset_request_does_not_reveal_an_unknown_address(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown@personal.test',
        ])->assertOk()->assertJsonStructure(['message']);

        Notification::assertNothingSent();
    }

    public function test_a_creator_can_reset_their_password_and_revoke_existing_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@personal.test',
            'password' => Hash::make('old-password'),
        ]);
        $user->createToken('phone');
        $user->createToken('laptop');
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk()->assertJsonStructure(['message']);

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_an_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'ada@personal.test']);

        $this->withHeader('Accept-Language', 'fr')->postJson('/api/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Ce lien de réinitialisation est invalide ou a expiré.');
    }

    public function test_transactional_email_uses_the_personal_visual_language(): void
    {
        $user = User::factory()->unverified()->create();
        $message = (new PersonalVerifyEmail)->toMail($user);
        $html = view($message->view['html'], $message->viewData)->render();
        $text = view($message->view['text'], $message->viewData)->render();

        $this->assertStringContainsString('Personal', $html);
        $this->assertStringContainsString('#e04f36', $html);
        $this->assertStringContainsString('#f7f5f0', $html);
        $this->assertStringContainsString('Confirm my email', $html);
        $this->assertStringNotContainsString('&amp;', $text);
    }
}
