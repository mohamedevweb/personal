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

    public function test_offline_fallback_does_not_choose_a_vertical_without_the_model(): void
    {
        $account = new InstagramAccount([
            'username' => 'founder.creator',
            'bio' => 'Building SaaS products with artificial intelligence',
        ]);

        $signals = app(NicheDetectionService::class)->detect($account, [[
            'caption' => 'Three lessons from building SaaS in 2026 https://example.test/post',
        ]]);

        $this->assertNull($signals['primary_vertical']);
        $this->assertNull($signals['primary_niche']);
        $this->assertSame('partial', $signals['analysis_status']);
        $this->assertSame('heuristic', $signals['analysis_method']);
        $this->assertContains('Saas', $signals['topics']);
        $this->assertStringContainsString('concise captions', $signals['voice_profile']);
        $this->assertNotContains('Http', $signals['topics']);
        $this->assertNotContains('2026', $signals['topics']);
    }

    public function test_verticals_are_only_accepted_as_explicit_canonical_slugs(): void
    {
        $verticals = app(CanonicalCreatorVerticals::class);

        $this->assertNull($verticals->fromSignals(['VivaTech 2026']));
        $this->assertNull($verticals->fromSignals(['AI SaaS founder']));
        $this->assertSame('tech-ai', $verticals->fromSignals(['tech-ai']));
    }

    public function test_startup_is_a_distinct_canonical_vertical_from_business(): void
    {
        $verticals = app(CanonicalCreatorVerticals::class);

        $this->assertSame('startup', $verticals->fromSignals(['startup']));
        $this->assertSame('business', $verticals->fromSignals(['business']));
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

        $this->assertNull($signals['primary_vertical']);
        $this->assertNull($signals['primary_niche']);
        $this->assertContains('Football', $signals['topics']);
        $this->assertNotContains('The', $signals['topics']);
        $this->assertNotContains('And', $signals['topics']);
        $this->assertNotContains('For', $signals['topics']);
        $this->assertNotContains('Was', $signals['topics']);
        $this->assertNotContains('Day', $signals['topics']);
    }

    public function test_offline_fallback_does_not_turn_campaign_copy_into_creator_dna(): void
    {
        $account = new InstagramAccount([
            'username' => 'pierre_chartier_x2007',
            'display_name' => 'Pierre Chartier',
            'bio' => "💼 @derivatives_finance\n✉️ Mail Pro : zarch@nuggets-influence.com\nPnL Maker👇🏼",
        ]);
        $media = [
            ['caption' => 'Pierre Chartier rejoint son broker au beach club.'],
            ['caption' => 'Pierre Chartier est sollicité pour avoir son avis.'],
            ['caption' => 'Avec Emergent, pas besoin de savoir coder votre idée.'],
            ['caption' => 'Votre pronostic est attendu par toute l équipe.'],
        ];

        $signals = app(NicheDetectionService::class)->detect($account, $media);

        $this->assertNull($signals['primary_niche']);
        $this->assertNull($signals['positioning']);
        $this->assertSame([], $signals['topics']);
        $this->assertSame([], $signals['content_pillars']);
        $this->assertSame('partial', $signals['analysis_status']);
        $this->assertSame('heuristic', $signals['analysis_method']);
    }

    public function test_a_failed_configured_model_is_reported_as_unavailable_without_false_claims(): void
    {
        config()->set('services.openai.api_key', 'configured-key');
        $llm = Mockery::mock(LlmJsonService::class);
        $llm->shouldReceive('object')->once()->andReturnNull();
        $account = new InstagramAccount([
            'username' => 'pierre_chartier_x2007',
            'display_name' => 'Pierre Chartier',
            'bio' => "💼 @derivatives_finance\n✉️ Mail Pro : zarch@nuggets-influence.com\nPnL Maker👇🏼",
        ]);
        $media = collect(range(1, 4))->map(fn (int $index): array => [
            'caption' => "Pierre Chartier est sollicité pour avoir son avis {$index}.",
        ])->all();

        $signals = (new NicheDetectionService($llm, app(CanonicalCreatorVerticals::class)))
            ->detect($account, $media);

        $this->assertNull($signals['primary_niche']);
        $this->assertSame([], $signals['topics']);
        $this->assertSame('analysis_unavailable', $signals['analysis_status']);
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
                'primary_vertical' => 'business',
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
        $this->assertSame('business', $signals['primary_vertical']);
        $this->assertSame('Helps founders build sustainable companies through practical business lessons.', $signals['positioning']);
        $this->assertSame(['Founder education series'], $signals['current_projects']);
        $this->assertSame(['Help founders build sustainable businesses'], $signals['goals']);
        $this->assertSame(['Practical explanations', 'Direct openings'], $signals['content_strengths']);
        $this->assertSame('complete', $signals['analysis_status']);
        $this->assertSame(30, $signals['evidence']['caption_count']);
    }
}
