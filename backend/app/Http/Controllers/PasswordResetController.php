<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:254'],
        ]);

        // The response never reveals whether the address belongs to an account.
        // The broker still applies its own per-address cooldown before sending.
        try {
            Password::sendResetLink(['email' => $data['email']]);
        } catch (Throwable $exception) {
            // A transport error must not turn into an account-enumeration signal.
            Log::error('Personal could not send a password reset email.', [
                'exception' => $exception,
            ]);
        }

        return response()->json([
            'message' => __('passwords.sent_generic'),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // A recovered account should not leave an unknown device signed in.
                    $user->tokens()->delete();
                });

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('passwords.token'),
            ]);
        }

        return response()->json([
            'message' => __('passwords.reset'),
        ]);
    }
}
