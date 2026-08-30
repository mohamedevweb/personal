<?php

namespace Tests\Feature\Content;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\LifeMoment;
use App\Models\User;

/**
 * A creator with a profile, one benchmark post to borrow structure from, and one
 * life moment to ground the draft in. Shared by every generation driver's tests so
 * they are comparing the same inputs.
 */
trait DraftsContentFixtures
{
    /** @return array{0: User, 1: ContentPost, 2: LifeMoment} */
    private function draftFixtures(): array
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'niche' => 'Entrepreneurship / SaaS',
            'positioning' => 'Building products for independent creators.',
            'audience_description' => 'Founders and creators',
            'topics' => ['Creator economy'],
            'tone' => ['Direct'],
            'voice_profile' => "# Creator Voice\n\nShort sentences. Concrete examples before conclusions.",
        ]);

        $creator = Creator::query()->create([
            'username' => 'benchmark',
            'display_name' => 'Benchmark Creator',
            'niche' => 'SaaS',
            'followers' => 100000,
            'average_views' => 50000,
            'average_likes' => 2000,
        ]);

        $post = ContentPost::query()->create([
            'creator_id' => $creator->id,
            'format' => 'Carousel',
            'hook' => 'I spent 3 years building the wrong kind of business.',
            'caption' => 'The long version of the same story.',
            'views' => 400000,
            'likes' => 18000,
            'comments' => 400,
            'published_at' => now()->subDay(),
            'performance_ratio' => 8.4,
            'tags' => ['SaaS'],
            'why_it_works' => 'Tension first, lesson last.',
            'hook_analysis' => 'A first-person admission.',
            'structure_analysis' => 'Problem, context, turning point, lesson.',
            // Three slides, already read: a carousel draft is written against
            // them one for one, so the number and the plan are the fixture.
            'media_urls' => [
                'https://cdn.example.com/slide-1.jpg',
                'https://cdn.example.com/slide-2.jpg',
                'https://cdn.example.com/slide-3.jpg',
            ],
            'carousel_analysis_status' => 'done',
            'carousel_analysis' => ['slides' => [
                ['position' => 1, 'text' => 'I spent 3 years building the wrong business.', 'role' => 'Hook', 'visual_description' => 'Portrait, text across the top third.'],
                ['position' => 2, 'text' => 'Here is what the numbers said.', 'role' => 'Evidence', 'visual_description' => 'Screenshot of a dashboard.'],
                ['position' => 3, 'text' => 'And here is what I do now.', 'role' => 'Lesson', 'visual_description' => 'Desk photo shot from above.'],
            ]],
        ]);

        $moment = LifeMoment::query()->create([
            'user_id' => $user->id,
            'content' => 'I decided to pivot my creator partnership product after four months of research.',
            'category' => 'Failure',
        ]);

        return [$user, $post, $moment];
    }
}
