<?php

namespace Tests\Feature\Creator;

use App\Models\InstagramAccount;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Instagram\NicheDetectionService;
use App\Services\Llm\LlmJsonService;
use Mockery;
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

    public function test_offline_topics_ignore_generic_caption_words_for_an_athlete(): void
    {
        $account = new InstagramAccount([
            'username' => 'famous.player',
            'display_name' => 'Football Athlete',
            'bio' => 'Professional football athlete sharing training and match preparation.',
        ]);
        $media = collect(range(1, 4))->map(fn (int $index): array => [
            'caption' => "The day was for football training and match preparation {$index}.",
        ])->all();

        $signals = app(NicheDetectionService::class)->detect($account, $media);

        $this->assertSame('Sport & Fitness', $signals['primary_niche']);
        $this->assertContains('Football', $signals['topics']);
        $this->assertNotContains('The', $signals['topics']);
        $this->assertNotContains('And', $signals['topics']);
        $this->assertNotContains('For', $signals['topics']);
        $this->assertNotContains('Was', $signals['topics']);
        $this->assertNotContains('Day', $signals['topics']);
    }

    public function test_llm_analysis_receives_each_recent_caption_instead_of_one_truncated_block(): void
    {
        $account = new InstagramAccount([
            'username' => 'founder.creator',
            'display_name' => 'Founder Creator',
            'bio' => 'I help founders build sustainable businesses.',
        ]);
        $media = collect(range(1, 30))->map(fn (int $index): array => [
            'caption' => $index === 1
                ? str_repeat('A long entrepreneurship story. ', 80)
                : ($index === 30
                    ? 'LAST_CREATOR_DNA_POST about building a company.'
                    : "Business lesson {$index} for founders."),
        ])->all();
        $llm = Mockery::mock(LlmJsonService::class);
        $llm->shouldReceive('object')
            ->once()
            ->withArgs(function (string $instructions, string $input): bool {
                $this->assertStringContainsString('stable editorial identity', $instructions);
                $this->assertStringContainsString('[Post 30] LAST_CREATOR_DNA_POST', $input);

                return true;
            })
            ->andReturn([
                'primary_niche' => 'Entrepreneurship',
                'sub_niches' => ['Business education'],
                'topics' => ['Starting a business'],
                'audience' => ['Founders'],
                'positioning' => 'Helps founders build sustainable companies through practical business lessons.',
                'language' => 'en',
                'content_pillars' => ['Business lessons'],
                'tone' => ['Educational'],
                'current_projects' => ['Founder education series'],
                'goals' => ['Help founders build sustainable businesses'],
                'content_strengths' => ['Practical explanations', 'Direct openings'],
                'voice_profile' => 'Uses direct openings and practical conclusions.',
                'confidence' => 0.92,
            ]);

        $signals = (new NicheDetectionService($llm, app(CanonicalCreatorVerticals::class)))
            ->detect($account, $media);

        $this->assertSame('Entrepreneurship', $signals['primary_niche']);
        $this->assertSame('Helps founders build sustainable companies through practical business lessons.', $signals['positioning']);
        $this->assertSame(['Founder education series'], $signals['current_projects']);
        $this->assertSame(['Help founders build sustainable businesses'], $signals['goals']);
        $this->assertSame(['Practical explanations', 'Direct openings'], $signals['content_strengths']);
        $this->assertSame('complete', $signals['analysis_status']);
        $this->assertSame(30, $signals['evidence']['caption_count']);
    }
}
