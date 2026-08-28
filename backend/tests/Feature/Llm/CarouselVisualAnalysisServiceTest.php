<?php

namespace Tests\Feature\Llm;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Instagram\InstagramMediaProxy;
use App\Services\Llm\CarouselVisualAnalysisService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI;
use Tests\TestCase;

class CarouselVisualAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_slide_is_sent_in_order_and_the_reading_is_normalized(): void
    {
        config()->set('services.carousel_analysis.model', 'gpt-5');
        config()->set('services.carousel_analysis.max_slides', 10);
        config()->set('services.carousel_analysis.image_detail', 'high');
        config()->set('services.carousel_analysis.max_output_tokens', 4000);
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
        $post = $this->contentPost();
        $service = new CarouselVisualAnalysisService($openai, app(InstagramMediaProxy::class));

        $analysis = $service->analyze($post);

        $this->assertSame('Stop waiting.', data_get($analysis, 'slides.0.text'));
        $this->assertSame(['Large serif hooks'], $analysis['visual_patterns']);
        $this->assertSame(2, $analysis['source_slide_count']);
        $content = $sentBodies[0]['input'][0]['content'];
        $this->assertSame('Slide 1', $content[1]['text']);
        $this->assertSame('https://cdn.example.test/slide-1.jpg', $content[2]['image_url']);
        $this->assertSame('Slide 2', $content[3]['text']);
        $this->assertSame('https://cdn.example.test/slide-2.jpg', $content[4]['image_url']);
        $this->assertSame('high', $content[4]['detail']);
    }

    private function contentPost(): ContentPost
    {
        $creator = Creator::query()->create([
            'username' => 'visual.creator',
            'display_name' => 'Visual Creator',
            'niche' => 'business',
            'followers' => 20_000,
            'average_views' => 8_000,
            'average_likes' => 800,
            'safety_status' => 'allowed',
        ]);

        return ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/visual/',
            'platform' => 'instagram',
            'format' => 'carousel',
            'hook' => 'Visual',
            'caption' => '',
            'thumbnail_url' => 'https://cdn.example.test/slide-1.jpg',
            'media_urls' => [
                'https://cdn.example.test/slide-1.jpg',
                'https://cdn.example.test/slide-2.jpg',
            ],
            'views' => 10_000,
            'likes' => 1_000,
            'comments' => 50,
            'published_at' => now(),
            'performance_ratio' => 2,
            'outlier_score' => 2,
            'safety_status' => 'allowed',
        ]);
    }

    /** @return array<string, mixed> */
    private function response(): array
    {
        return [
            'id' => 'resp_carousel',
            'object' => 'response',
            'created_at' => 1755000000,
            'status' => 'completed',
            'error' => null,
            'incomplete_details' => null,
            'instructions' => null,
            'max_output_tokens' => 4000,
            'model' => 'gpt-5',
            'output' => [[
                'id' => 'msg_carousel',
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'slides' => [
                            ['text' => 'Stop waiting.', 'role' => 'Hook', 'visual_description' => 'Large serif type.'],
                            ['text' => 'Start today.', 'role' => 'Action', 'visual_description' => 'Small sans serif type.'],
                        ],
                        'hook' => 'Stop waiting.',
                        'narrative_structure' => 'Challenge, then action.',
                        'visual_patterns' => ['Large serif hooks'],
                        'content_patterns' => ['Ends with one action'],
                        'tone' => ['Direct'],
                    ], JSON_THROW_ON_ERROR),
                    'annotations' => [],
                ]],
            ]],
            'parallel_tool_calls' => true,
            'previous_response_id' => null,
            'store' => true,
            'temperature' => 1.0,
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => 'carousel_analysis',
                'strict' => true,
                'schema' => ['type' => 'object'],
            ]],
            'tool_choice' => 'auto',
            'tools' => [],
            'top_p' => 1.0,
            'truncation' => 'disabled',
            'usage' => [
                'input_tokens' => 500,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens' => 100,
                'output_tokens_details' => ['reasoning_tokens' => 0],
                'total_tokens' => 600,
            ],
            'user' => null,
            'metadata' => [],
        ];
    }
}
