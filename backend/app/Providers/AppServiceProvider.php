<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use Anthropic\RequestOptions;
use App\Services\Content\ClaudeContentGenerationService;
use App\Services\Content\ContentGenerationService;
use App\Services\Content\MockContentGenerationService;
use App\Services\Content\OpenAiContentGenerationService;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
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

        $this->app->bind(InstagramDataProvider::class, function (): InstagramDataProvider {
            return $this->app->make(InstagramDataProviderManager::class)->provider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => $request->routeIs('media.*')
            ? Limit::none()
            : Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Signed media can fan out into an image and avatar request per feed card.
        // Keep it bounded without spending the product API's interactive budget.
        RateLimiter::for('media', fn (Request $request) => Limit::perMinute(600)
            ->by($request->ip()));

        // Credential endpoints are rate limited per identity and per address so a
        // single address cannot spray attempts across many accounts.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by((string) $request->input('email')),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('generation', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        // Creator search can spend provider credits, so it only runs after an
        // explicit user action and has a separate per-account budget.
        RateLimiter::for('discovery', fn (Request $request) => Limit::perMinute(10)
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
