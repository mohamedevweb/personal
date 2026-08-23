<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Sends the email-verification notification: the account exists but stays
        // gated until the creator confirms the address.
        event(new Registered($user));

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $user->createToken($this->tokenName($request))->plainTextToken,
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $user->createToken($this->tokenName($request))->plainTextToken,
        ]);
    }

    public function logout(Request $request): Response
    {
        // The API is also a stateful SPA backend, so a caller can hold a token, a
        // session, or both. Signing out has to end whichever one authenticated it.
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    private function tokenName(Request $request): string
    {
        return str($request->userAgent() ?? 'api')->limit(60, '')->toString();
    }

    /**
     * The public shape of a user the SPA relies on. `email_verified_at` drives the
     * verification gate, so every auth response has to carry it.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'avatar_url', 'instagram_username', 'email_verified_at']);
    }
}
