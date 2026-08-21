<?php

namespace Tests\Feature;

use App\Models\ContentPost;
use App\Models\SavedContent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonalMvpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->where('email', 'creator@personal.local')->firstOrFail();
    }

    public function test_feed_returns_ranked_global_content(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/feed');

        $response->assertOk()
            ->assertJsonPath('greeting_name', 'Mohamed')
            ->assertJsonCount(12, 'items')
            ->assertJsonStructure(['featured_opportunity', 'items' => [[
                'id', 'hook', 'performance_ratio', 'recommendation_score', 'why_recommended', 'creator',
            ]]]);

        $scores = collect($response->json('items'))->pluck('recommendation_score');
        $this->assertSame($scores->sortDesc()->values()->all(), $scores->values()->all());
    }

    public function test_the_feed_query_count_does_not_grow_with_the_catalog(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($this->user)->getJson('/api/feed')->assertOk();

        // The seeder ships 40 posts; a per-post lookup would put this in the forties.
        $this->assertLessThan(12, $queries, "The feed issued {$queries} queries.");
    }

    public function test_saved_content_is_returned_most_recently_saved_first(): void
    {
        $posts = ContentPost::query()->orderBy('id')->take(3)->get();

        foreach ($posts as $index => $post) {
            SavedContent::query()->create([
                'user_id' => $this->user->id,
                'content_post_id' => $post->id,
                'created_at' => now()->subMinutes(10 - $index),
                'updated_at' => now()->subMinutes(10 - $index),
            ]);
        }

        $response = $this->actingAs($this->user)->getJson('/api/saved')->assertOk();

        $expected = $posts->reverse()->pluck('id')->values()->all();
        $returned = collect($response->json('items'))->pluck('id')
            ->intersect($expected)->values()->all();

        $this->assertSame($expected, $returned);
        $this->assertTrue(collect($response->json('items'))->every(fn (array $item) => $item['is_saved']));
    }

    public function test_content_can_be_saved_and_dismissed(): void
    {
        $post = ContentPost::query()->firstOrFail();

        $this->actingAs($this->user)->postJson("/api/content/{$post->id}/save")
            ->assertOk()->assertJson(['saved' => true]);
        $this->assertDatabaseHas('saved_content', ['user_id' => $this->user->id, 'content_post_id' => $post->id]);

        $this->actingAs($this->user)->postJson("/api/content/{$post->id}/dismiss")
            ->assertOk()->assertJson(['dismissed' => true]);
        $this->assertDatabaseHas('dismissed_content', ['user_id' => $this->user->id, 'content_post_id' => $post->id]);
    }

    public function test_new_moment_gets_story_intelligence_and_an_opportunity(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/moments', [
            'content' => 'I realized our best marketing idea was hiding in a customer complaint after three months of building.',
            'category' => 'Lesson',
            'happened_at' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('moment.story_score', 7);
        $this->assertDatabaseHas('content_opportunities', [
            'user_id' => $this->user->id,
            'life_moment_id' => $response->json('moment.id'),
            'origin' => 'life_moment',
        ]);
    }

    public function test_remix_uses_source_pattern_and_personal_moment(): void
    {
        $post = ContentPost::query()->firstOrFail();
        $moment = $this->user->moments()->firstOrFail();

        $response = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'carousel',
            'life_moment_id' => $moment->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('remix.format', 'carousel')
            ->assertJsonPath('remix.life_moment_id', $moment->id)
            ->assertJsonCount(6, 'remix.generated_content.slides');
        $this->assertSame($post->hook, $response->json('remix.generated_content.original_pattern'));
        $this->assertSame($moment->content, $response->json('remix.generated_content.your_context'));
    }

    public function test_personal_memory_is_editable(): void
    {
        $this->user->creatorProfile->update([
            'creator_dna' => ['primary_niche' => 'old niche', 'topics' => ['old topic']],
            'discovery_queries' => ['old niche creator'],
            'discovery_hashtags' => ['oldniche'],
            'discovery_refreshed_at' => now(),
        ]);

        $this->actingAs($this->user)->patchJson('/api/me/profile', [
            'positioning' => 'I build calm tools for independent creators.',
            'topics' => ['Creator tools', 'Founder stories'],
        ])->assertOk()->assertJsonPath('profile.positioning', 'I build calm tools for independent creators.');

        $this->assertDatabaseHas('creator_profiles', [
            'user_id' => $this->user->id,
            'positioning' => 'I build calm tools for independent creators.',
        ]);

        $profile = $this->user->creatorProfile()->firstOrFail();
        $this->assertSame(['Creator tools', 'Founder stories'], $profile->creator_dna['topics']);
        $this->assertSame('manual', $profile->creator_dna['analysis_method']);
        $this->assertEquals(1.0, $profile->creator_dna['confidence']);
        $this->assertNull($profile->discovery_queries);
        $this->assertNull($profile->discovery_refreshed_at);
    }

    public function test_a_new_personal_memory_does_not_claim_placeholder_insights(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('profile.niche', null)
            ->assertJsonPath('profile.audience_description', null)
            ->assertJsonPath('profile.positioning', null)
            ->assertJsonPath('profile.topics', [])
            ->assertJsonPath('profile.tone', [])
            ->assertJsonPath('profile.current_projects', [])
            ->assertJsonPath('profile.goals', [])
            ->assertJsonPath('profile.content_strengths', []);
    }
}
