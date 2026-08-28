<?php

namespace Tests\Feature\Discovery;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\ContentSafetyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EnforceContentSafetyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_the_policy_version_and_disqualifies_blocked_content(): void
    {
        $post = $this->contentPost();
        $policy = Mockery::mock(ContentSafetyPolicy::class);
        $policy->shouldReceive('storedPost')->once()->withArgs(fn (ContentPost $candidate): bool => $candidate->is($post))
            ->andReturn(new ContentSafetyDecision(ContentSafetyDecision::BLOCKED, ['policy:nudity']));
        $this->app->instance(ContentSafetyPolicy::class, $policy);

        $this->artisan('personal:enforce-content-safety-policy', ['--limit' => 1])
            ->expectsOutput('Policy pass: 1 checked, 1 blocked, 0 pending.')
            ->assertSuccessful();

        $post->refresh();
        $this->assertSame(ContentSafetyDecision::BLOCKED, $post->safety_status);
        $this->assertSame(['policy:nudity'], $post->safety_reasons);
        $this->assertSame(ContentSafetyPolicy::VERSION, $post->safety_policy_version);
        $this->assertNull($post->measured_at);
        $this->assertSame(0.0, $post->outlier_score);
    }

    private function contentPost(): ContentPost
    {
        $creator = Creator::query()->create([
            'username' => 'policy.creator',
            'display_name' => 'Policy Creator',
            'niche' => 'fashion',
            'followers' => 20_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'safety_status' => 'allowed',
        ]);

        return ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/policy/',
            'platform' => 'instagram',
            'format' => 'image',
            'hook' => 'Policy',
            'caption' => 'Campaign',
            'thumbnail_url' => 'https://cdn.example.test/policy.jpg',
            'views' => 20_000,
            'likes' => 2_000,
            'comments' => 100,
            'published_at' => now(),
            'performance_ratio' => 2,
            'outlier_score' => 2,
            'engagement_rate' => 0.1,
            'measured_at' => now(),
            'safety_status' => 'allowed',
        ]);
    }
}
