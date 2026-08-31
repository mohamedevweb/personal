<?php

namespace Tests\Feature\Discovery;

use App\Models\ContentPost;
use App\Models\Creator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassifyFeedPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_structured_classification_for_unclassified_posts(): void
    {
        $creator = Creator::query()->create([
            'username' => 'founder.fr',
            'display_name' => 'Founder FR',
            'niche' => 'tech-ai',
            'primary_vertical' => 'tech-ai',
            'followers' => 10_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'curation_status' => 'approved',
            'safety_status' => 'allowed',
        ]);
        $post = ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/classify/',
            'platform' => 'instagram',
            'format' => 'image',
            'hook' => 'Build a SaaS product',
            'caption' => 'How I build a SaaS product with a small team',
            'views' => 20_000,
            'likes' => 1_000,
            'comments' => 50,
            'published_at' => now(),
            'metadata' => [],
        ]);

        $this->artisan('personal:classify-feed-posts')
            ->expectsOutput('Classification pass: 1 checked, 1 classified, 0 unclassified.')
            ->assertSuccessful();

        $post->refresh();

        $this->assertSame('tech-ai', data_get($post->metadata, 'feed_classification.vertical'));
        $this->assertIsArray(data_get($post->metadata, 'feed_classification.topics'));
    }

    public function test_it_does_not_overwrite_an_existing_structured_classification(): void
    {
        $creator = Creator::query()->create([
            'username' => 'classified.creator',
            'display_name' => 'Classified Creator',
            'niche' => 'food-cooking',
            'followers' => 10_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
        ]);
        $post = ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/classified/',
            'platform' => 'instagram',
            'format' => 'image',
            'hook' => 'Existing classification',
            'caption' => 'A recipe',
            'views' => 20_000,
            'likes' => 1_000,
            'comments' => 50,
            'published_at' => now(),
            'metadata' => [
                'feed_classification' => [
                    'vertical' => 'food-cooking',
                    'primary_niche' => 'recipes',
                    'sub_niches' => [],
                    'topics' => ['recipes'],
                    'avoid_topics' => [],
                ],
            ],
        ]);

        $this->artisan('personal:classify-feed-posts')->assertSuccessful();

        $this->assertSame('food-cooking', data_get($post->fresh()->metadata, 'feed_classification.vertical'));
    }

    public function test_it_uses_the_validated_creator_vertical_when_post_text_has_no_matching_subject(): void
    {
        $creator = Creator::query()->create([
            'username' => 'garyvee.test',
            'display_name' => 'Gary Vee Test',
            'niche' => 'personal-branding',
            'primary_vertical' => 'personal-branding',
            'followers' => 10_000,
            'average_views' => 10_000,
            'average_likes' => 1_000,
            'curation_status' => 'approved',
            'safety_status' => 'allowed',
        ]);
        ContentPost::query()->create([
            'creator_id' => $creator->id,
            'source_url' => 'https://www.instagram.com/p/creator-fallback/',
            'platform' => 'instagram',
            'format' => 'image',
            'hook' => 'Stop being scared',
            'caption' => 'Stop being scared. No one cares if you miss.',
            'views' => 20_000,
            'likes' => 1_000,
            'comments' => 50,
            'published_at' => now(),
            'metadata' => [],
        ]);

        $this->artisan('personal:classify-feed-posts')
            ->expectsOutput('Classification pass: 1 checked, 1 classified, 0 unclassified.')
            ->assertSuccessful();

        $classification = data_get(ContentPost::query()->latest('id')->first()->metadata, 'feed_classification');

        $this->assertSame('personal-branding', $classification['vertical']);
        $this->assertNull($classification['primary_niche']);
    }
}
