<?php

namespace Tests\Feature\Content;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\User;
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

    public function test_users_receive_their_primary_vertical_before_stronger_global_posts(): void
    {
        $offNiche = $this->creator('gym.bro', 20_000, 900);
        $offNiche->update(['niche' => 'sport-fitness', 'niche_topics' => ['powerlifting', 'gym']]);

        $match = $this->storePost($this->creator('small.vegan', 20_000, 900), 2.0);
        $stranger = $this->storePost($offNiche, 2.2, ['tags' => ['powerlifting', 'gym']]);

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
        $this->assertNotSame($veganFeed->pluck('hook')->all(), $strengthFeed->pluck('hook')->all());
        $this->assertFalse($veganFeed->pluck('signals')->flatten()->contains('Great fit for you'));
        $this->assertFalse($veganFeed->pluck('signals')->flatten()->contains('Similar creator'));
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
        $strongerButGeneric = $this->storePost($otherCreator, 2.2, ['tags' => ['pastry', 'desserts']]);

        $feed = app(RecommendationService::class)->forUser($this->user);

        $this->assertSame($relevant->hook, $feed->first()['hook']);
        $this->assertGreaterThan(
            $feed->firstWhere('id', $strongerButGeneric->id)['creator_fit_score'],
            $feed->firstWhere('id', $relevant->id)['creator_fit_score'],
        );
    }
}
