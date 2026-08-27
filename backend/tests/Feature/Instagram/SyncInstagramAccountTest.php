<?php

namespace Tests\Feature\Instagram;

use App\Jobs\Discovery\DiscoverNicheContent;
use App\Jobs\Instagram\SyncInstagramAccount;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramAuthService;
use App\Services\Instagram\NicheDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncInstagramAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_stores_normalized_real_media_and_initializes_creator_profile(): void
    {
        config()->set('services.openai.api_key');
        config()->set('services.anthropic.api_key');
        config()->set('services.instagram.graph_url', 'https://graph.instagram.com');
        config()->set('services.instagram.api_version', 'v25.0');
        config()->set('services.instagram.media_limit', 25);

        $user = User::factory()->create();
        $account = InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => '123',
            'username' => 'founder_creator',
            'display_name' => 'Founder Creator',
            'bio' => 'Building SaaS for the creator economy',
            'account_type' => 'BUSINESS',
            'access_token' => 'server-side-secret',
            'token_expires_at' => now()->addMonth(),
            'connected_at' => now(),
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/me/media')) {
                return Http::response(['data' => [[
                    'id' => 'media-1',
                    'caption' => 'I learned three lessons building my SaaS for creators.',
                    'media_type' => 'VIDEO',
                    'media_product_type' => 'REELS',
                    'permalink' => 'https://instagram.com/reel/example',
                    'thumbnail_url' => 'https://cdn.example.test/thumb.jpg',
                    'timestamp' => '2026-08-15T10:00:00+0000',
                    'like_count' => 84,
                    'comments_count' => 12,
                ]]]);
            }

            if (str_contains($request->url(), '/media-1/insights')) {
                return Http::response(['data' => [
                    ['name' => 'views', 'values' => [['value' => 1900]]],
                    ['name' => 'reach', 'values' => [['value' => 1400]]],
                ]]);
            }

            return Http::response([
                'id' => '123',
                'username' => 'founder_creator',
                'name' => 'Founder Creator',
                'biography' => 'Building SaaS for the creator economy',
                'account_type' => 'BUSINESS',
                'followers_count' => 2500,
                'follows_count' => 300,
                'media_count' => 1,
            ]);
        });

        $job = new SyncInstagramAccount($account->id);
        $job->handle(app(InstagramAuthService::class), app(InstagramApiService::class), app(NicheDetectionService::class));

        $account->refresh();
        $this->assertSame('completed', $account->sync_status);
        $this->assertSame(2500, $account->followers_count);
        $this->assertDatabaseHas('instagram_media', [
            'instagram_media_id' => 'media-1',
            'media_product_type' => 'REELS',
            'like_count' => 84,
        ]);
        $this->assertSame(1900, $account->media()->firstOrFail()->metrics['views']);
        $creatorProfile = $user->creatorProfile()->firstOrFail();
        $this->assertContains('Saas', $creatorProfile->topics);
        $this->assertSame($creatorProfile->niche, $creatorProfile->creator_dna['primary_niche']);
        $this->assertSame('partial', $creatorProfile->creator_dna['analysis_status']);
        $this->assertSame('heuristic', $creatorProfile->creator_dna['analysis_method']);
        $this->assertSame('Building SaaS for the creator economy', $creatorProfile->positioning);
        $this->assertContains('First-person storytelling', $creatorProfile->content_strengths);
        $this->assertContains('Practical education', $creatorProfile->content_strengths);
        $this->assertStringContainsString('first person', $creatorProfile->voice_profile);
        $this->assertNotNull($creatorProfile->dna_analyzed_at);
        $creator = Creator::query()->where('instagram_user_id', '123')->firstOrFail();
        $this->assertSame($user->id, $creator->user_id);
        $this->assertSame('founder_creator', $creator->username);
        $this->assertSame('discovered', $creator->curation_status);
        $this->assertSame('pending', $creator->safety_status);
    }

    public function test_unavailable_insights_do_not_fan_out_one_request_per_metric(): void
    {
        config()->set('services.instagram.graph_url', 'https://graph.instagram.com');
        config()->set('services.instagram.api_version', 'v25.0');

        $user = User::factory()->create();
        $account = InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => '123',
            'username' => 'founder_creator',
            'access_token' => 'server-side-secret',
            'token_expires_at' => now()->addMonth(),
            'connected_at' => now(),
        ]);

        $insightsRequests = 0;

        Http::fake(function ($request) use (&$insightsRequests) {
            if (str_contains($request->url(), '/insights')) {
                $insightsRequests++;

                return Http::response(['error' => [
                    'message' => 'The metric is not supported for this media product type.',
                    'code' => 100,
                ]], 400);
            }

            if (str_contains($request->url(), '/me/media')) {
                return Http::response(['data' => [[
                    'id' => 'media-1',
                    'media_type' => 'IMAGE',
                    'media_product_type' => 'FEED',
                    'timestamp' => '2026-08-15T10:00:00+0000',
                ]]]);
            }

            return Http::response(['id' => '123', 'username' => 'founder_creator']);
        });

        (new SyncInstagramAccount($account->id))
            ->handle(app(InstagramAuthService::class), app(InstagramApiService::class), app(NicheDetectionService::class));

        // One batched attempt plus a single narrowed retry — never one call per metric.
        $this->assertSame(2, $insightsRequests);
        $this->assertSame([], $account->media()->firstOrFail()->metrics);
        $this->assertSame('completed', $account->refresh()->sync_status);
    }

    public function test_sync_does_not_discover_from_insufficient_evidence_or_keep_placeholder_context(): void
    {
        Bus::fake();
        config()->set('services.openai.api_key');
        config()->set('services.anthropic.api_key');
        config()->set('services.instagram.graph_url', 'https://graph.instagram.com');
        config()->set('services.instagram.api_version', 'v25.0');

        $user = User::factory()->create();
        $account = InstagramAccount::query()->create([
            'user_id' => $user->id,
            'instagram_user_id' => '456',
            'username' => 'sample.creator',
            'access_token' => 'server-side-secret',
            'token_expires_at' => now()->addMonth(),
            'connected_at' => now(),
        ]);
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'niche' => 'Http / Samplecreator',
            'topics' => ['Http', 'Samplecreator', 'Vivatech', '2026'],
            'current_projects' => ['Personal'],
            'goals' => ['Build a personal brand', 'Grow an audience', 'Launch Personal'],
            'content_strengths' => ['Founder stories', 'Personal lessons', 'Behind the scenes'],
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/me/media')) {
                return Http::response(['data' => [[
                    'id' => 'media-weak',
                    'caption' => 'https://example.test @sample.creator VivaTech 2026',
                    'media_type' => 'IMAGE',
                    'media_product_type' => 'FEED',
                    'timestamp' => '2026-08-20T10:00:00+0000',
                ]]]);
            }

            if (str_contains($request->url(), '/insights')) {
                return Http::response(['data' => []]);
            }

            return Http::response([
                'id' => '456',
                'username' => 'sample.creator',
                'name' => 'Sample Creator',
                'media_count' => 1,
            ]);
        });

        (new SyncInstagramAccount($account->id))
            ->handle(app(InstagramAuthService::class), app(InstagramApiService::class), app(NicheDetectionService::class));

        $profile = $user->creatorProfile()->firstOrFail();
        $this->assertNull($profile->niche);
        $this->assertSame([], $profile->topics);
        $this->assertSame([], $profile->current_projects);
        $this->assertSame([], $profile->goals);
        $this->assertSame([], $profile->content_strengths);
        $this->assertSame('insufficient_evidence', $profile->creator_dna['analysis_status']);
        Bus::assertNotDispatched(DiscoverNicheContent::class);
    }
}
