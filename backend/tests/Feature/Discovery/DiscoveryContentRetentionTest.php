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

    public function test_strict_market_cleanup_removes_protected_content_and_inspirations(): void
    {
        $user = User::factory()->create();
        $unsupported = Creator::query()->create([
            'username' => 'strict-outside-markets', 'display_name' => 'Strict Outside Markets', 'niche' => 'wellness',
            'market' => 'AU', 'followers' => 50000, 'average_views' => 10000, 'average_likes' => 1000,
        ]);
        $post = $this->storePost($unsupported, 'strict-unsupported', 1);
        SavedContent::query()->create(['user_id' => $user->id, 'content_post_id' => $post->id]);
        Remix::query()->create([
            'user_id' => $user->id, 'source_content_id' => $post->id,
            'format' => 'reel', 'generated_content' => [], 'status' => 'draft',
        ]);
        $user->inspirationCreators()->attach($unsupported->id);

        $this->artisan('personal:prune-unsupported-markets --including-protected')->assertSuccessful();

        $this->assertModelMissing($post);
        $this->assertModelMissing($unsupported);
        $this->assertSame(0, SavedContent::query()->count());
        $this->assertSame(0, Remix::query()->count());
        $this->assertSame(0, $user->inspirationCreators()->count());
    }

    public function test_market_cleanup_quarantines_unclassified_creators_without_deleting_them(): void
    {
        $creator = Creator::query()->create([
            'username' => 'unclassified-market', 'display_name' => 'Unclassified Market', 'niche' => 'wellness',
            'market' => null, 'followers' => 50000, 'average_views' => 10000, 'average_likes' => 1000,
        ]);
        $post = $this->storePost($creator, 'unclassified', 1);

        $this->artisan('personal:prune-unsupported-markets --including-protected')->assertSuccessful();

        $this->assertModelExists($creator);
        $this->assertModelExists($post);
    }

    public function test_strict_market_cleanup_removes_unclassified_discovery_creators(): void
    {
        $creator = Creator::query()->create([
            'username' => 'strict-unclassified-market', 'display_name' => 'Strict Unclassified Market', 'niche' => 'wellness',
            'market' => null, 'followers' => 50000, 'average_views' => 10000, 'average_likes' => 1000,
        ]);
        $post = $this->storePost($creator, 'strict-unclassified', 1);

        $this->artisan('personal:prune-unsupported-markets --including-protected --including-unclassified')->assertSuccessful();

        $this->assertModelMissing($creator);
        $this->assertModelMissing($post);
    }

    public function test_market_cleanup_redetects_spanish_content_before_strict_removal(): void
    {
        $ambiguousAllowedCreator = Creator::query()->create([
            'username' => 'ambiguous-us-account', 'display_name' => 'Ambiguous US Account', 'niche' => 'business',
            'market' => 'US', 'primary_language' => 'en',
            'followers' => 50000, 'average_views' => 10000, 'average_likes' => 1000,
            'metadata' => ['country_code' => 'BR'],
        ]);
        $ambiguousPost = $this->storePost($ambiguousAllowedCreator, 'ambiguous-post', 1);
        $ambiguousPost->update(['caption' => 'The creator sharing weekly productivity tips']);

        $creator = Creator::query()->create([
            'username' => 'spanish-us-account', 'display_name' => 'Spanish US Account', 'niche' => 'music',
            'market' => 'US', 'primary_language' => 'en',
            'followers' => 50000, 'average_views' => 10000, 'average_likes' => 1000,
            'metadata' => ['country_code' => 'US'],
        ]);
        $post = $this->storePost($creator, 'spanish-post', 1);
        $post->update([
            'caption' => 'Lucy miro al mundo y noto que esta girando. Mi segundo disco disponible este lunes.',
        ]);

        $this->artisan('personal:prune-unsupported-markets --dry-run --redetect --including-protected --including-unclassified')->assertSuccessful();

        $this->assertSame('US', $creator->fresh()->market);
        $this->assertModelExists($post);

        $this->artisan('personal:prune-unsupported-markets --redetect --including-protected --including-unclassified')->assertSuccessful();

        $this->assertModelMissing($creator);
        $this->assertModelMissing($post);
        $this->assertModelExists($ambiguousAllowedCreator);
        $this->assertModelExists($ambiguousPost);
        $this->assertSame('US', $ambiguousAllowedCreator->fresh()->market);
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
