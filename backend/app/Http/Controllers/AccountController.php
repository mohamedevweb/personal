<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /**
     * Update the signed-in creator's name and/or email. Changing the email drops
     * the verified stamp and re-sends the verification notification to the new
     * address, so a swapped email cannot silently stay trusted.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $user->email;

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'avatar_url', 'email_verified_at']),
        ]);
    }

    /**
     * Rotate the password after confirming the current one. Token auth means the
     * `current_password` rule cannot lean on the web guard, so the check is done
     * against the resolved user directly (matching AuthController::login()).
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The provided password does not match your current password.'),
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => __('Your password has been updated.')]);
    }
}
