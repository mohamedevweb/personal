<?php

namespace Tests\Feature\Content;

use App\Jobs\Content\AnalyzeCarouselContentPost;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentPostMediaRefresh;
use App\Services\Llm\CarouselVisualAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AnalyzeCarouselContentPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.carousel_analysis.enabled', true);
        config()->set('services.openai.api_key', 'test-key');
    }

    public function test_visual_analysis_is_stored_for_a_carousel(): void
    {
        $post = $this->contentPost();
        $analysis = [
            'slides' => [['position' => 1, 'text' => 'Stop waiting', 'role' => 'Hook', 'visual_description' => 'Large type']],
            'hook' => 'Stop waiting',
            'narrative_structure' => 'Challenge, explanation, action.',
            'visual_patterns' => ['Large serif hooks'],
            'content_patterns' => ['Ends with one action'],
            'tone' => ['Direct'],
            'source_slide_count' => 2,
            'analysis_version' => CarouselVisualAnalysisService::ANALYSIS_VERSION,
        ];
        $service = Mockery::mock(CarouselVisualAnalysisService::class);
        $service->shouldReceive('analyze')->once()->withArgs(fn (ContentPost $value): bool => $value->is($post))->andReturn($analysis);

        (new AnalyzeCarouselContentPost($post->id))->handle($service, app(ContentPostMediaRefresh::class));

        $post->refresh();
        $this->assertSame(AnalyzeCarouselContentPost::DONE, $post->carousel_analysis_status);
        $this->assertSame('Stop waiting', data_get($post->carousel_analysis, 'slides.0.text'));
        $this->assertNotNull($post->carousel_analyzed_at);
        $this->assertNotNull($post->carousel_analysis_started_at);
        $this->assertNotNull($post->carousel_analysis_duration_ms);
    }

    public function test_a_completed_current_analysis_is_not_paid_for_twice(): void
    {
        $post = $this->contentPost([
            'carousel_analysis' => ['analysis_version' => CarouselVisualAnalysisService::ANALYSIS_VERSION],
            'carousel_analysis_status' => AnalyzeCarouselContentPost::DONE,
        ]);
        $service = Mockery::mock(CarouselVisualAnalysisService::class);
        $service->shouldNotReceive('analyze');

        (new AnalyzeCarouselContentPost($post->id))->handle($service, app(ContentPostMediaRefresh::class));

        $this->assertSame(AnalyzeCarouselContentPost::DONE, $post->refresh()->carousel_analysis_status);
    }

    public function test_a_non_carousel_is_marked_unavailable(): void
    {
        $post = $this->contentPost(['format' => 'image']);
        $service = Mockery::mock(CarouselVisualAnalysisService::class);
        $service->shouldNotReceive('analyze');

        (new AnalyzeCarouselContentPost($post->id))->handle($service, app(ContentPostMediaRefresh::class));

        $this->assertSame(AnalyzeCarouselContentPost::UNAVAILABLE, $post->refresh()->carousel_analysis_status);
    }

    private function contentPost(array $overrides = []): ContentPost
    {
        $creator = Creator::query()->create([
            'username' => 'carousel.creator',
            'display_name' => 'Carousel Creator',
            'niche' => 'business',
            'followers' => 50_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'safety_status' => 'allowed',
        ]);

        return ContentPost::query()->create(array_merge([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/carousel/',
            'platform' => 'instagram',
            'format' => 'carousel',
            'hook' => 'Carousel',
            'caption' => 'A useful carousel.',
            'thumbnail_url' => 'https://cdn.example.test/slide-1.jpg',
            'media_urls' => [
                'https://cdn.example.test/slide-1.jpg',
                'https://cdn.example.test/slide-2.jpg',
            ],
            'views' => 20_000,
            'likes' => 2_000,
            'comments' => 100,
            'published_at' => now(),
            'performance_ratio' => 2,
            'outlier_score' => 2,
            'safety_status' => 'allowed',
        ], $overrides));
    }
}
