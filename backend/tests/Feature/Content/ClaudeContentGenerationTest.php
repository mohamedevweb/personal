<?php

namespace Tests\Feature\Content;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Exceptions\ContentGenerationException;
use App\Services\Content\ClaudeContentGenerationService;
use App\Services\Content\ContentDraftAssembler;
use App\Services\Content\ContentDraftBlueprint;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * Exercises the real SDK request/response path with a stubbed PSR-18 transport, so
 * the schema, the beta flags and the response decoding are all covered without
 * reaching the network.
 */
class ClaudeContentGenerationTest extends TestCase
{
    use DraftsContentFixtures;
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $sentBodies = [];

    public function test_a_carousel_is_grounded_in_the_creator_material(): void
    {
        $service = $this->serviceReturning([
            'why_it_works' => ['A specific decision creates tension', 'The lesson is usable'],
            'your_version' => 'I pivoted after four months of research.',
            'slides' => [
                ['text' => 'I pivoted after four months of research.'],
                ['text' => 'The research said one thing. My customers said another.'],
                ['text' => 'Here is what I changed.'],
            ],
        ]);

        [$user, $post, $moment] = $this->draftFixtures();

        $result = $service->generate($post, $user, 'carousel', $moment);

        $this->assertSame($post->hook, $result['original_pattern']);
        $this->assertSame($moment->content, $result['your_context']);
        $this->assertSame('Entrepreneurship / SaaS', $result['profile_used']['niche']);
        $this->assertSame([1, 2, 3], array_column($result['slides'], 'id'));
        $this->assertSame('Here is what I changed.', $result['slides'][2]['text']);
        $this->assertArrayNotHasKey('caption', $result);

        $body = $this->sentBodies[0];
        $this->assertSame(config('services.anthropic.model'), $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame(
            ['why_it_works', 'your_version', 'slides'],
            $body['output_config']['format']['schema']['required'],
        );
        $this->assertStringContainsString($moment->content, $body['messages'][0]['content']);
        $this->assertStringContainsString($post->structure_analysis, $body['messages'][0]['content']);
    }

    public function test_a_caption_request_only_asks_for_caption_fields(): void
    {
        $service = $this->serviceReturning([
            'why_it_works' => ['Opens on a concrete decision'],
            'your_version' => 'I stopped guessing what to post.',
            'caption' => "I stopped guessing what to post.\n\nHere is what changed.",
        ]);

        [$user, $post] = $this->draftFixtures();

        $result = $service->generate($post, $user, 'caption');

        $this->assertArrayHasKey('caption', $result);
        $this->assertArrayNotHasKey('slides', $result);
        $this->assertSame(
            ['why_it_works', 'your_version', 'caption'],
            $this->sentBodies[0]['output_config']['format']['schema']['required'],
        );
    }

    public function test_a_refusal_surfaces_as_a_readable_failure(): void
    {
        $service = $this->serviceReturningRaw([
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5',
            'content' => [],
            'stop_reason' => 'refusal',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 0],
        ]);

        [$user, $post] = $this->draftFixtures();

        $this->expectException(ContentGenerationException::class);
        $service->generate($post, $user, 'reel');
    }

    public function test_an_api_error_surfaces_as_a_readable_failure(): void
    {
        $service = $this->serviceWithTransport(new class implements ClientInterface
        {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(500, ['Content-Type' => 'application/json'], (string) json_encode([
                    'type' => 'error',
                    'error' => ['type' => 'api_error', 'message' => 'Internal server error'],
                ]));
            }
        });

        [$user, $post] = $this->draftFixtures();

        $this->expectException(ContentGenerationException::class);
        $service->generate($post, $user, 'caption');
    }

    /** @param array<string, mixed> $payload */
    private function serviceReturning(array $payload): ClaudeContentGenerationService
    {
        return $this->serviceReturningRaw([
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5',
            'content' => [['type' => 'text', 'text' => (string) json_encode($payload)]],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 200],
        ]);
    }

    /** @param array<string, mixed> $message */
    private function serviceReturningRaw(array $message): ClaudeContentGenerationService
    {
        $sent = &$this->sentBodies;

        return $this->serviceWithTransport(new class($message, $sent) implements ClientInterface
        {
            /**
             * @param  array<string, mixed>  $message
             * @param  list<array<string, mixed>>  $sent
             */
            public function __construct(private array $message, private array &$sent) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->sent[] = json_decode((string) $request->getBody(), true);

                return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($this->message));
            }
        });
    }

    private function serviceWithTransport(ClientInterface $transport): ClaudeContentGenerationService
    {
        return new ClaudeContentGenerationService(
            new Client(
                apiKey: 'test-key',
                requestOptions: RequestOptions::with(maxRetries: 0, transporter: $transport),
            ),
            new ContentDraftBlueprint,
            new ContentDraftAssembler,
        );
    }
}
