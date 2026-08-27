<?php

namespace App\Services\Discovery;

use App\Exceptions\ContentDiscoveryException;
use Illuminate\Contracts\Foundation\Application;

class InstagramDataProviderManager
{
    public function __construct(private readonly Application $app) {}

    public function provider(?string $driver = null): InstagramDataProvider
    {
        $driver ??= (string) config('services.discovery.driver');

        return match ($driver) {
            'scrapecreators' => $this->configured(
                'services.discovery.scrapecreators.api_key',
                ScrapeCreatorsInstagramProvider::class,
                'ScrapeCreators is not configured. Set SCRAPECREATORS_API_KEY.',
            ),
            'mock' => $this->mock(),
            default => throw new ContentDiscoveryException("Unknown Instagram discovery provider [{$driver}]."),
        };
    }

    /** @param class-string<InstagramDataProvider> $provider */
    private function configured(string $credential, string $provider, string $message): InstagramDataProvider
    {
        if (! (bool) config($credential)) {
            throw new ContentDiscoveryException($message);
        }

        return $this->app->make($provider);
    }

    private function mock(): InstagramDataProvider
    {
        if ($this->app->environment('production')) {
            throw new ContentDiscoveryException('Mock Instagram discovery is not available in production.');
        }

        return $this->app->make(MockInstagramDataProvider::class);
    }
}
