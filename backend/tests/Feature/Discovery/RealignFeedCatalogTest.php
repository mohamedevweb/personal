<?php

namespace Tests\Feature\Discovery;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\CanonicalCreatorVerticals;
use App\Services\Discovery\ContentSafetyDecision;
use App\Services\Discovery\ContentSafetyPolicy;
use App\Services\Discovery\CreatorMarketDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RealignFeedCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_realigns_pending_creator_and_post_records_without_deleting_content(): void
    {
        $creator = Creator::query()->create([
            'username' => 'founder.fr',
            'display_name' => 'Founder FR',
            'bio' => 'Entrepreneur à Paris',
            'niche' => 'personal branding',
            'niche_topics' => ['startup SaaS'],
            'followers' => 10_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'safety_status' => ContentSafetyDecision::PENDING,
            'safety_policy_version' => 0,
        ]);
        $post = ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/realign/',
            'platform' => 'instagram',
            'format' => 'image',
            'hook' => 'Build a SaaS',
            'caption' => 'Build a SaaS in Paris',
            'views' => 20_000,
            'likes' => 1_000,
            'comments' => 50,
            'published_at' => now(),
            'safety_status' => ContentSafetyDecision::PENDING,
            'safety_policy_version' => 0,
        ]);

        $safety = Mockery::mock(ContentSafetyPolicy::class);
        $safety->shouldReceive('storedCreator')->once()->andReturn(new ContentSafetyDecision(ContentSafetyDecision::ALLOWED));
        $safety->shouldReceive('storedPost')->once()->andReturn(new ContentSafetyDecision(ContentSafetyDecision::ALLOWED));
        $this->app->instance(ContentSafetyPolicy::class, $safety);

        $markets = Mockery::mock(CreatorMarketDetector::class);
        $markets->shouldReceive('detect')->once()->andReturn([
            'market' => 'FR', 'confidence' => 0.9, 'language' => 'fr', 'evidence' => ['french_language'],
        ]);
        $this->app->instance(CreatorMarketDetector::class, $markets);

        $verticals = Mockery::mock(CanonicalCreatorVerticals::class);
        $verticals->shouldReceive('fromSignals')->once()->andReturn('personal-branding');
        $this->app->instance(CanonicalCreatorVerticals::class, $verticals);

        $this->artisan('personal:realign-feed-catalog', ['--limit' => 10])
            ->assertSuccessful();

        $creator->refresh();
        $post->refresh();
        $this->assertSame(ContentSafetyDecision::ALLOWED, $creator->safety_status);
        $this->assertSame('FR', $creator->market);
        $this->assertSame('personal-branding', $creator->primary_vertical);
        $this->assertSame(ContentSafetyDecision::ALLOWED, $post->safety_status);
        $this->assertNotNull($post->safety_checked_at);
    }
}
