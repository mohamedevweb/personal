<?php

namespace Tests\Feature\Discovery;

use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Llm\LlmJsonService;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class CreatorNicheServiceTest extends TestCase
{
    public function test_it_uses_a_balanced_sample_and_rejects_one_off_topics(): void
    {
        $posts = collect(range(1, 12))->map(function (int $index): DiscoveredPost {
            $caption = match ($index) {
                1 => str_repeat('A practical business lesson for people building their dream company. ', 35),
                4 => 'She had to know. A surprising street moment with food and pasta.',
                12 => 'LAST_SAMPLE_MARKER. How to start a business and fund your dream without waiting for permission.',
                default => "Business lesson {$index}. Start your dream company with practical entrepreneurship advice.",
            };

            return new DiscoveredPost(
                sourceUrl: "https://instagram.test/p/simon-{$index}",
                username: 'simonsquibb',
                displayName: 'Simon Squibb | the DREAM guy',
                avatarUrl: null,
                followers: 1_000_000,
                caption: $caption,
                thumbnailUrl: null,
                likes: 10_000,
                comments: 500,
                views: 100_000,
                publishedAt: CarbonImmutable::now()->subDays($index),
                format: 'reel',
                hashtags: $index === 4
                    ? ['simonsquibb', 'dreams', 'business', 'food', 'pasta']
                    : ['simonsquibb', 'dreams', 'business'],
                externalId: "simon-{$index}",
            );
        });
        $profile = new DiscoveredProfile(
            username: 'simonsquibb',
            displayName: 'Simon Squibb | the DREAM guy',
            avatarUrl: null,
            followers: 1_000_000,
            posts: $posts,
            bio: 'I help people start businesses and build their dreams.',
            metadata: [
                'category' => 'Entrepreneur',
                'external_url' => 'https://example.test/dreams',
            ],
        );
        $llm = Mockery::mock(LlmJsonService::class);
        $llm->shouldReceive('object')
            ->once()
            ->withArgs(function (string $instructions, string $input, array $schema): bool {
                $profileEvidence = strstr($input, 'Caption sample:', true);

                $this->assertStringContainsString('stable editorial identity', $instructions);
                $this->assertStringContainsString('content mechanics', $instructions);
                $this->assertStringContainsString('business (12/12 posts)', $input);
                $this->assertStringContainsString('dreams (12/12 posts)', $input);
                $this->assertStringNotContainsString('simonsquibb (', (string) $profileEvidence);
                $this->assertStringNotContainsString('food (', (string) $profileEvidence);
                $this->assertStringNotContainsString('pasta (', (string) $profileEvidence);
                $this->assertStringContainsString('[Post 12] LAST_SAMPLE_MARKER', $input);
                $this->assertContains('content_mechanics', $schema['required']);

                return true;
            })
            ->andReturn([
                'niche' => 'entrepreneurship giveaways',
                'topics' => ['starting a business', 'business education', 'dream building', 'food', 'pasta'],
                'content_mechanics' => ['giveaways', 'street interviews'],
                'evidence_summary' => 'The bio and repeated posts consistently focus on entrepreneurship.',
                'confidence' => 0.96,
            ]);

        $signals = (new CreatorNicheService($llm))->detect($profile);

        $this->assertSame('entrepreneurship', $signals['niche']);
        $this->assertContains('starting a business', $signals['topics']);
        $this->assertNotContains('food', $signals['topics']);
        $this->assertNotContains('pasta', $signals['topics']);
    }
}
