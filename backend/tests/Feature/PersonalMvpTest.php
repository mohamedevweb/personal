<?php

namespace Tests\Feature;

use App\Exceptions\ContentGenerationException;
use App\Jobs\AnalyzeContentPost;
use App\Jobs\GenerateRemix;
use App\Models\ContentPost;
use App\Models\Remix;
use App\Models\SavedContent;
use App\Models\User;
use App\Services\ContentGenerationService;
use App\Services\Discovery\PostInsightService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
            ->assertJsonMissingPath('greeting_name')
            ->assertJsonCount(24, 'items')
            ->assertJsonStructure(['featured_opportunity', 'items' => [[
                'id', 'hook', 'performance_ratio', 'recommendation_score', 'why_recommended', 'creator',
            ]]]);

        $scores = collect($response->json('items'))->pluck('recommendation_score');
        $this->assertSame($scores->sortDesc()->values()->all(), $scores->values()->all());
    }

    public function test_feed_rotation_does_not_repeat_until_the_catalog_is_exhausted(): void
    {
        $first = $this->actingAs($this->user)->getJson('/api/feed')->assertOk();
        $firstIds = collect($first->json('items'))->pluck('id');

        $next = $this->actingAs($this->user)->getJson('/api/feed?'.http_build_query([
            'exclude' => $firstIds->all(),
        ]))->assertOk();
        $nextIds = collect($next->json('items'))->pluck('id');

        $this->assertNotEmpty($nextIds);
        $this->assertSame([], $nextIds->intersect($firstIds)->values()->all());

        $seenIds = $firstIds->concat($nextIds)->unique()->values()->all();
        $this->actingAs($this->user)->getJson('/api/feed?'.http_build_query([
            'exclude' => $seenIds,
        ]))->assertOk()->assertJsonCount(0, 'items');

        $restartedIds = collect(
            $this->actingAs($this->user)->getJson('/api/feed')->assertOk()->json('items'),
        )->pluck('id');
        $this->assertSame($firstIds->all(), $restartedIds->all());
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

    public function test_content_analysis_follows_the_requested_language(): void
    {
        Queue::fake();
        config()->set('services.openai.api_key', null);
        config()->set('services.anthropic.api_key', null);
        $post = ContentPost::query()->firstOrFail();

        $this->actingAs($this->user)
            ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->getJson("/api/content/{$post->id}")
            ->assertOk()
            ->assertJsonPath('content.analysis_status', 'pending')
            ->assertJsonPath('content.hook_analysis', fn (string $analysis): bool => str_starts_with($analysis, "L'accroche"));

        $this->actingAs($this->user)
            ->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->postJson("/api/content/{$post->id}/analysis")
            ->assertAccepted();
        Queue::assertPushed(AnalyzeContentPost::class, fn (AnalyzeContentPost $job): bool => $job->contentPostId === $post->id
            && $job->locale === 'fr'
            && $job->queue === 'analysis');

        (new AnalyzeContentPost($post->id, 'fr'))->handle(app(PostInsightService::class));
        $this->assertDatabaseHas('content_posts', ['id' => $post->id, 'analysis_locale' => 'fr']);

        $this->actingAs($this->user)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->getJson("/api/content/{$post->id}")
            ->assertOk()
            ->assertJsonPath('content.analysis_status', 'pending')
            ->assertJsonPath('content.hook_analysis', fn (string $analysis): bool => str_starts_with($analysis, 'The hook'));

        (new AnalyzeContentPost($post->id, 'en'))->handle(app(PostInsightService::class));
        $this->assertDatabaseHas('content_posts', ['id' => $post->id, 'analysis_locale' => 'en']);

        $this->actingAs($this->user)
            ->withHeader('Accept-Language', 'fr')
            ->getJson("/api/content/{$post->id}")
            ->assertOk()
            ->assertJsonPath('content.hook_analysis', fn (string $analysis): bool => str_starts_with($analysis, "L'accroche"));

        $post->refresh();
        $this->assertSame('en', $post->analysis_locale);
        $this->assertSame(['fr', 'en'], array_keys($post->analysis_translations));
    }

    public function test_french_requests_localize_moment_intelligence_and_mock_drafts(): void
    {
        Queue::fake();
        config()->set('services.openai.api_key', null);
        config()->set('services.anthropic.api_key', null);

        $momentResponse = $this->actingAs($this->user)
            ->withHeader('Accept-Language', 'fr')
            ->postJson('/api/moments', [
                'content' => "J'ai changé de direction après avoir compris ce que mes clients demandaient vraiment.",
                'category' => 'Lesson',
                'happened_at' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('moment.story_score', 7)
            ->assertJsonPath('moment.story_reasons.0', 'personnel et précis')
            ->assertJsonPath('moment.story_reasons.1', 'transformation forte');

        $post = ContentPost::query()->firstOrFail();

        $response = $this->actingAs($this->user)
            ->withHeader('Accept-Language', 'fr')
            ->postJson("/api/content/{$post->id}/remix", [
                'format' => 'carousel',
                'life_moment_id' => $momentResponse->json('moment.id'),
            ])
            ->assertAccepted()
            ->assertJsonPath('remix.status', 'generating')
            ->assertJsonPath('remix.generated_content', []);

        $remixId = $response->json('remix.id');
        (new GenerateRemix($remixId, 'fr'))->handle(
            app(ContentGenerationService::class),
            app(PostInsightService::class),
        );

        $this->assertSame(
            'Une ouverture sous tension qui éveille immédiatement la curiosité',
            Remix::query()->findOrFail($remixId)->generated_content['why_it_works'][0],
        );
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
        Queue::fake();
        $post = ContentPost::query()->firstOrFail();
        $moment = $this->user->moments()->firstOrFail();

        $response = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'carousel',
            'life_moment_id' => $moment->id,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('remix.format', 'carousel')
            ->assertJsonPath('remix.life_moment_id', $moment->id)
            ->assertJsonPath('remix.status', 'generating')
            ->assertJsonPath('remix.generated_content', []);

        $remixId = $response->json('remix.id');
        Queue::assertPushed(GenerateRemix::class, fn (GenerateRemix $job): bool => $job->remixId === $remixId
            && $job->queue === 'remix');

        (new GenerateRemix($remixId, 'en'))->handle(
            app(ContentGenerationService::class),
            app(PostInsightService::class),
        );
        $remix = Remix::query()->findOrFail($remixId);

        $this->assertSame('draft', $remix->status);
        $this->assertCount(6, $remix->generated_content['slides']);
        $this->assertSame($post->hook, $remix->generated_content['original_pattern']);
        $this->assertSame($moment->content, $remix->generated_content['your_context']);
    }

    public function test_remixing_the_same_source_and_format_returns_the_existing_draft(): void
    {
        Queue::fake();
        $post = ContentPost::query()->firstOrFail();
        $moment = $this->user->moments()->firstOrFail();

        $first = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'carousel',
            'life_moment_id' => $moment->id,
        ])->assertAccepted();

        $remix = Remix::query()->findOrFail($first->json('remix.id'));
        $remix->update(['generated_content' => ['slides' => [['id' => 1, 'text' => 'My own words.']]], 'status' => 'draft']);

        $second = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'carousel',
            'life_moment_id' => $moment->id,
        ])->assertAccepted();

        $this->assertSame($remix->id, $second->json('remix.id'));
        $this->assertSame('My own words.', $second->json('remix.generated_content.slides.0.text'));
        $this->assertSame(1, Remix::query()->where('user_id', $this->user->id)->where('format', 'carousel')->count());
        Queue::assertPushed(GenerateRemix::class, 1);
    }

    public function test_each_format_keeps_its_own_draft(): void
    {
        Queue::fake();
        $post = ContentPost::query()->firstOrFail();
        $moment = $this->user->moments()->firstOrFail();

        $carousel = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'carousel',
            'life_moment_id' => $moment->id,
        ])->assertAccepted();

        $reel = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'reel',
            'life_moment_id' => $moment->id,
        ])->assertAccepted();

        $this->assertNotSame($carousel->json('remix.id'), $reel->json('remix.id'));

        $back = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'carousel',
            'life_moment_id' => $moment->id,
        ])->assertAccepted();

        $this->assertSame($carousel->json('remix.id'), $back->json('remix.id'));
    }

    public function test_an_archived_draft_does_not_block_a_fresh_one(): void
    {
        Queue::fake();
        $post = ContentPost::query()->firstOrFail();

        $first = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'caption',
        ])->assertAccepted();

        Remix::query()->findOrFail($first->json('remix.id'))->update(['status' => 'archived']);

        $second = $this->actingAs($this->user)->postJson("/api/content/{$post->id}/remix", [
            'format' => 'caption',
        ])->assertAccepted();

        $this->assertNotSame($first->json('remix.id'), $second->json('remix.id'));
    }

    public function test_drafts_are_returned_for_the_authenticated_user_most_recently_updated_first(): void
    {
        $post = ContentPost::query()->firstOrFail();
        $older = Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $post->id,
            'life_moment_id' => null,
            'format' => 'caption',
            'generated_content' => ['caption' => 'An older draft.'],
            'status' => 'draft',
            'updated_at' => now()->subHour(),
        ]);
        $newer = Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $post->id,
            'life_moment_id' => null,
            'format' => 'reel',
            'generated_content' => ['hook' => 'A newer draft.'],
            'status' => 'ready',
        ]);
        Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $post->id,
            'life_moment_id' => null,
            'format' => 'carousel',
            'generated_content' => [],
            'status' => 'archived',
        ]);
        $otherUser = User::factory()->create();
        Remix::query()->create([
            'user_id' => $otherUser->id,
            'source_content_id' => $post->id,
            'life_moment_id' => null,
            'format' => 'caption',
            'generated_content' => ['caption' => 'Not yours.'],
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/remixes')->assertOk();

        $response->assertJsonCount(2, 'remixes')
            ->assertJsonPath('remixes.0.id', $newer->id)
            ->assertJsonPath('remixes.1.id', $older->id)
            ->assertJsonMissingPath('remixes.0.user_id')
            ->assertJsonStructure(['remixes' => [[
                'id', 'format', 'generated_content', 'status', 'updated_at',
                'source_content' => ['id', 'hook', 'creator' => ['username']],
            ]]]);
    }

    public function test_a_failed_remix_can_be_retried_without_losing_its_selection(): void
    {
        Queue::fake();
        $post = ContentPost::query()->firstOrFail();
        $moment = $this->user->moments()->firstOrFail();
        $remix = Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $post->id,
            'life_moment_id' => $moment->id,
            'format' => 'reel',
            'generated_content' => [],
            'status' => 'failed',
        ]);

        $this->actingAs($this->user)
            ->withHeader('Accept-Language', 'fr')
            ->postJson("/api/remixes/{$remix->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('remix.status', 'generating')
            ->assertJsonPath('remix.format', 'reel')
            ->assertJsonPath('remix.life_moment_id', $moment->id);

        Queue::assertPushed(GenerateRemix::class, fn (GenerateRemix $job): bool => $job->remixId === $remix->id
            && $job->locale === 'fr');

        $generator = \Mockery::mock(ContentGenerationService::class);
        $generator->shouldReceive('generate')
            ->once()
            ->andThrow(new ContentGenerationException('Provider unavailable.'));
        (new GenerateRemix($remix->id, 'fr'))->handle($generator, app(PostInsightService::class));

        $this->assertDatabaseHas('remixes', ['id' => $remix->id, 'status' => 'failed']);
    }

    public function test_a_finished_draft_can_be_rewritten_from_scratch(): void
    {
        Queue::fake();
        $post = ContentPost::query()->firstOrFail();
        $remix = Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $post->id,
            'life_moment_id' => null,
            'format' => 'caption',
            'generated_content' => ['caption' => 'The take I no longer want.'],
            'status' => 'ready',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/remixes/{$remix->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('remix.status', 'generating')
            ->assertJsonPath('remix.generated_content', []);

        Queue::assertPushed(GenerateRemix::class, fn (GenerateRemix $job): bool => $job->remixId === $remix->id);
    }

    public function test_a_draft_being_written_cannot_be_rewritten_twice_at_once(): void
    {
        Queue::fake();
        $post = ContentPost::query()->firstOrFail();
        $remix = Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $post->id,
            'life_moment_id' => null,
            'format' => 'reel',
            'generated_content' => [],
            'status' => 'generating',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/remixes/{$remix->id}/retry")
            ->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_an_abandoned_generating_remix_becomes_retryable(): void
    {
        Queue::fake();
        config()->set('services.content_generation.stale_after_seconds', 180);
        $post = ContentPost::query()->firstOrFail();
        $remix = Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $post->id,
            'life_moment_id' => null,
            'format' => 'reel',
            'generated_content' => [],
            'status' => 'generating',
        ]);
        Remix::query()->whereKey($remix->id)->update([
            'updated_at' => now()->subSeconds(181),
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/remixes/{$remix->id}")
            ->assertOk()
            ->assertJsonPath('remix.status', 'failed');

        $this->actingAs($this->user)
            ->postJson("/api/remixes/{$remix->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('remix.status', 'generating');

        Queue::assertPushed(GenerateRemix::class, fn (GenerateRemix $job): bool => $job->remixId === $remix->id);
    }

    public function test_a_recent_generating_remix_keeps_polling(): void
    {
        config()->set('services.content_generation.stale_after_seconds', 180);
        $post = ContentPost::query()->firstOrFail();
        $remix = Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $post->id,
            'life_moment_id' => null,
            'format' => 'reel',
            'generated_content' => [],
            'status' => 'generating',
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/remixes/{$remix->id}")
            ->assertOk()
            ->assertJsonPath('remix.status', 'generating');
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
            'niche' => 'création de contenu',
            'topics' => ['Creator tools', 'Founder stories'],
            'voice_profile' => "# Creator Voice\n\nDirect, reflective, and specific.",
        ])->assertOk()->assertJsonPath('profile.positioning', 'I build calm tools for independent creators.');

        $this->assertDatabaseHas('creator_profiles', [
            'user_id' => $this->user->id,
            'positioning' => 'I build calm tools for independent creators.',
            'voice_profile' => "# Creator Voice\n\nDirect, reflective, and specific.",
        ]);

        $profile = $this->user->creatorProfile()->firstOrFail();
        $this->assertSame(['Creator tools', 'Founder stories'], $profile->creator_dna['topics']);
        $this->assertSame('manual', $profile->creator_dna['analysis_method']);
        $this->assertEquals(1.0, $profile->creator_dna['confidence']);
        $this->assertSame('personal-branding', $profile->primary_vertical);
        $this->assertNull($profile->discovery_queries);
        $this->assertNull($profile->discovery_refreshed_at);
    }

    public function test_a_voice_profile_has_a_bounded_length(): void
    {
        $this->actingAs($this->user)->patchJson('/api/me/profile', [
            'voice_profile' => str_repeat('a', 12001),
        ])->assertUnprocessable()->assertJsonValidationErrors('voice_profile');
    }

    public function test_demo_personal_memory_uses_a_complete_generic_creator_profile(): void
    {
        $this->assertFalse($this->user->instagramAccount()->exists());

        $this->actingAs($this->user)->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('profile.display_name', 'Créateur Personal')
            ->assertJsonPath('profile.niche', 'Création de contenu et personal branding')
            ->assertJsonPath('profile.audience_description', 'Entrepreneurs, créateurs et indépendants qui veulent développer leur visibilité avec une stratégie éditoriale simple.')
            ->assertJsonPath('profile.positioning', 'Partager une expertise de manière claire, utile et incarnée pour aider une audience à passer à l’action.')
            ->assertJsonCount(4, 'profile.topics')
            ->assertJsonCount(4, 'profile.tone')
            ->assertJsonCount(2, 'profile.current_projects')
            ->assertJsonCount(3, 'profile.goals')
            ->assertJsonCount(3, 'profile.content_strengths')
            ->assertJsonPath('profile.voice_profile', null);
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
            ->assertJsonPath('profile.content_strengths', [])
            ->assertJsonPath('profile.voice_profile', null);
    }
}
