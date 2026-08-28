<?php

namespace Tests\Feature\Discovery;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\Remix;
use App\Models\SavedContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryContentRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_deletes_only_expired_unprotected_content(): void
    {
        $user = User::factory()->create();
        $creator = Creator::query()->create([
            'username' => 'retention', 'display_name' => 'Retention', 'niche' => 'wellness',
            'followers' => 50000, 'average_views' => 10000, 'average_likes' => 1000,
        ]);
        $expired = $this->storePost($creator, 'expired', 91);
        $saved = $this->storePost($creator, 'saved', 120);
        $remixed = $this->storePost($creator, 'remixed', 120);
        $recent = $this->storePost($creator, 'recent', 89);
        SavedContent::query()->create(['user_id' => $user->id, 'content_post_id' => $saved->id]);
        Remix::query()->create([
            'user_id' => $user->id, 'source_content_id' => $remixed->id,
            'format' => 'reel', 'generated_content' => [], 'status' => 'draft',
        ]);

        $this->artisan('personal:prune-discovery-content')->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($saved);
        $this->assertModelExists($remixed);
        $this->assertModelExists($recent);
    }

    public function test_market_cleanup_deletes_only_unprotected_unsupported_discovery_content(): void
    {
        $user = User::factory()->create();
        $unsupported = Creator::query()->create([
            'username' => 'outside-markets', 'display_name' => 'Outside Markets', 'niche' => 'wellness',
            'market' => 'BR', 'followers' => 50000, 'average_views' => 10000, 'average_likes' => 1000,
        ]);
        $allowed = Creator::query()->create([
            'username' => 'inside-markets', 'display_name' => 'Inside Markets', 'niche' => 'wellness',
            'market' => 'FR', 'followers' => 50000, 'average_views' => 10000, 'average_likes' => 1000,
        ]);
        $deleted = $this->storePost($unsupported, 'unsupported', 1);
        $saved = $this->storePost($unsupported, 'unsupported-saved', 1);
        $kept = $this->storePost($allowed, 'allowed', 1);
        SavedContent::query()->create(['user_id' => $user->id, 'content_post_id' => $saved->id]);

        $this->artisan('personal:prune-unsupported-markets --dry-run')->assertSuccessful();
        $this->assertModelExists($deleted);

        $this->artisan('personal:prune-unsupported-markets')->assertSuccessful();

        $this->assertModelMissing($deleted);
        $this->assertModelExists($saved);
        $this->assertModelExists($kept);
        $this->assertSame('inactive', $unsupported->fresh()->curation_status);
        $this->assertSame('stopped', $saved->fresh()->tracking_status);
        $this->assertNull($saved->measured_at);
    }

    private function storePost(Creator $creator, string $hook, int $days): ContentPost
    {
        return ContentPost::query()->create([
            'creator_id' => $creator->id, 'source_url' => "https://instagram.test/{$hook}",
            'platform' => 'instagram', 'format' => 'reel', 'hook' => $hook,
            'caption' => $hook, 'views' => 1000, 'likes' => 100, 'comments' => 10,
            'published_at' => now()->subDays($days),
        ]);
    }
}
