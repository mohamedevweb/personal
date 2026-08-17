<?php

namespace Tests\Feature;

use App\Jobs\SyncInstagramAccount;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncInstagramAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_stores_normalized_real_media_and_initializes_creator_profile(): void
    {
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
        $job->handle(app(InstagramAuthService::class), app(InstagramApiService::class));

        $account->refresh();
        $this->assertSame('completed', $account->sync_status);
        $this->assertSame(2500, $account->followers_count);
        $this->assertDatabaseHas('instagram_media', [
            'instagram_media_id' => 'media-1',
            'media_product_type' => 'REELS',
            'like_count' => 84,
        ]);
        $this->assertSame(1900, $account->media()->firstOrFail()->metrics['views']);
        $this->assertContains('Saas', $user->creatorProfile()->firstOrFail()->topics);
    }
}
