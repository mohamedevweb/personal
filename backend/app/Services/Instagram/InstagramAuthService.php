<?php

namespace App\Services\Instagram;

use App\Exceptions\InstagramIntegrationException;
use App\Jobs\Instagram\SyncInstagramAccount;
use App\Models\InstagramAccount;
use App\Models\InstagramOauthState;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InstagramAuthService
{
    public function __construct(private readonly InstagramApiService $api) {}

    public function authorizationUrl(User $user, string $locale = 'en'): string
    {
        $this->ensureConfigured();

        $state = Str::random(64).'.'.$this->supportedLocale($locale);
        InstagramOauthState::query()->create([
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);

        return config('services.instagram.authorization_url').'?'.http_build_query([
            'client_id' => config('services.instagram.app_id'),
            'redirect_uri' => config('services.instagram.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(',', config('services.instagram.scopes')),
            'state' => $state,
            'enable_fb_login' => 0,
            'force_authentication' => 1,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function handleCallback(string $code, string $state): InstagramAccount
    {
        $oauthState = $this->consumeState($state);
        $shortLived = $this->exchangeAuthorizationCode(rtrim($code, '#_'));
        $longLived = $this->exchangeForLongLivedToken($shortLived['access_token']);
        $profile = $this->api->fetchProfile($longLived['access_token']);

        if (strtoupper((string) ($profile['account_type'] ?? '')) === 'PERSONAL') {
            throw new InstagramIntegrationException(
                'Personal currently supports Instagram Creator and Business accounts only.'
            );
        }

        if (InstagramAccount::query()
            ->where('instagram_user_id', $profile['instagram_user_id'])
            ->where('user_id', '!=', $oauthState->user_id)
            ->exists()) {
            throw new InstagramIntegrationException('This Instagram account is already connected to another Personal account.');
        }

        $account = InstagramAccount::query()->updateOrCreate(
            ['user_id' => $oauthState->user_id],
            [
                ...$profile,
                'access_token' => $longLived['access_token'],
                'token_expires_at' => isset($longLived['expires_in'])
                    ? now()->addSeconds((int) $longLived['expires_in'])
                    : null,
                'sync_status' => 'connecting',
                'sync_error' => null,
                'connected_at' => now(),
            ],
        );

        SyncInstagramAccount::dispatch($account->id, $this->localeFromState($state))->afterCommit();

        return $account;
    }

    private function localeFromState(string $state): string
    {
        return $this->supportedLocale(Str::afterLast($state, '.'));
    }

    private function supportedLocale(string $locale): string
    {
        return Str::lower(Str::before($locale, '-')) === 'fr' ? 'fr' : 'en';
    }

    public function accessToken(InstagramAccount $account): string
    {
        if ($account->token_expires_at?->isBefore(now()->addDays(7))) {
            $refreshed = $this->refreshLongLivedToken($account->access_token);
            $account->forceFill([
                'access_token' => $refreshed['access_token'],
                'token_expires_at' => isset($refreshed['expires_in'])
                    ? now()->addSeconds((int) $refreshed['expires_in'])
                    : $account->token_expires_at,
            ])->save();
        }

        return $account->access_token;
    }

    public function disconnect(InstagramAccount $account): void
    {
        $account->delete();
    }

    /** @return array{access_token: string, user_id?: int|string} */
    private function exchangeAuthorizationCode(string $code): array
    {
        $response = Http::asForm()->acceptJson()->timeout(15)->post(
            config('services.instagram.token_url'),
            [
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.instagram.redirect_uri'),
                'code' => $code,
            ],
        );

        return $this->validatedTokenResponse($response);
    }

    /** @return array{access_token: string, token_type?: string, expires_in?: int} */
    private function exchangeForLongLivedToken(string $accessToken): array
    {
        $response = Http::acceptJson()->timeout(15)->get(
            rtrim(config('services.instagram.graph_url'), '/').'/access_token',
            [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => config('services.instagram.app_secret'),
                'access_token' => $accessToken,
            ],
        );

        return $this->validatedTokenResponse($response);
    }

    /** @return array{access_token: string, token_type?: string, expires_in?: int} */
    private function refreshLongLivedToken(string $accessToken): array
    {
        $response = Http::acceptJson()->timeout(15)->get(
            rtrim(config('services.instagram.graph_url'), '/').'/refresh_access_token',
            [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $accessToken,
            ],
        );

        return $this->validatedTokenResponse($response);
    }

    private function consumeState(string $state): InstagramOauthState
    {
        return DB::transaction(function () use ($state): InstagramOauthState {
            $oauthState = InstagramOauthState::query()
                ->where('state_hash', hash('sha256', $state))
                ->lockForUpdate()
                ->first();

            if (! $oauthState || $oauthState->consumed_at || $oauthState->expires_at->isPast()) {
                throw new InstagramIntegrationException('The Instagram authorization session expired. Please try again.');
            }

            $oauthState->update(['consumed_at' => now()]);

            return $oauthState;
        });
    }

    /** @return array<string, mixed> */
    private function validatedTokenResponse(Response $response): array
    {
        if ($response->successful() && is_string($response->json('access_token'))) {
            return $response->json();
        }

        $error = $response->json('error', []);
        throw new InstagramIntegrationException(
            $error['message'] ?? $response->json('error_message') ?? 'Instagram authorization failed.',
            isset($error['code']) ? (string) $error['code'] : null,
        );
    }

    private function ensureConfigured(): void
    {
        if (! config('services.instagram.app_id') || ! config('services.instagram.app_secret')) {
            throw new InstagramIntegrationException('Instagram is not configured yet.');
        }
    }
}
