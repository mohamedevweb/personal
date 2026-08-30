<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\View\UserView;
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
    public function register(Request $request, UserView $view): JsonResponse
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

        return $this->tokenResponse($user, $view, $request, Response::HTTP_CREATED);
    }

    private function tokenResponse(User $user, UserView $view, Request $request, int $status = Response::HTTP_OK): JsonResponse
    {
        $token = $user->createToken($this->tokenName($request))->plainTextToken;

        return response()->json([
            'user' => $view->make($user),
            // Keep the token in the API response for non-browser API clients.
            // The Nuxt app authenticates with the HttpOnly cookie below.
            'token' => $token,
        ], $status)->cookie($this->tokenCookie($token));
    }

    public function login(Request $request, UserView $view): JsonResponse
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

        return $this->tokenResponse($user, $view, $request);
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

        return response()->noContent()->withCookie(cookie()->forget('personal_token'));
    }

    public function me(Request $request, UserView $view): JsonResponse
    {
        return response()->json(['user' => $view->make($request->user())]);
    }

    private function tokenName(Request $request): string
    {
        return str($request->userAgent() ?? 'api')->limit(60, '')->toString();
    }

    private function tokenCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            'personal_token',
            $token,
            60 * 24 * 30,
            '/',
            config('session.domain'),
            config('session.secure') ?? app()->isProduction(),
            true,
            false,
            config('session.same_site', 'lax'),
        );
    }
}
