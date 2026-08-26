<?php

namespace Tests\Feature\Instagram;

use App\Exceptions\ContentDiscoveryException;
use App\Services\Discovery\HikerInstagramProvider;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\InstagramDataProviderManager;
use App\Services\Discovery\MockInstagramDataProvider;
use App\Services\Discovery\ScrapeCreatorsInstagramProvider;
use Tests\TestCase;

class InstagramDataProviderBindingTest extends TestCase
{
    public function test_hiker_driver_resolves_the_real_provider_when_configured(): void
    {
        config([
            'services.discovery.driver' => 'hiker',
            'services.discovery.hiker.api_key' => 'test-key',
        ]);

        $this->assertInstanceOf(HikerInstagramProvider::class, app(InstagramDataProvider::class));
    }

    public function test_hiker_driver_does_not_fall_back_to_fake_data_without_a_key(): void
    {
        config([
            'services.discovery.driver' => 'hiker',
            'services.discovery.hiker.api_key' => null,
        ]);

        $this->expectException(ContentDiscoveryException::class);

        app(InstagramDataProvider::class);
    }

    public function test_mock_driver_remains_available_during_tests(): void
    {
        config(['services.discovery.driver' => 'mock']);

        $this->assertInstanceOf(MockInstagramDataProvider::class, app(InstagramDataProvider::class));
    }

    public function test_scrapecreators_driver_resolves_the_real_provider_when_configured(): void
    {
        config([
            'services.discovery.driver' => 'scrapecreators',
            'services.discovery.scrapecreators.api_key' => 'test-key',
        ]);

        $this->assertInstanceOf(ScrapeCreatorsInstagramProvider::class, app(InstagramDataProvider::class));
    }

    public function test_a_provider_can_be_selected_explicitly_without_changing_the_default(): void
    {
        config([
            'services.discovery.driver' => 'hiker',
            'services.discovery.hiker.api_key' => 'hiker-key',
            'services.discovery.scrapecreators.api_key' => 'scrapecreators-key',
        ]);

        $providers = app(InstagramDataProviderManager::class);

        $this->assertInstanceOf(HikerInstagramProvider::class, $providers->provider());
        $this->assertInstanceOf(ScrapeCreatorsInstagramProvider::class, $providers->provider('scrapecreators'));
    }
}
