<?php

namespace Tests\Feature\Discovery;

use App\Jobs\Discovery\MeasureAccountEngagement;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorNicheCatalog;
use App\Services\Discovery\CreatorNicheService;
use App\Services\Discovery\DiscoveredPost;
use App\Services\Discovery\DiscoveredProfile;
use App\Services\Discovery\InstagramDataProvider;
use App\Services\Discovery\OutlierScore;
use App\Services\Instagram\InstagramMediaProxy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use OpenAI\Resources\Moderations;
use OpenAI\Resources\Responses;
use OpenAI\Responses\Moderations\CreateResponse;
use OpenAI\Responses\Responses\CreateResponse as ResponsesCreateResponse;
use OpenAI\Testing\ClientFake;
use OpenAI\Testing\Enums\OverrideStrategy;
use RuntimeException;
use Tests\TestCase;

class ContentSafetyPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discovery.safety.enabled' => true,
            'services.discovery.safety.use_openai' => false,
            'services.discovery.safety.enforce_policy' => false,
            'services.discovery.min_followers' => 5000,
        ]);
    }

    public function test_it_blocks_explicit_creator_profiles_and_abusive_posts_locally(): void
    {
        $policy = app(ContentSafetyPolicy::class);
        $creator = $this->profile('adult.creator', 'Contenu adulte et OnlyFans');
        $post = $this->discoveredPost('Tu es vraiment un connard');

        $creatorDecision = $policy->creator($creator);
        $postDecision = $policy->post($post);

        $this->assertSame(ContentSafetyDecision::BLOCKED, $creatorDecision->status);
        $this->assertContains('term:contenu adulte', $creatorDecision->reasons);
        $this->assertSame(ContentSafetyDecision::BLOCKED, $postDecision->status);
        $this->assertContains('term:connard', $postDecision->reasons);
    }

    public function test_it_keeps_safe_profiles_and_posts_allowed(): void
    {
        $policy = app(ContentSafetyPolicy::class);

        $this->assertTrue($policy->creator($this->profile('studio.food', 'Recettes simples du quotidien'))->isAllowed());
        $this->assertTrue($policy->post($this->discoveredPost('Trois idées pour préparer le dîner'))->isAllowed());
    }

    public function test_it_sends_the_caption_and_thumbnail_to_multimodal_moderation(): void
    {
        config([
            'services.discovery.safety.use_openai' => true,
            'services.openai.api_key' => 'test-key',
        ]);
        $client = new ClientFake([
            CreateResponse::fake([
                'results' => [['categories' => ['sexual' => true]]],
            ]),
        ]);

        $decision = $this->policy($client)->post($this->discoveredPost('Une publication'));

        $this->assertSame(ContentSafetyDecision::BLOCKED, $decision->status);
        $this->assertContains('moderation:sexual', $decision->reasons);
        $client->assertSent(Moderations::class, function (string $method, array $parameters): bool {
            return $method === 'create'
                && $parameters['model'] === 'omni-moderation-latest'
                && $parameters['input'][0]['type'] === 'text'
                && $parameters['input'][1]['type'] === 'image_url'
                && $parameters['input'][1]['image_url']['url'] === 'https://cdn.example.test/post-1.jpg';
        });
    }

    public function test_it_downloads_instagram_thumbnails_before_multimodal_moderation(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://scontent-cdg4-3.cdninstagram.com/*' => Http::response('jpeg-body', 200, ['Content-Type' => 'image/jpeg']),
        ]);
        config([
            'services.discovery.safety.use_openai' => true,
            'services.openai.api_key' => 'test-key',
        ]);
        $client = new ClientFake([
            CreateResponse::fake([
                'results' => [['categories' => [
                    'hate/threatening' => false,
                    'sexual' => false,
                    'violence' => false,
                ]]],
            ]),
        ]);
        $post = $this->discoveredPost('Une publication');
        $post = new DiscoveredPost(
            sourceUrl: $post->sourceUrl,
            username: $post->username,
            displayName: $post->displayName,
            avatarUrl: $post->avatarUrl,
            followers: $post->followers,
            caption: $post->caption,
            thumbnailUrl: 'https://scontent-cdg4-3.cdninstagram.com/image.jpg?token=temporary',
            likes: $post->likes,
            comments: $post->comments,
            views: $post->views,
            publishedAt: $post->publishedAt,
            format: $post->format,
            hashtags: $post->hashtags,
            externalId: $post->externalId,
        );

        $decision = $this->policy($client)->post($post);

        $this->assertTrue($decision->isAllowed());
        Http::assertSentCount(1);
        $client->assertSent(Moderations::class, fn (string $method, array $parameters): bool => $method === 'create'
            && $parameters['input'][1]['image_url']['url'] === 'data:image/jpeg;base64,'.base64_encode('jpeg-body'));
    }

    public function test_it_blocks_the_fixed_product_policy_across_every_carousel_frame(): void
    {
        config([
            'services.discovery.safety.use_openai' => true,
            'services.discovery.safety.enforce_policy' => true,
            'services.discovery.safety.policy_model' => 'gpt-5',
            'services.discovery.safety.policy_reasoning_effort' => 'low',
            'services.discovery.safety.policy_max_output_tokens' => 2500,
            'services.discovery.safety.policy_max_frames' => 10,
            'services.discovery.safety.policy_image_detail' => 'low',
            'services.openai.api_key' => 'test-key',
        ]);
        $client = new ClientFake([
            CreateResponse::fake(['results' => [[
                'categories' => $this->moderationCategories(),
                'category_scores' => $this->moderationScores(),
                'flagged' => false,
            ]]]),
            ResponsesCreateResponse::fake(['output' => [[
                'type' => 'message',
                'id' => 'msg_policy',
                'status' => 'completed',
                'role' => 'assistant',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'sexual_suggestive' => false,
                        'nudity' => false,
                        'lingerie_bikini' => true,
                        'sexual_adult_topics' => false,
                        'graphic_violence' => false,
                    ], JSON_THROW_ON_ERROR),
                    'annotations' => [],
                ]],
            ]]], strategy: OverrideStrategy::Replace),
        ]);
        $post = $this->discoveredPost('Summer campaign');
        $post = new DiscoveredPost(
            sourceUrl: $post->sourceUrl,
            username: $post->username,
            displayName: $post->displayName,
            avatarUrl: $post->avatarUrl,
            followers: $post->followers,
            caption: $post->caption,
            thumbnailUrl: $post->thumbnailUrl,
            likes: $post->likes,
            comments: $post->comments,
            views: $post->views,
            publishedAt: $post->publishedAt,
            format: 'carousel',
            hashtags: $post->hashtags,
            externalId: $post->externalId,
            mediaUrls: [
                'https://cdn.example.test/slide-1.jpg',
                'https://cdn.example.test/slide-2.jpg',
            ],
        );

        $decision = $this->policy($client)->post($post);

        $this->assertSame(ContentSafetyDecision::BLOCKED, $decision->status);
        $this->assertSame(['policy:lingerie/bikini'], $decision->reasons);
        $client->assertSent(Responses::class, function (string $method, array $parameters): bool {
            $content = $parameters['input'][0]['content'];

            return $method === 'create'
                && $parameters['store'] === false
                && $parameters['text']['format']['strict'] === true
                && $content[1]['image_url'] === 'https://cdn.example.test/slide-1.jpg'
                && $content[2]['image_url'] === 'https://cdn.example.test/slide-2.jpg';
        });
    }

    public function test_it_keeps_an_instagram_post_pending_when_its_thumbnail_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        Http::fake(['https://scontent-cdg4-3.cdninstagram.com/*' => Http::response('', 403)]);
        config([
            'services.discovery.safety.use_openai' => true,
            'services.discovery.safety.fail_closed' => true,
            'services.openai.api_key' => 'test-key',
        ]);
        $client = new ClientFake;
        $post = $this->discoveredPost('Une publication');
        $post = new DiscoveredPost(
            sourceUrl: $post->sourceUrl,
            username: $post->username,
            displayName: $post->displayName,
            avatarUrl: $post->avatarUrl,
            followers: $post->followers,
            caption: $post->caption,
            thumbnailUrl: 'https://scontent-cdg4-3.cdninstagram.com/missing.jpg',
            likes: $post->likes,
            comments: $post->comments,
            views: $post->views,
            publishedAt: $post->publishedAt,
            format: $post->format,
            hashtags: $post->hashtags,
            externalId: $post->externalId,
        );

        $decision = $this->policy($client)->post($post);

        $this->assertSame(ContentSafetyDecision::PENDING, $decision->status);
        $this->assertSame(['moderation:unavailable'], $decision->reasons);
    }

    public function test_it_leaves_content_pending_when_remote_moderation_is_unavailable(): void
    {
        config([
            'services.discovery.safety.use_openai' => true,
            'services.discovery.safety.fail_closed' => true,
            'services.openai.api_key' => 'test-key',
        ]);
        $client = new ClientFake([new RuntimeException('Unavailable')]);

        $decision = $this->policy($client)->post($this->discoveredPost('Une publication'));

        $this->assertSame(ContentSafetyDecision::PENDING, $decision->status);
        $this->assertSame(['moderation:unavailable'], $decision->reasons);
    }

    public function test_measurement_rejects_an_unsafe_creator_before_storing_posts(): void
    {
        $profile = $this->profile('explicit.creator', 'Photos nudes et contenu explicite');
        $this->measureProfile($profile);

        $creator = Creator::query()->where('username', 'explicit.creator')->firstOrFail();

        $this->assertSame(ContentSafetyDecision::BLOCKED, $creator->safety_status);
        $this->assertSame(0, $creator->posts()->count());
        $this->assertNotNull($creator->last_measured_at);

        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldNotReceive('getProfile');
        (new MeasureAccountEngagement(['explicit.creator']))->handle(
            $provider,
            app(CreatorNicheService::class),
            app(CreatorNicheCatalog::class),
            app(OutlierScore::class),
            app(ContentSafetyPolicy::class),
        );
    }

    public function test_measurement_stores_and_scores_only_safe_posts(): void
    {
        $profile = new DiscoveredProfile(
            username: 'mixed.creator',
            displayName: 'Mixed Creator',
            avatarUrl: null,
            followers: 20_000,
            posts: collect([
                $this->discoveredPost('Une méthode claire pour mieux organiser sa semaine', 'safe-post'),
                $this->discoveredPost('Tu es un idiot et un abruti', 'unsafe-post'),
            ]),
            bio: 'Conseils de productivité',
            externalId: 'mixed-creator',
        );

        $this->measureProfile($profile);

        $creator = Creator::query()->where('username', 'mixed.creator')->firstOrFail();

        $this->assertSame(ContentSafetyDecision::ALLOWED, $creator->safety_status);
        $this->assertDatabaseHas('content_posts', [
            'instagram_media_id' => 'safe-post',
            'safety_status' => ContentSafetyDecision::ALLOWED,
        ]);
        $this->assertDatabaseMissing('content_posts', ['instagram_media_id' => 'unsafe-post']);
        $this->assertNotNull(ContentPost::query()->where('instagram_media_id', 'safe-post')->value('measured_at'));
    }

    private function measureProfile(DiscoveredProfile $profile): void
    {
        $provider = Mockery::mock(InstagramDataProvider::class);
        $provider->shouldReceive('getProfile')->once()->with($profile->username, true)->andReturn($profile);

        (new MeasureAccountEngagement([$profile->username]))->handle(
            $provider,
            app(CreatorNicheService::class),
            app(CreatorNicheCatalog::class),
            app(OutlierScore::class),
            app(ContentSafetyPolicy::class),
        );
    }

    private function policy(ClientFake $client): ContentSafetyPolicy
    {
        return new ContentSafetyPolicy($client, app(InstagramMediaProxy::class));
    }

    private function profile(string $username, string $bio): DiscoveredProfile
    {
        return new DiscoveredProfile(
            username: $username,
            displayName: str($username)->headline()->toString(),
            avatarUrl: null,
            followers: 20_000,
            posts: collect([$this->discoveredPost('Une publication utile')]),
            bio: $bio,
            externalId: $username,
        );
    }

    private function discoveredPost(string $caption, string $externalId = 'post-1'): DiscoveredPost
    {
        return new DiscoveredPost(
            sourceUrl: 'https://www.instagram.com/p/'.$externalId.'/',
            username: 'creator',
            displayName: 'Creator',
            avatarUrl: null,
            followers: 20_000,
            caption: $caption,
            thumbnailUrl: 'https://cdn.example.test/'.$externalId.'.jpg',
            likes: 1200,
            comments: 80,
            views: 15_000,
            publishedAt: CarbonImmutable::now()->subDay(),
            format: 'reel',
            hashtags: [],
            externalId: $externalId,
        );
    }

    /** @return array<string, bool> */
    private function moderationCategories(): array
    {
        return [
            'hate' => false,
            'hate/threatening' => false,
            'harassment' => false,
            'harassment/threatening' => false,
            'self-harm' => false,
            'self-harm/intent' => false,
            'self-harm/instructions' => false,
            'sexual' => false,
            'sexual/minors' => false,
            'violence' => false,
            'violence/graphic' => false,
        ];
    }

    /** @return array<string, float> */
    private function moderationScores(): array
    {
        return collect(array_keys($this->moderationCategories()))
            ->mapWithKeys(fn (string $category): array => [$category => 0.0])
            ->all();
    }
}
