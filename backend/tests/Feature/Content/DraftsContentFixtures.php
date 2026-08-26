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
        ]);

        $moment = LifeMoment::query()->create([
            'user_id' => $user->id,
            'content' => 'I decided to pivot my creator partnership product after four months of research.',
            'category' => 'Failure',
            'story_score' => 9,
            'story_reasons' => ['strong transformation'],
        ]);

        return [$user, $post, $moment];
    }
}
