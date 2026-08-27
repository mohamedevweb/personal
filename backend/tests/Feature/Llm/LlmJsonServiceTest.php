<?php

namespace Tests\Feature\Llm;

use Anthropic\Client as AnthropicClient;
use App\Services\Llm\LlmJsonService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OpenAI;
use Tests\TestCase;

class LlmJsonServiceTest extends TestCase
{
    public function test_openai_analysis_preserves_output_room_after_reasoning(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.analysis_reasoning_effort', 'low');
        config()->set('services.openai.analysis_max_output_tokens', 5000);
        $sentBodies = [];
        $handler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($this->response())),
        ]);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::mapRequest(function ($request) use (&$sentBodies) {
            $sentBodies[] = json_decode((string) $request->getBody(), true);

            return $request;
        }));
        $openai = OpenAI::factory()
            ->withApiKey('test-key')
            ->withHttpClient(new GuzzleClient(['handler' => $stack]))
            ->make();
        $service = new LlmJsonService($openai, app(AnthropicClient::class));

        $result = $service->object('Return JSON.', 'Return ok.', [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['ok'],
            'properties' => ['ok' => ['type' => 'boolean']],
        ]);

        $this->assertSame(['ok' => true], $result);
        $this->assertSame(5000, $sentBodies[0]['max_output_tokens']);
        $this->assertSame(['effort' => 'low'], $sentBodies[0]['reasoning']);
    }

    /** @return array<string, mixed> */
    private function response(): array
    {
        return [
            'id' => 'resp_1',
            'object' => 'response',
            'created_at' => 1755000000,
            'status' => 'completed',
            'error' => null,
            'incomplete_details' => null,
            'instructions' => null,
            'max_output_tokens' => 5000,
            'model' => 'gpt-5',
            'output' => [[
                'id' => 'msg_1',
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => '{"ok":true}',
                    'annotations' => [],
                ]],
            ]],
            'parallel_tool_calls' => true,
            'previous_response_id' => null,
            'store' => true,
            'temperature' => 1.0,
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => 'result',
                'strict' => true,
                'schema' => ['type' => 'object'],
            ]],
            'tool_choice' => 'auto',
            'tools' => [],
            'top_p' => 1.0,
            'truncation' => 'disabled',
            'usage' => [
                'input_tokens' => 20,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens' => 10,
                'output_tokens_details' => ['reasoning_tokens' => 0],
                'total_tokens' => 30,
            ],
            'user' => null,
            'metadata' => [],
        ];
    }
}
