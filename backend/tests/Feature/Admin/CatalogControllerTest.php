<?php

namespace Tests\Feature\Admin;

use App\Jobs\Discovery\AdminCatalogImport;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.catalog_admin_emails' => ['mohamedchettah0208@gmail.com']]);
    }

    public function test_only_the_catalog_admin_can_access_catalog_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['email' => 'other@example.com']));

        $this->getJson('/api/admin/catalog/creators')->assertNotFound();
        $this->getJson('/api/admin/catalog/posts')->assertNotFound();
        $this->getJson('/api/admin/catalog/imports')->assertNotFound();
    }

    public function test_the_admin_can_queue_a_creator_import(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create(['email' => 'mohamedchettah0208@gmail.com']));

        $response = $this->postJson('/api/admin/catalog/imports', [
            'type' => 'creator',
            'url' => 'https://www.instagram.com/studio.food/',
            'vertical' => 'food-cooking',
            'country_code' => 'FR',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('import.status', 'queued')
            ->assertJsonPath('import.creator_username', 'studio.food');
        $this->assertDatabaseHas('admin_catalog_imports', [
            'type' => 'creator',
            'creator_username' => 'studio.food',
            'vertical' => 'food-cooking',
            'country_code' => 'FR',
        ]);
        Queue::assertPushed(AdminCatalogImport::class);
    }

    public function test_a_post_import_requires_an_existing_creator(): void
    {
        Sanctum::actingAs(User::factory()->create(['email' => 'mohamedchettah0208@gmail.com']));

        $this->postJson('/api/admin/catalog/imports', [
            'type' => 'post',
            'url' => 'https://www.instagram.com/p/ABC123/',
            'vertical' => 'food-cooking',
            'country_code' => 'FR',
        ])->assertStatus(422);
    }

    public function test_the_admin_can_search_existing_creators(): void
    {
        Sanctum::actingAs(User::factory()->create(['email' => 'mohamedchettah0208@gmail.com']));
        Creator::query()->create([
            'username' => 'studio.food',
            'display_name' => 'Studio Food',
            'niche' => 'Food',
            'followers' => 10000,
            'average_views' => 20000,
            'average_likes' => 1000,
        ]);

        $this->getJson('/api/admin/catalog/creators?q=studio')
            ->assertOk()
            ->assertJsonPath('items.0.username', 'studio.food');
    }

    public function test_the_admin_can_update_creator_and_post_verticals_and_delete_a_post(): void
    {
        Sanctum::actingAs(User::factory()->create(['email' => 'mohamedchettah0208@gmail.com']));
        $creator = Creator::query()->create([
            'username' => 'studio.food',
            'display_name' => 'Studio Food',
            'niche' => 'Food',
            'followers' => 10000,
            'average_views' => 20000,
            'average_likes' => 1000,
        ]);
        $post = ContentPost::query()->create([
            'creator_id' => $creator->id,
            'format' => 'reel',
            'hook' => 'A recipe hook',
            'caption' => 'A recipe caption',
            'views' => 10000,
            'likes' => 1000,
            'comments' => 100,
            'published_at' => now()->subDay(),
            'performance_ratio' => 1.4,
            'tags' => ['food'],
            'why_it_works' => 'Clear promise',
            'hook_analysis' => 'Direct opening',
            'structure_analysis' => 'Simple steps',
        ]);

        $this->getJson('/api/admin/catalog/posts')
            ->assertOk()
            ->assertJsonPath('items.0.id', $post->id)
            ->assertJsonPath('items.0.vertical', null)
            ->assertJsonPath('items.0.creator.username', 'studio.food');

        $this->patchJson("/api/admin/catalog/creators/{$creator->id}", [
            'vertical' => 'food-cooking',
        ])->assertOk()
            ->assertJsonPath('creator.vertical', 'food-cooking');

        $this->patchJson("/api/admin/catalog/posts/{$post->id}", [
            'vertical' => 'wellness',
        ])->assertOk()
            ->assertJsonPath('post.vertical', 'wellness');

        $this->assertSame('wellness', data_get($post->fresh()->metadata, 'feed_classification.vertical'));

        $this->deleteJson("/api/admin/catalog/posts/{$post->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('content_posts', ['id' => $post->id]);
    }
}
