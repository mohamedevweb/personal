<?php

namespace Tests\Feature\Discovery;

use App\Services\Discovery\CanonicalCreatorVerticals;
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
                $this->assertStringContainsString('synthesize a durable Creator DNA summary', $instructions);
                $this->assertStringContainsString('content mechanics', $instructions);
                $this->assertStringContainsString('business (12/12 posts)', $input);
                $this->assertStringContainsString('dreams (12/12 posts)', $input);
                $this->assertStringNotContainsString('simonsquibb (', (string) $profileEvidence);
                $this->assertStringNotContainsString('food (', (string) $profileEvidence);
                $this->assertStringNotContainsString('pasta (', (string) $profileEvidence);
                $this->assertStringContainsString('[Post 12] LAST_SAMPLE_MARKER', $input);
                $this->assertContains('content_mechanics', $schema['required']);
                $this->assertContains('primary_vertical', $schema['required']);

                return true;
            })
            ->andReturn([
                'niche' => 'entrepreneurship giveaways',
                'primary_vertical' => 'business',
                'topics' => ['starting a business', 'business education', 'dream building', 'food', 'pasta'],
                'content_mechanics' => ['giveaways', 'street interviews'],
                'evidence_summary' => 'The bio and repeated posts consistently focus on entrepreneurship.',
                'confidence' => 0.96,
            ]);

        $signals = (new CreatorNicheService($llm, app(CanonicalCreatorVerticals::class)))->detect($profile);

        $this->assertSame('entrepreneurship', $signals['niche']);
        $this->assertSame('business', $signals['primary_vertical']);
        $this->assertContains('starting a business', $signals['topics']);
        $this->assertNotContains('food', $signals['topics']);
        $this->assertNotContains('pasta', $signals['topics']);
    }

    public function test_the_model_can_classify_a_local_event_organizer_in_the_merged_vertical(): void
    {
        $profile = new DiscoveredProfile(
            username: 'soiree_comptoir_lumiere',
            displayName: 'Comptoir Lumière',
            avatarUrl: null,
            followers: 30_000,
            posts: collect([
                new DiscoveredPost(
                    sourceUrl: 'https://instagram.test/p/contre-soiree',
                    username: 'soiree_comptoir_lumiere',
                    displayName: 'Comptoir Lumière',
                    avatarUrl: null,
                    followers: 30_000,
                    caption: 'La CONTRESOIREE, une soirée guidée pour créer des rencontres à Lyon.',
                    thumbnailUrl: null,
                    likes: 1_000,
                    comments: 50,
                    views: 10_000,
                    publishedAt: CarbonImmutable::now()->subDay(),
                    format: 'reel',
                    hashtags: ['comptoirlumiere'],
                    externalId: 'contre-soiree',
                ),
                new DiscoveredPost(
                    sourceUrl: 'https://instagram.test/p/picnic-lumiere',
                    username: 'soiree_comptoir_lumiere',
                    displayName: 'Comptoir Lumière',
                    avatarUrl: null,
                    followers: 30_000,
                    caption: 'Réserve ta place pour notre prochain événement convivial à Lyon.',
                    thumbnailUrl: null,
                    likes: 900,
                    comments: 40,
                    views: 9_000,
                    publishedAt: CarbonImmutable::now()->subDays(2),
                    format: 'reel',
                    hashtags: ['comptoirlumiere'],
                    externalId: 'picnic-lumiere',
                ),
            ]),
            bio: 'Des événements intimistes pour créer des liens authentiques à Lyon.',
        );
        $llm = Mockery::mock(LlmJsonService::class);
        $llm->shouldReceive('object')
            ->once()
            ->withArgs(function (string $instructions): bool {
                $this->assertStringContainsString('local-culture-events', $instructions);

                return true;
            })
            ->andReturn([
                'primary_vertical' => 'local-culture-events',
                'niche' => 'Social events',
                'topics' => ['guided social events', 'local meetups'],
                'content_mechanics' => [],
                'evidence_summary' => 'The profile consistently promotes recurring social events.',
                'confidence' => 0.97,
            ]);

        $signals = (new CreatorNicheService($llm, app(CanonicalCreatorVerticals::class)))->detect($profile);

        $this->assertSame('local-culture-events', $signals['primary_vertical']);
    }
}
