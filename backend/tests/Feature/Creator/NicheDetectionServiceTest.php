<?php

namespace Tests\Feature\Creator;

use App\Models\InstagramAccount;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Instagram\NicheDetectionService;
use Tests\TestCase;

class NicheDetectionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key');
        config()->set('services.anthropic.api_key');
    }

    public function test_one_caption_without_a_descriptive_bio_is_not_presented_as_a_niche(): void
    {
        $account = new InstagramAccount([
            'username' => 'sample.creator',
            'display_name' => 'Sample Creator',
        ]);

        $signals = app(NicheDetectionService::class)->detect($account, [[
            'caption' => 'https://example.test @sample.creator VivaTech 2026',
        ]]);

        $this->assertNull($signals['primary_niche']);
        $this->assertSame([], $signals['topics']);
        $this->assertSame('insufficient_evidence', $signals['analysis_status']);
        $this->assertSame('none', $signals['analysis_method']);
        $this->assertSame(1, $signals['evidence']['caption_count']);
    }

    public function test_offline_fallback_uses_a_canonical_vertical_instead_of_joining_raw_words(): void
    {
        $account = new InstagramAccount([
            'username' => 'founder.creator',
            'bio' => 'Building SaaS products with artificial intelligence',
        ]);

        $signals = app(NicheDetectionService::class)->detect($account, [[
            'caption' => 'Three lessons from building SaaS in 2026 https://example.test/post',
        ]]);

        $this->assertSame('Tech & AI', $signals['primary_niche']);
        $this->assertSame('partial', $signals['analysis_status']);
        $this->assertSame('heuristic', $signals['analysis_method']);
        $this->assertContains('Saas', $signals['topics']);
        $this->assertStringContainsString('concise captions', $signals['voice_profile']);
        $this->assertNotContains('Http', $signals['topics']);
        $this->assertNotContains('2026', $signals['topics']);
    }

    public function test_vertical_aliases_must_match_whole_words(): void
    {
        $verticals = app(CanonicalCreatorVerticals::class);

        $this->assertNull($verticals->fromSignals(['VivaTech 2026']));
        $this->assertSame('tech-ai', $verticals->fromSignals(['AI SaaS founder']));
    }
}
