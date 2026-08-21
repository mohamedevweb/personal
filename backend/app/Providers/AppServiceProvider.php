<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use Anthropic\RequestOptions;
use App\Exceptions\ContentDiscoveryException;
use App\Services\ClaudeContentGenerationService;
use App\Services\ContentGenerationService;
use App\Services\Discovery\ApifyInstagramDiscoveryService;
use App\Services\Discovery\ApifyProfileScraperService;
use App\Services\Discovery\ContentDiscoveryService;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use App\Services\Discovery\MockInstagramDiscoveryService;
use App\Services\Discovery\MockProfileScraperService;
use App\Services\Discovery\ProfileDiscoveryService;
use App\Services\MockContentGenerationService;
use App\Services\OpenAiContentGenerationService;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use OpenAI;
use OpenAI\Contracts\ClientContract as OpenAiClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerModelClients();

        $this->app->bind(ContentGenerationService::class, function (): ContentGenerationService {
            // A driver without credentials falls back to the deterministic mock, so
            // a missing key degrades the drafts rather than breaking the product.
            return match (true) {
                config('services.content_generation.driver') === 'openai'
                    && (bool) config('services.openai.api_key') => $this->app->make(OpenAiContentGenerationService::class),

                config('services.content_generation.driver') === 'claude'
                    && (bool) config('services.anthropic.api_key') => $this->app->make(ClaudeContentGenerationService::class),

                default => $this->app->make(MockContentGenerationService::class),
            };
        });

        $this->app->bind(ContentDiscoveryService::class, function (): ContentDiscoveryService {
            return match (true) {
                config('services.discovery.driver') === 'apify'
                    && (bool) config('services.discovery.apify.token') => $this->app->make(ApifyInstagramDiscoveryService::class),

                config('services.discovery.driver') === 'mock'
                    && ! $this->app->environment('production') => $this->app->make(MockInstagramDiscoveryService::class),

                default => throw new ContentDiscoveryException('Content discovery is not configured for the selected provider.'),
            };
        });

        $this->app->bind(ProfileDiscoveryService::class, function (): ProfileDiscoveryService {
            return match (true) {
                config('services.discovery.driver') === 'apify'
                    && (bool) config('services.discovery.apify.token') => $this->app->make(ApifyProfileScraperService::class),

                config('services.discovery.driver') === 'mock'
                    && ! $this->app->environment('production') => $this->app->make(MockProfileScraperService::class),

                default => throw new ContentDiscoveryException('Profile discovery is not configured for the selected provider.'),
            };
        });

        $this->app->bind(InstagramDataProvider::class, function (): InstagramDataProvider {
            return $this->app->make(InstagramDataProviderManager::class)->provider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Credential endpoints are rate limited per identity and per address so a
        // single address cannot spray attempts across many accounts.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by((string) $request->input('email')),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('generation', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));
    }

    private function registerModelClients(): void
    {
        $this->app->singleton(OpenAiClient::class, fn (): OpenAiClient => OpenAI::factory()
            ->withApiKey((string) config('services.openai.api_key'))
            ->withHttpClient(new GuzzleClient([
                'timeout' => (int) config('services.openai.request_timeout'),
            ]))
            ->make());

        $this->app->singleton(AnthropicClient::class, fn (): AnthropicClient => new AnthropicClient(
            apiKey: (string) config('services.anthropic.api_key'),
            requestOptions: RequestOptions::with(
                timeout: (float) config('services.anthropic.timeout'),
            ),
        ));
    }
}
