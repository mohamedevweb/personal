<?php

namespace Tests\Feature\Content;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\InstagramAccount;
use App\Models\Remix;
use App\Models\SavedContent;
use App\Models\User;
use App\Services\Feed\PostRelevance;
use App\Services\Feed\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The feed's contract: a post earns its place by beating the account that
 * published it, not by coming from a large one.
 */
class FeedRankingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests focus on relevance boundaries. The production floor is
        // covered separately, so keep the fixture shelf strict by default.
        config(['services.discovery.minimum_feed_size' => 0]);

        $this->user = User::factory()->create();

        CreatorProfile::query()->create([
            'user_id' => $this->user->id,
            'niche' => 'vegan cooking',
            'topics' => ['vegan', 'meal prep', 'recipes'],
            'primary_vertical' => 'food-cooking',
        ]);
    }

    private function creator(string $username, int $followers, int $baseline): Creator
    {
        return Creator::query()->create([
            'username' => $username,
            'display_name' => $username,
            'niche' => 'food-cooking',
            'niche_topics' => ['vegan', 'recipes'],
            'market' => 'US',
            'followers' => $followers,
            'average_views' => 0,
            'average_likes' => 0,
            'baseline_engagement' => $baseline,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function storePost(Creator $creator, float $outlier, array $attributes = []): ContentPost
    {
        $engagement = (int) round($creator->baseline_engagement * $outlier);

        return ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/'.$creator->username.'-'.$outlier.'/',
            'platform' => 'instagram',
            'format' => 'reel',
            'hook' => $creator->username.' at '.$outlier.'x',
            'caption' => 'A vegan meal prep recipe',
            'views' => $engagement * 10,
            'likes' => $engagement,
            'comments' => 0,
            'published_at' => now()->subDay(),
            'outlier_score' => $outlier,
            'performance_ratio' => $outlier,
            'engagement_rate' => $creator->followers > 0 ? round($engagement / $creator->followers * 100, 3) : 0,
            'measured_at' => now(),
            'tags' => ['vegan', 'mealprep', 'recipes'],
            ...$attributes,
        ]);
    }

    /** @return list<string> */
    private function feedHooks(): array
    {
        return app(RecommendationService::class)
            ->forUser($this->user)
            ->pluck('hook')
            ->all();
    }

    public function test_a_flat_post_from_a_huge_account_never_reaches_the_feed(): void
    {
        // The exact shape that used to dominate: a giant account posting its usual
        // numbers, which outranked everything because the old ratio compared it to
        // the scrape batch rather than to itself.
        $flat = $this->storePost($this->creator('mega.vegan', 2_000_000, 80_000), 0.8);
        $breakout = $this->storePost($this->creator('small.vegan', 20_000, 900), 3.2);

        $hooks = $this->feedHooks();

        $this->assertContains($breakout->hook, $hooks);
        $this->assertNotContains($flat->hook, $hooks);
    }

    public function test_a_spam_account_with_a_handful_of_likes_never_reaches_the_feed(): void
    {
        // Straight from production: a hashtag scrape of reach-bait tags returns
        // accounts with no audience, whose median post gets two likes. Three likes
        // against that is a 1.5x "outlier" — which is exactly how posts with two
        // likes ended up in front of a creator.
        $spam = $this->storePost($this->creator('spam.account', 0, 2), 1.5);
        $real = $this->storePost($this->creator('small.vegan', 20_000, 900), 1.5);

        $hooks = $this->feedHooks();

        $this->assertContains($real->hook, $hooks);
        $this->assertNotContains($spam->hook, $hooks);
    }

    public function test_an_unmeasured_post_is_never_shown(): void
    {
        // Freshly discovered through a hashtag: no baseline, so no evidence. When
        // measurement has not run — or has failed in the queue — the feed shows its
        // empty state rather than falling back to raw scrape output.
        $this->storePost(
            $this->creator('unknown.vegan', 50_000, 0),
            1.0,
            ['outlier_score' => 0, 'performance_ratio' => 0, 'engagement_rate' => 0, 'measured_at' => null, 'published_at' => now()],
        );

        $this->assertSame([], $this->feedHooks());
    }

    public function test_fallback_matching_allows_a_same_vertical_post_without_subject_metadata(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'startup-saas',
            'creator_dna' => [
                'primary_niche' => 'startup-saas',
                'sub_niches' => [],
                'topics' => ['product building'],
            ],
        ]);

        $creator = $this->creator('business.creator', 30_000, 900);
        $creator->update(['niche' => 'business', 'niche_topics' => []]);
        $post = $this->storePost($creator, 1.5, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'business',
                'primary_niche' => null,
                'sub_niches' => [],
                'topics' => [],
            ]],
        ]);

        $relevance = app(PostRelevance::class);

        $this->assertNull($relevance->assess($this->user->creatorProfile->fresh(), $post)['bucket']);
        $this->assertSame(
            PostRelevance::FOR_YOU,
            $relevance->assess($this->user->creatorProfile->fresh(), $post, true)['bucket'],
        );
    }

    public function test_fallback_matching_surfaces_an_adjacent_vertical_in_explore(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'startup-saas',
            'creator_dna' => [
                'primary_niche' => 'startup-saas',
                'sub_niches' => [],
                'topics' => ['product building'],
            ],
        ]);

        $creator = $this->creator('tech.creator', 30_000, 900);
        $creator->update(['niche' => 'tech-ai', 'niche_topics' => []]);
        $post = $this->storePost($creator, 1.5, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'tech-ai',
                'primary_niche' => null,
                'sub_niches' => [],
                'topics' => [],
            ]],
        ]);

        config(['services.discovery.minimum_feed_size' => 1]);
        $sections = app(RecommendationService::class)->sectionsForUser($this->user->fresh());

        $this->assertContains($post->id, $sections['explore_items']->pluck('id'));
    }

    public function test_fallback_matching_allows_an_unshared_adjacent_subject_in_explore(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'startup-saas',
            'creator_dna' => [
                'primary_niche' => 'startup-saas',
                'sub_niches' => [],
                'topics' => ['product building'],
            ],
        ]);

        $creator = $this->creator('ai.creator', 30_000, 900);
        $creator->update(['niche' => 'tech-ai', 'niche_topics' => ['ai agents']]);
        $post = $this->storePost($creator, 1.5, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'tech-ai',
                'primary_niche' => 'ai-agents',
                'sub_niches' => [],
                'topics' => ['ai agents'],
            ]],
        ]);

        $relevance = app(PostRelevance::class);

        $this->assertNull($relevance->assess($this->user->creatorProfile->fresh(), $post)['bucket']);
        $this->assertSame(
            PostRelevance::EXPLORE,
            $relevance->assess($this->user->creatorProfile->fresh(), $post, true)['bucket'],
        );
    }

    public function test_posts_older_than_the_feed_window_are_left_out(): void
    {
        config(['services.discovery.feed_window_days' => 30]);

        $recent = $this->storePost($this->creator('small.vegan', 20_000, 900), 2.4);
        $stale = $this->storePost(
            $this->creator('old.vegan', 20_000, 900),
            9.0,
            ['published_at' => now()->subDays(120)],
        );

        $hooks = $this->feedHooks();

        // A 9× post is still the strongest signal in the table — and still wrong to
        // show, because the niche has moved on since.
        $this->assertContains($recent->hook, $hooks);
        $this->assertNotContains($stale->hook, $hooks);
    }

    public function test_blocked_creators_and_posts_never_reach_the_feed(): void
    {
        $blockedCreator = $this->creator('blocked.creator', 20_000, 900);
        $blockedCreator->update(['safety_status' => 'blocked']);
        $creatorPost = $this->storePost($blockedCreator, 3.0);

        $blockedPost = $this->storePost(
            $this->creator('safe.creator', 20_000, 900),
            3.0,
            ['safety_status' => 'blocked'],
        );

        $hooks = $this->feedHooks();

        $this->assertNotContains($creatorPost->hook, $hooks);
        $this->assertNotContains($blockedPost->hook, $hooks);
    }

    public function test_users_never_receive_an_unrelated_vertical_as_feed_filler(): void
    {
        $offNiche = $this->creator('gym.bro', 20_000, 900);
        $offNiche->update(['niche' => 'sport-fitness', 'niche_topics' => ['powerlifting', 'gym']]);

        $match = $this->storePost($this->creator('small.vegan', 20_000, 900), 2.0);
        $stranger = $this->storePost($offNiche, 2.2, [
            'caption' => 'A strength training workout at the gym',
            'tags' => ['powerlifting', 'gym'],
        ]);

        $otherUser = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $otherUser->id,
            'niche' => 'strength training',
            'topics' => ['powerlifting', 'gym'],
            'primary_vertical' => 'sport-fitness',
        ]);

        $veganFeed = app(RecommendationService::class)->forUser($this->user);
        $strengthFeed = app(RecommendationService::class)->forUser($otherUser);

        $this->assertSame($match->hook, $veganFeed->first()['hook']);
        $this->assertSame($stranger->hook, $strengthFeed->first()['hook']);
        $this->assertNotContains($stranger->hook, $veganFeed->pluck('hook'));
        $this->assertNotContains($match->hook, $strengthFeed->pluck('hook'));
        $this->assertNotSame($veganFeed->pluck('hook')->all(), $strengthFeed->pluck('hook')->all());
        $this->assertFalse($veganFeed->pluck('signals')->flatten()->contains('Great fit for you'));
        $this->assertFalse($veganFeed->pluck('signals')->flatten()->contains('Similar creator'));
    }

    public function test_a_first_time_account_gets_curated_content_before_personal_context_is_ready(): void
    {
        $this->user->creatorProfile()->update([
            'instagram_username' => 'le_bonbon_lyon',
            'niche' => null,
            'topics' => [],
            'primary_vertical' => null,
            'creator_dna' => null,
        ]);
        InstagramAccount::query()->create([
            'user_id' => $this->user->id,
            'instagram_user_id' => 'bonbon-lyon',
            'username' => 'le_bonbon_lyon',
            'access_token' => 'secret',
            'connected_at' => now(),
        ]);

        $tech = $this->creator('swerikcodes', 250_000, 900);
        $tech->update([
            'niche' => 'tech-ai',
            'niche_topics' => ['developer tools', 'software development'],
        ]);
        $post = $this->storePost($tech, 2.0, [
            'caption' => 'The developer tool I use to build faster',
            'tags' => ['developer tools'],
        ]);

        $this->assertContains($post->id, app(RecommendationService::class)->forUser($this->user)->pluck('id'));
    }

    public function test_creator_dna_topics_prioritize_the_closest_creator_inside_the_same_vertical(): void
    {
        $this->user->creatorProfile()->update([
            'creator_dna' => [
                'primary_niche' => 'Vegan cooking',
                'sub_niches' => ['Meal prep'],
                'topics' => ['Vegan recipes', 'Plant protein'],
                'content_pillars' => ['Quick meals'],
            ],
        ]);

        $relevantCreator = $this->creator('plant.prep', 20000, 900);
        $relevantCreator->update(['niche_topics' => ['vegan recipes', 'meal prep', 'plant protein']]);
        $otherCreator = $this->creator('pastry.school', 20000, 900);
        $otherCreator->update(['niche_topics' => ['pastry', 'bread', 'desserts']]);

        $relevant = $this->storePost($relevantCreator, 2.0, ['tags' => ['vegan', 'quick meals']]);
        $strongerButGeneric = $this->storePost($otherCreator, 2.2, [
            'caption' => 'A pastry and bread masterclass',
            'tags' => ['pastry', 'desserts'],
        ]);

        $feed = app(RecommendationService::class)->forUser($this->user);

        $this->assertSame($relevant->hook, $feed->first()['hook']);
        $this->assertFalse($feed->pluck('id')->contains($strongerButGeneric->id));
    }

    public function test_one_creator_cannot_fill_the_personalized_feed(): void
    {
        $dominant = $this->creator('dominant.vegan', 200_000, 900);

        foreach ([3.0, 2.9, 2.8, 2.7, 2.6] as $outlier) {
            $this->storePost($dominant, $outlier);
        }

        $alternativeOne = $this->storePost($this->creator('plant.one', 30_000, 900), 2.0);
        $alternativeTwo = $this->storePost($this->creator('plant.two', 30_000, 900), 1.9);

        $feed = app(RecommendationService::class)->forUser($this->user, 6);

        $this->assertSame(2, $feed->where('creator.username', $dominant->username)->count());
        $this->assertContains($alternativeOne->id, $feed->pluck('id'));
        $this->assertContains($alternativeTwo->id, $feed->pluck('id'));
    }

    public function test_the_creator_limit_survives_feed_pagination(): void
    {
        $dominant = $this->creator('dominant.pages', 200_000, 900);

        foreach ([3.0, 2.9, 2.8, 2.7] as $outlier) {
            $this->storePost($dominant, $outlier);
        }

        $alternative = $this->storePost($this->creator('plant.alternative', 30_000, 900), 2.0);
        $first = app(RecommendationService::class)->forUser($this->user, 2);
        $next = app(RecommendationService::class)->forUser($this->user, 6, $first->pluck('id')->all());

        $this->assertSame(2, $first->where('creator.username', $dominant->username)->count());
        $this->assertSame(0, $next->where('creator.username', $dominant->username)->count());
        $this->assertContains($alternative->id, $next->pluck('id'));
    }

    public function test_a_creator_cannot_appear_in_for_you_and_explore(): void
    {
        $this->user->creatorProfile()->update([
            'niche' => 'Personal branding for founders',
            'topics' => ['startup', 'audience building', 'copywriting'],
            'primary_vertical' => 'personal-branding',
        ]);

        $leila = $this->creator('leilahormozi', 1_600_000, 900);
        $leila->update([
            'niche' => 'personal-branding',
            'niche_topics' => ['founders', 'audience building'],
        ]);
        $forYou = $this->storePost($leila, 2.4, [
            'caption' => 'Personal branding and audience building for founders',
            'tags' => ['personal branding', 'audience building'],
        ]);
        $leilaExplore = $this->storePost($leila, 2.3, [
            'caption' => 'How I build my SaaS startup in public',
            'tags' => ['saas', 'startup'],
        ]);

        $neighbour = $this->creator('another.saas', 80_000, 900);
        $neighbour->update(['niche' => 'tech-ai', 'niche_topics' => ['saas', 'startup']]);
        $otherExplore = $this->storePost($neighbour, 2.0, [
            'caption' => 'Lessons from building a SaaS startup',
            'tags' => ['saas', 'startup'],
        ]);

        $sections = app(RecommendationService::class)->sectionsForUser($this->user->fresh());

        $this->assertContains($forYou->id, $sections['items']->pluck('id'));
        $this->assertNotContains($leilaExplore->id, $sections['explore_items']->pluck('id'));
        $this->assertContains($otherExplore->id, $sections['explore_items']->pluck('id'));
        $this->assertSame([], $sections['items']->pluck('creator.username')
            ->intersect($sections['explore_items']->pluck('creator.username'))
            ->values()
            ->all());
    }

    public function test_startup_profile_gets_only_startup_content(): void
    {
        $this->user->creatorProfile()->update([
            'niche' => 'Early-stage tech entrepreneurship',
            'topics' => ['SaaS startup building', 'Product design workflow', 'Development work and tools'],
            // This is the legacy classification held by the production account.
            'primary_vertical' => 'personal-branding',
        ]);

        $saasCreator = $this->creator('saas.builder', 30000, 900);
        $saasCreator->update(['niche' => 'tech-ai', 'niche_topics' => ['SaaS', 'startup', 'product building']]);
        $saas = $this->storePost($saasCreator, 1.8, [
            'caption' => 'How I build my SaaS in public as an early-stage founder',
            'tags' => ['saas', 'startup'],
        ]);

        $consumerTechCreator = $this->creator('gadget.reviews', 50000, 900);
        $consumerTechCreator->update(['niche' => 'tech-ai', 'niche_topics' => ['smartphones', 'gadgets']]);
        $consumerTech = $this->storePost($consumerTechCreator, 5.0, [
            'caption' => 'Mon test du dernier smartphone haut de gamme',
            'tags' => ['smartphone', 'gadgets'],
        ]);

        $businessCreator = $this->creator('founder.stories', 30000, 900);
        $businessCreator->update(['niche' => 'personal-branding', 'niche_topics' => ['founders', 'entrepreneurship']]);
        $adjacent = $this->storePost($businessCreator, 2.1, [
            'caption' => 'Three lessons from founders growing their audience',
            'tags' => ['founders'],
        ]);

        foreach ([
            ['sport-fitness', 'A strength training workout at the gym'],
            ['food-cooking', 'My fastest pasta recipe'],
            ['wellness', 'A mindfulness routine for better sleep'],
        ] as [$vertical, $caption]) {
            $creator = $this->creator(str_replace('-', '.', $vertical), 50000, 900);
            $creator->update(['niche' => $vertical, 'niche_topics' => [$vertical]]);
            $this->storePost($creator, 5.0, ['caption' => $caption, 'tags' => []]);
        }

        $sections = app(RecommendationService::class)->sectionsForUser($this->user);

        $this->assertContains($adjacent->id, $sections['items']->pluck('id'));
        $this->assertContains($saas->id, $sections['explore_items']->pluck('id'));
        $this->assertNotContains($consumerTech->id, $sections['items']->pluck('id'));
        $this->assertNotContains($consumerTech->id, $sections['explore_items']->pluck('id'));
        $this->assertNotContains($adjacent->id, $sections['explore_items']->pluck('id'));
        $this->assertTrue($sections['items']->every(
            fn (array $item): bool => ! in_array($item['creator']['niche'], ['sport-fitness', 'food-cooking', 'wellness'], true),
        ));

        $this->actingAs($this->user)
            ->getJson('/api/feed')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonFragment(['id' => $adjacent->id]);
    }

    public function test_transcript_and_carousel_text_can_change_post_eligibility(): void
    {
        $creator = $this->creator('mixed.creator', 30000, 900);
        $offTopic = $this->storePost($creator, 3.0, [
            'caption' => 'A day in my life',
            'tags' => [],
            'transcript' => 'My strength training workout at the gym',
        ]);
        $relevant = $this->storePost($creator, 1.8, [
            'caption' => 'Swipe for the full method',
            'tags' => [],
            'carousel_analysis' => ['slides' => [
                ['position' => 1, 'text' => 'Vegan meal prep recipes', 'role' => 'hook'],
            ]],
        ]);

        $feed = app(RecommendationService::class)->forUser($this->user);

        $this->assertContains($relevant->id, $feed->pluck('id'));
        $this->assertNotContains($offTopic->id, $feed->pluck('id'));
    }

    public function test_dismissing_a_creator_removes_their_other_posts(): void
    {
        $creator = $this->creator('not.for.me', 30000, 900);
        $dismissed = $this->storePost($creator, 2.0);
        $other = $this->storePost($creator, 1.9, ['source_url' => 'https://instagram.test/not-for-me/other']);
        $alternative = $this->storePost($this->creator('keep.this', 30000, 900), 1.8);

        $this->actingAs($this->user)
            ->postJson("/api/content/{$dismissed->id}/dismiss", ['reason' => 'creator'])
            ->assertOk();

        $feed = app(RecommendationService::class)->forUser($this->user);

        $this->assertNotContains($other->id, $feed->pluck('id'));
        $this->assertContains($alternative->id, $feed->pluck('id'));
        $this->assertDatabaseHas('dismissed_content', [
            'user_id' => $this->user->id,
            'content_post_id' => $dismissed->id,
            'reason' => 'creator',
        ]);
    }

    public function test_saves_and_remixes_boost_similar_future_posts(): void
    {
        $preferredCreator = $this->creator('preferred.creator', 30000, 900);
        $source = $this->storePost($preferredCreator, 1.7, ['published_at' => now()->subDays(40)]);
        $preferred = $this->storePost($preferredCreator, 1.8, ['source_url' => 'https://instagram.test/preferred/current']);
        $rival = $this->storePost($this->creator('slightly.stronger', 30000, 900), 1.9);
        SavedContent::query()->create(['user_id' => $this->user->id, 'content_post_id' => $source->id]);
        Remix::query()->create([
            'user_id' => $this->user->id,
            'source_content_id' => $source->id,
            'format' => 'caption',
            'generated_content' => [],
            'status' => 'draft',
        ]);

        $feed = app(RecommendationService::class)->forUser($this->user);

        $this->assertSame($preferred->id, $feed->first()['id']);
        $this->assertContains($rival->id, $feed->pluck('id'));
    }

    /** A broad vertical never overrides a precise topic mismatch. */
    public function test_a_post_in_the_members_vertical_is_rejected_without_a_shared_cluster(): void
    {
        config(['services.discovery.personalization.minimum_affinity' => 0.99]);
        $this->user->creatorProfile()->update([
            'creator_dna' => [
                'primary_niche' => 'Vegan cooking',
                'sub_niches' => ['Meal prep'],
                'topics' => ['Vegan recipes', 'Plant protein', 'Batch cooking'],
                'content_pillars' => ['Quick weeknight meals'],
            ],
        ]);

        // Same broad vertical, nothing else in common: barbecue against a vegan
        // meal prep profile. The post must not become a For You filler item.
        $distant = $this->creator('bbq.school', 20_000, 900);
        $distant->update([
            'niche' => 'Barbecue et grillades',
            'niche_topics' => ['barbecue', 'grillades', 'viande'],
            'primary_vertical' => 'food-cooking',
        ]);
        $post = $this->storePost($distant, 2.0, [
            'caption' => 'Ma cuisine au barbecue: boeuf marine et feu de bois',
            'tags' => ['barbecue', 'grillades'],
        ]);

        $verdict = app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post);

        $this->assertNull($verdict['bucket']);
        $this->assertNull($verdict['content_vertical']);
        $this->assertNotContains($post->id, app(RecommendationService::class)->forUser($this->user)->pluck('id'));
    }

    /** Shared clusters keep both posts eligible, then affinity orders them. */
    public function test_between_two_posts_of_the_same_vertical_affinity_decides_the_order(): void
    {
        $this->user->creatorProfile()->update([
            'creator_dna' => [
                'primary_niche' => 'Vegan cooking',
                'sub_niches' => ['Meal prep'],
                'topics' => ['Vegan recipes', 'Plant protein'],
                'content_pillars' => ['Quick meals'],
            ],
        ]);

        $close = $this->creator('plant.prep', 20_000, 900);
        $close->update(['niche' => 'vegan cooking', 'niche_topics' => ['vegan recipes', 'meal prep', 'plant protein']]);
        $far = $this->creator('pastry.school', 20_000, 900);
        $far->update(['niche' => 'baking', 'niche_topics' => ['pastry', 'bread', 'desserts']]);

        $closePost = $this->storePost($close, 2.0, ['caption' => 'Une recette vegan de meal prep', 'tags' => ['vegan', 'meal prep']]);
        $farPost = $this->storePost($far, 2.2, ['caption' => 'Une recette de patisserie', 'tags' => ['patisserie']]);

        $relevance = app(PostRelevance::class);
        $this->assertSame(PostRelevance::FOR_YOU, $relevance->assess($this->user->creatorProfile, $closePost)['bucket']);
        $this->assertNull($relevance->assess($this->user->creatorProfile, $farPost)['bucket']);

        $feed = app(RecommendationService::class)->forUser($this->user);
        $ids = $feed->pluck('id')->all();

        $this->assertContains($closePost->id, $ids);
        $this->assertNotContains($farPost->id, $ids);
        $this->assertSame($closePost->id, $feed->first()['id']);
    }

    /** Case C — an adjacent vertical with nothing in common is still refused. */
    public function test_an_adjacent_vertical_without_any_common_ground_is_refused(): void
    {
        $this->user->creatorProfile()->update([
            'niche' => 'Personal branding',
            'topics' => ['audience building', 'copywriting'],
            'primary_vertical' => 'personal-branding',
        ]);

        $stranger = $this->creator('gadget.reviews', 30_000, 900);
        $stranger->update(['niche' => 'tech-ai', 'niche_topics' => ['smartphone', 'gadgets']]);
        $post = $this->storePost($stranger, 2.4, [
            'caption' => 'Mon setup high-tech: le smartphone que je garde',
            'tags' => ['gadgets'],
        ]);

        $verdict = app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post);

        $this->assertNull($verdict['bucket']);
        $this->assertNotContains($post->id, app(RecommendationService::class)->forUser($this->user)->pluck('id'));
    }

    /** Adjacent verticals stay out once the member has a primary vertical. */
    public function test_an_adjacent_vertical_sharing_a_cluster_stays_out_of_the_feed(): void
    {
        config(['services.discovery.minimum_feed_size' => 1]);
        $this->user->creatorProfile()->update([
            'niche' => 'Personal branding for founders',
            'topics' => ['startup', 'audience building'],
            'primary_vertical' => 'personal-branding',
        ]);

        $neighbour = $this->creator('saas.builder', 30_000, 900);
        $neighbour->update(['niche' => 'tech-ai', 'niche_topics' => ['saas', 'startup']]);
        $post = $this->storePost($neighbour, 2.4, [
            'caption' => 'Comment je construis mon saas en public',
            'tags' => ['saas', 'startup'],
        ]);

        $sections = app(RecommendationService::class)->sectionsForUser($this->user->fresh());

        $this->assertSame(
            PostRelevance::EXPLORE,
            app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post)['bucket'],
        );
        $this->assertNotContains($post->id, $sections['items']->pluck('id'));
        $this->assertContains($post->id, $sections['explore_items']->pluck('id'));
    }

    /**
     * Case E — the human label and the canonical vertical are two different
     * things. A creator described as "Entrepreneurship / SaaS" is the same
     * universe as a member classified `tech-ai`, and the feed has to see it.
     */
    public function test_a_free_text_niche_is_matched_on_its_canonical_vertical(): void
    {
        $this->user->creatorProfile()->update([
            'niche' => 'SaaS and AI tooling',
            'topics' => ['saas', 'ai'],
            'primary_vertical' => 'tech-ai',
        ]);

        $creator = $this->creator('founder.saas', 30_000, 900);
        $creator->update([
            'niche' => 'Entrepreneurship / SaaS',
            'niche_topics' => ['saas', 'startup'],
            'primary_vertical' => 'tech-ai',
        ]);
        $post = $this->storePost($creator, 2.0, ['caption' => 'Ce que mon saas m a appris', 'tags' => ['saas']]);

        $this->assertSame('tech-ai', $creator->fresh()->primary_vertical);
        $this->assertNotSame($creator->fresh()->primary_vertical, $creator->fresh()->niche);
        $this->assertSame(
            PostRelevance::FOR_YOU,
            app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post)['bucket'],
        );
        $this->assertContains($post->id, app(RecommendationService::class)->forUser($this->user->fresh())->pluck('id'));
    }

    /**
     * Case F — the gate opened for the member's own vertical, and for nothing
     * else: unrelated verticals stay out of For You even when they perform far
     * better, whether their subject is readable from the post or only from the
     * account behind it.
     */
    public function test_unrelated_verticals_still_never_reach_for_you(): void
    {
        $match = $this->storePost($this->creator('small.vegan', 20_000, 900), 1.4);

        foreach ([
            ['gym.rats', 'sport-fitness', ['musculation', 'workout'], 'Une seance de musculation'],
            ['mind.calm', 'wellness', ['meditation', 'sommeil'], 'Ma routine de meditation'],
            ['gadget.desk', 'tech-ai', ['gadgets', 'setup'], 'Mon setup high-tech'],
        ] as [$username, $niche, $topics, $caption]) {
            $creator = $this->creator($username, 500_000, 900);
            $creator->update(['niche' => $niche, 'niche_topics' => $topics]);
            $this->storePost($creator, 6.0, ['caption' => $caption, 'tags' => $topics]);
        }

        // The same thing again, with a post nothing can be read from: only the
        // account says what it is, and it says the wrong vertical.
        $silent = $this->creator('silent.gym', 500_000, 900);
        $silent->update(['niche' => 'strength coaching', 'niche_topics' => ['musculation']]);
        $this->storePost($silent, 6.0, ['caption' => 'Jour 12', 'tags' => []]);

        $items = app(RecommendationService::class)->forUser($this->user);

        $this->assertSame([$match->id], $items->pluck('id')->all());
    }

    public function test_same_vertical_with_a_different_primary_niche_is_rejected(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'saas',
            'topics' => ['product building'],
            'creator_dna' => [
                'primary_niche' => 'saas',
                'sub_niches' => ['building-in-public'],
                'topics' => ['product building'],
                'avoid_topics' => [],
            ],
        ]);

        $creator = $this->creator('property.creator', 30_000, 900);
        $creator->update(['niche' => 'business', 'niche_topics' => ['real estate']]);
        $post = $this->storePost($creator, 10.0, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'business',
                'primary_niche' => 'real-estate',
                'sub_niches' => [],
                'topics' => ['real-estate'],
            ]],
        ]);

        $verdict = app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post);

        $this->assertNull($verdict['bucket']);
    }

    public function test_one_shared_sub_niche_is_enough_for_for_you(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'saas',
            'creator_dna' => [
                'primary_niche' => 'saas',
                'sub_niches' => ['bootstrapping', 'building-in-public', 'solopreneurship'],
                'topics' => ['product building', 'growth'],
            ],
        ]);

        $creator = $this->creator('bootstrapped.creator', 30_000, 900);
        $creator->update(['niche' => 'business', 'niche_topics' => ['bootstrapping']]);
        $post = $this->storePost($creator, 2.0, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'business',
                'primary_niche' => 'entrepreneurship',
                'sub_niches' => ['bootstrapping'],
                'topics' => ['bootstrapping'],
            ]],
        ]);

        $this->assertSame(
            PostRelevance::FOR_YOU,
            app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post)['bucket'],
        );
    }

    public function test_adjacent_vertical_requires_a_shared_concept_and_goes_to_explore(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'saas',
            'creator_dna' => [
                'primary_niche' => 'saas',
                'sub_niches' => ['ai-entrepreneurship'],
                'topics' => ['product building'],
            ],
        ]);

        $creator = $this->creator('ai.tools', 30_000, 900);
        $creator->update(['niche' => 'tech-ai', 'niche_topics' => ['ai agents']]);
        $post = $this->storePost($creator, 2.0, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'tech-ai',
                'primary_niche' => 'ai-agents',
                'sub_niches' => ['ai-entrepreneurship'],
                'topics' => ['ai agents'],
            ]],
        ]);

        $this->assertSame(
            PostRelevance::EXPLORE,
            app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post)['bucket'],
        );
    }

    public function test_an_avoid_topic_is_a_hard_relevance_boundary(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'saas',
            'creator_dna' => [
                'primary_niche' => 'saas',
                'sub_niches' => [],
                'topics' => ['product building'],
                'avoid_topics' => ['crypto-trading'],
            ],
        ]);

        $creator = $this->creator('crypto.creator', 30_000, 900);
        $creator->update(['niche' => 'business', 'niche_topics' => ['crypto trading']]);
        $post = $this->storePost($creator, 10.0, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'business',
                'primary_niche' => 'crypto-trading',
                'sub_niches' => [],
                'topics' => ['crypto-trading'],
            ]],
        ]);

        $verdict = app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post);

        $this->assertNull($verdict['bucket']);
        $this->assertSame(['crypto-trading'], $verdict['matched_avoid_topics']);
    }

    public function test_creator_fit_cannot_rescue_a_post_with_an_unrelated_subject(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'saas',
            'creator_dna' => [
                'primary_niche' => 'saas',
                'sub_niches' => ['building-in-public'],
                'topics' => ['product building'],
            ],
        ]);

        $creator = $this->creator('saas.travels', 30_000, 900);
        $creator->update(['niche' => 'business', 'niche_topics' => ['saas', 'building-in-public']]);
        $post = $this->storePost($creator, 10.0, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'travel',
                'primary_niche' => 'travel',
                'sub_niches' => [],
                'topics' => ['travel'],
            ]],
        ]);

        $verdict = app(PostRelevance::class)->assess($this->user->creatorProfile->fresh(), $post);

        $this->assertNull($verdict['bucket']);
        $this->assertGreaterThan(0, $verdict['creator_affinity']);
    }

    public function test_ranking_only_compares_posts_after_the_relevance_gate(): void
    {
        $this->user->creatorProfile()->update([
            'primary_vertical' => 'business',
            'niche' => 'saas',
            'creator_dna' => [
                'primary_niche' => 'saas',
                'sub_niches' => ['bootstrapping'],
                'topics' => ['product building'],
            ],
        ]);

        $relevantCreator = $this->creator('saas.breakout', 30_000, 900);
        $relevant = $this->storePost($relevantCreator, 2.5, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'business',
                'primary_niche' => 'saas',
                'sub_niches' => ['bootstrapping'],
                'topics' => ['product building'],
            ]],
        ]);
        $irrelevantCreator = $this->creator('property.breakout', 30_000, 900);
        $irrelevant = $this->storePost($irrelevantCreator, 10.0, [
            'metadata' => ['feed_classification' => [
                'vertical' => 'business',
                'primary_niche' => 'real-estate',
                'sub_niches' => [],
                'topics' => ['real-estate'],
            ]],
        ]);

        $feed = app(RecommendationService::class)->forUser($this->user->fresh());

        $this->assertContains($relevant->id, $feed->pluck('id'));
        $this->assertNotContains($irrelevant->id, $feed->pluck('id'));
    }
}
