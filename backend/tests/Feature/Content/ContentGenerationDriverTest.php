<?php

namespace Tests\Feature\Content;

use App\Services\Content\ClaudeContentGenerationService;
use App\Services\Content\ContentGenerationService;
use App\Services\Content\MockContentGenerationService;
use App\Services\Content\OpenAiContentGenerationService;
use Tests\TestCase;

class ContentGenerationDriverTest extends TestCase
{
    public function test_the_configured_driver_decides_which_model_writes_drafts(): void
    {
        $this->assertResolves('openai', ['services.openai.api_key' => 'sk-test'], OpenAiContentGenerationService::class);
        $this->assertResolves('claude', ['services.anthropic.api_key' => 'sk-test'], ClaudeContentGenerationService::class);
        $this->assertResolves('mock', [], MockContentGenerationService::class);
    }

    public function test_a_driver_without_credentials_falls_back_to_the_mock(): void
    {
        $this->assertResolves('openai', ['services.openai.api_key' => null], MockContentGenerationService::class);
        $this->assertResolves('claude', ['services.anthropic.api_key' => null], MockContentGenerationService::class);
    }

    /** @param array<string, mixed> $config */
    private function assertResolves(string $driver, array $config, string $expected): void
    {
        config(['services.content_generation.driver' => $driver, ...$config]);
        $this->app->forgetInstance(ContentGenerationService::class);

        $this->assertInstanceOf($expected, $this->app->make(ContentGenerationService::class));
    }
}
