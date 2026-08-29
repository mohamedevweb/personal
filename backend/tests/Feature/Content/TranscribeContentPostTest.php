<?php

namespace Tests\Feature\Content;

use App\Jobs\Content\TranscribeContentPost;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentPostMediaRefresh;
use App\Services\Discovery\ReelVideoFetcher;
use App\Services\Llm\AudioTranscriptionService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OpenAI;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use Tests\TestCase;

class TranscribeContentPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.transcription.enabled', true);
        config()->set('services.openai.api_key', 'test-key');
    }

    public function test_a_reel_is_downloaded_and_its_spoken_script_is_stored(): void
    {
        $this->fakeCdn();
        $this->fakeTranscription('Everyone has a dream. Most people never start.');
        $post = $this->reel();

        $this->runJob($post);

        $post->refresh();
        $this->assertSame('Everyone has a dream. Most people never start.', $post->transcript);
        $this->assertSame(TranscribeContentPost::DONE, $post->transcript_status);
        $this->assertNotNull($post->transcribed_at);
        $this->assertNotNull($post->transcription_started_at);
        $this->assertNotNull($post->transcription_duration_ms);
    }

    public function test_a_post_that_is_not_a_reel_is_never_downloaded(): void
    {
        Http::fake();
        $post = $this->reel(['format' => 'image', 'video_url' => null]);

        $this->runJob($post);

        $this->assertSame(TranscribeContentPost::UNAVAILABLE, $post->refresh()->transcript_status);
        $this->assertNull($post->transcript);
        Http::assertNothingSent();
    }

    public function test_a_reel_longer_than_the_cap_costs_no_bandwidth(): void
    {
        Http::fake();
        $post = $this->reel(['metadata' => ['video_duration' => 400]]);

        $this->runJob($post);

        $this->assertSame(TranscribeContentPost::UNAVAILABLE, $post->refresh()->transcript_status);
        Http::assertNothingSent();
    }

    public function test_a_video_url_outside_the_instagram_cdn_is_refused(): void
    {
        Http::fake();
        $post = $this->reel(['video_url' => 'https://example.com/video.mp4']);

        $this->runJob($post);

        $this->assertSame(TranscribeContentPost::UNAVAILABLE, $post->refresh()->transcript_status);
        Http::assertNothingSent();
    }

    public function test_an_expired_signed_url_is_unavailable_not_an_error(): void
    {
        Http::fake(['*' => Http::response('', 403)]);
        $post = $this->reel();

        $this->runJob($post);

        $this->assertSame(TranscribeContentPost::UNAVAILABLE, $post->refresh()->transcript_status);
    }

    public function test_a_transcription_failure_is_retryable_and_keeps_the_batch_alive(): void
    {
        $this->fakeCdn();
        $this->bindOpenAi(new Response(500, [], '{"error":{"message":"boom"}}'));
        $post = $this->reel();

        $this->runJob($post);

        $this->assertSame(TranscribeContentPost::FAILED, $post->refresh()->transcript_status);
        $this->assertNull($post->transcript);
    }

    public function test_a_reel_without_speech_is_unavailable(): void
    {
        $this->fakeCdn();
        $this->fakeTranscription('   ');
        $post = $this->reel();

        $this->runJob($post);

        $this->assertSame(TranscribeContentPost::UNAVAILABLE, $post->refresh()->transcript_status);
    }

    public function test_the_feature_flag_stops_every_call(): void
    {
        config()->set('services.transcription.enabled', false);
        Http::fake();
        $post = $this->reel();

        $this->runJob($post);

        $this->assertSame('pending', $post->refresh()->transcript_status);
        Http::assertNothingSent();
    }

    public function test_an_already_transcribed_reel_is_never_paid_for_twice(): void
    {
        Http::fake();
        $post = $this->reel([
            'transcript' => 'Already read.',
            'transcript_status' => TranscribeContentPost::DONE,
        ]);

        $this->runJob($post);

        $this->assertSame('Already read.', $post->refresh()->transcript);
        Http::assertNothingSent();
    }

    private function runJob(ContentPost $post): void
    {
        (new TranscribeContentPost($post->id))->handle(
            app(ReelVideoFetcher::class),
            app(AudioTranscriptionService::class),
            app(ContentPostMediaRefresh::class),
        );
    }

    private function fakeCdn(): void
    {
        Http::fake(['*.cdninstagram.com/*' => Http::response('binary-mp4-bytes', 200, ['Content-Type' => 'video/mp4'])]);
    }

    private function fakeTranscription(string $text): void
    {
        $this->bindOpenAi(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['text' => $text])));
    }

    private function bindOpenAi(Response $response): void
    {
        $client = OpenAI::factory()
            ->withApiKey('test-key')
            ->withHttpClient(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([$response]))]))
            ->make();

        $this->app->instance(OpenAiClient::class, $client);
    }

    private function reel(array $overrides = []): ContentPost
    {
        $creator = Creator::query()->create([
            'username' => 'transcribed.creator',
            'display_name' => 'Transcribed Creator',
            'niche' => 'business',
            'followers' => 50_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'safety_status' => 'allowed',
        ]);

        return ContentPost::query()->create(array_merge([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/reel/transcribed/',
            'platform' => 'instagram',
            'format' => 'reel',
            'hook' => 'A spoken hook',
            'caption' => '🔥 #entrepreneur',
            'video_url' => 'https://scontent.cdninstagram.com/reel.mp4',
            'views' => 100_000,
            'likes' => 5_000,
            'comments' => 200,
            'published_at' => now()->subDay(),
            'outlier_score' => 2,
            'metadata' => ['video_duration' => 45],
        ], $overrides));
    }
}
