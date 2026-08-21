<?php

namespace Database\Seeders;

use App\Models\ContentOpportunity;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\CreatorProfile;
use App\Models\LifeMoment;
use App\Models\SavedContent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Demo data is disabled in production.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'creator@personal.local'],
            ['name' => 'Mohamed Chettah', 'password' => Hash::make('personal'), 'email_verified_at' => now()],
        );

        CreatorProfile::query()->updateOrCreate(['user_id' => $user->id], [
            'instagram_username' => null,
            'display_name' => 'Mohamed Chettah',
            'bio' => null,
            'niche' => null,
            'audience_description' => null,
            'positioning' => null,
            'topics' => [],
            'tone' => [],
            'current_projects' => [],
            'goals' => [],
            'content_strengths' => [],
        ]);

        $creators = collect([
            ['buildwithmaya', 'Maya Chen', 'SaaS', 118000, 72000, 4200],
            ['alexbuilds', 'Alex Morgan', 'Entrepreneurship', 84000, 44000, 2600],
            ['nadiacreates', 'Nadia Benali', 'Creator economy', 156000, 91000, 5700],
            ['founderframes', 'Leo Park', 'Building in public', 67000, 38000, 2100],
            ['growthnotes', 'Amara Lewis', 'Marketing', 212000, 130000, 8100],
            ['saaswithsam', 'Sam Rivera', 'SaaS', 94000, 51000, 3000],
            ['theoperator', 'Nico Laurent', 'Entrepreneurship', 176000, 99000, 6200],
            ['storybysofia', 'Sofia Marin', 'Personal branding', 132000, 76000, 4900],
            ['productdiary', 'Yara Haddad', 'Product', 73000, 41000, 2300],
            ['creatorbrief', 'Jamie Kim', 'Creator economy', 188000, 110000, 7000],
            ['bootstrappedben', 'Ben Foster', 'Bootstrapping', 59000, 34000, 1900],
            ['marketwithmina', 'Mina Okafor', 'Marketing', 145000, 85000, 5300],
            ['lucaslaunches', 'Lucas Silva', 'Launch strategy', 101000, 58000, 3500],
            ['honestfounder', 'Eva Brooks', 'Founder journey', 127000, 69000, 4100],
            ['onepersonstudio', 'Omar Aziz', 'Solo business', 81000, 46000, 2800],
        ])->map(function (array $item, int $index) {
            return Creator::query()->updateOrCreate(['username' => $item[0]], [
                'display_name' => $item[1],
                'avatar_url' => 'https://i.pravatar.cc/160?img='.(12 + $index),
                'niche' => $item[2],
                // The benchmark layer stands in for measured accounts, so it carries
                // the same signals a profile scrape would have written.
                'niche_topics' => array_map('strtolower', explode(' ', $item[2])),
                'followers' => $item[3],
                'average_views' => $item[4],
                'average_likes' => $item[5],
                'baseline_engagement' => (int) round($item[5] * 1.1),
                'avg_engagement_rate' => round($item[5] / $item[3] * 100, 2),
            ]);
        });

        $hooks = [
            'I spent 3 years building the wrong kind of business.',
            'Nobody tells you this before your first product launch.',
            'The boring system behind my best month ever.',
            'I interviewed 47 creators. They all said the same thing.',
            'My startup failed. This decision was the real reason.',
            'Stop trying to find your niche. Do this instead.',
            'The one-person business model I wish I understood sooner.',
            'We crossed $20k MRR—and immediately changed the roadmap.',
            'A customer complaint completely changed our product.',
            'Three signs you are building for an imaginary customer.',
            'The content strategy that got me my first 10,000 followers.',
            'I deleted half my roadmap. Growth went up.',
            'Your audience does not need more advice.',
            'What building in public actually taught me about trust.',
            'I almost skipped the meeting that changed my company.',
            'The launch checklist that saved us from an expensive mistake.',
            'Most founder content fails before the first sentence.',
            'I tracked every hour for 30 days. Here is what surprised me.',
            'The smallest feature created our biggest growth loop.',
            'A simple test for ideas that are actually worth posting.',
            'Why I stopped copying creators with 1M followers.',
            'I raised my prices and lost the wrong customers.',
            'Seven lessons from shipping seven products.',
            'My most embarrassing sales call became my best post.',
            'The 15-minute ritual that fixed my creative block.',
            'I thought consistency was the answer. I was wrong.',
            'This carousel outperformed everything—and it took 20 minutes.',
            'What 100 customer conversations taught me about positioning.',
            'The founder advantage nobody can automate.',
            'I stopped sharing outcomes and started sharing decisions.',
            'Your next content idea is probably already in your calendar.',
            'The anti-growth tactic that made our audience care.',
            'I built a feature nobody used. Here is the useful part.',
            'A better way to turn expertise into content.',
            'The story framework I use when nothing feels worth sharing.',
            'One screenshot generated 1,200 qualified leads.',
            'Behind our pivot: four months of evidence I ignored.',
            'You do not need to be an expert to teach this.',
            'My best marketing channel was hiding in support tickets.',
            'The week I nearly quit was the week the product finally clicked.',
        ];
        $images = [
            'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1553877522-43269d4ea984?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1531482615713-2afd69097998?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80',
        ];
        $ratios = [8.4, 6.7, 5.2, 9.1, 4.8, 7.3, 3.9, 8.8, 5.6, 6.1, 4.4, 7.9];

        foreach ($hooks as $index => $hook) {
            $creator = $creators[$index % $creators->count()];
            $ratio = $ratios[$index % count($ratios)];
            $views = (int) round($creator->average_views * $ratio);
            $likes = (int) round($views * (0.045 + (($index % 4) * 0.008)));
            $comments = 80 + (($index * 47) % 730);
            ContentPost::query()->updateOrCreate(
                ['hook' => $hook],
                [
                    'creator_id' => $creator->id,
                    'platform' => 'instagram',
                    'format' => $index % 3 === 0 ? 'Carousel' : 'Reel',
                    'caption' => $hook."\n\nThe useful part was not the outcome. It was the decision that changed what happened next. Here is the honest breakdown and the lesson I would carry into the next build.",
                    'thumbnail_url' => $images[$index % count($images)],
                    'views' => $views,
                    'likes' => $likes,
                    'comments' => $comments,
                    'published_at' => now()->subHours(6 + ($index * 7)),
                    'performance_ratio' => $ratio,
                    'outlier_score' => $ratio,
                    'engagement_rate' => round(($likes + $comments) / $creator->followers * 100, 3),
                    'measured_at' => now(),
                    'tags' => array_values(array_unique([$creator->niche, $index % 2 ? 'Founder story' : 'SaaS', $index % 3 ? 'Lesson' : 'Building in public'])),
                    'why_it_works' => 'It starts with a specific tension, earns attention through lived experience, and closes with a practical shift the audience can apply.',
                    'hook_analysis' => 'A first-person admission creates curiosity and credibility without giving away the resolution.',
                    'structure_analysis' => 'Unexpected problem → honest context → turning point → useful lesson → audience prompt.',
                ],
            );
        }

        $moments = collect([
            ['I decided to pivot my creator partnership product after four months of research.', 'Failure', 9, ['strong transformation', 'personal', 'relatable founder problem', 'creates authority']],
            ['I might go to San Francisco for an incubator in September.', 'Upcoming event', 7, ['future tension', 'creates anticipation', 'invites the audience into the journey']],
            ['A creator told me he spends hours every week trying to find content ideas.', 'Meeting', 8, ['real customer insight', 'specific pain point', 'supports your positioning']],
        ])->map(fn (array $item, int $index) => LifeMoment::query()->updateOrCreate(
            ['user_id' => $user->id, 'content' => $item[0]],
            [
                'category' => $item[1],
                'happened_at' => now()->subDays($index * 4)->toDateString(),
                'upcoming_at' => $item[1] === 'Upcoming event' ? now()->addMonth()->toDateString() : null,
                'story_score' => $item[2],
                'story_reasons' => $item[3],
            ],
        ));

        $topPost = ContentPost::query()->orderByDesc('performance_ratio')->firstOrFail();
        $opportunities = [
            [
                'moment' => $moments[0],
                'post' => $topPost,
                'title' => 'Tell the story of your pivot using a failure → realization → new direction format.',
                'explanation' => 'This format is working right now, and you have the perfect story for it.',
                'score' => 96,
                'origin' => 'combined',
            ],
            [
                'moment' => $moments[2],
                'post' => ContentPost::query()->where('format', 'Carousel')->orderByDesc('performance_ratio')->first(),
                'title' => 'Turn one customer sentence into a sharp problem-awareness carousel.',
                'explanation' => 'The quote makes the pain concrete and naturally establishes why Personal should exist.',
                'score' => 91,
                'origin' => 'combined',
            ],
            [
                'moment' => $moments[1],
                'post' => null,
                'title' => 'Build anticipation around the incubator decision before the outcome is known.',
                'explanation' => 'Sharing the decision process now gives your audience a reason to follow the next chapter.',
                'score' => 82,
                'origin' => 'life_moment',
            ],
        ];

        foreach ($opportunities as $item) {
            ContentOpportunity::query()->updateOrCreate(
                ['user_id' => $user->id, 'title' => $item['title']],
                [
                    'content_post_id' => $item['post']?->id,
                    'life_moment_id' => $item['moment']->id,
                    'explanation' => $item['explanation'],
                    'relevance_score' => $item['score'],
                    'origin' => $item['origin'],
                ],
            );
        }

        SavedContent::query()->firstOrCreate([
            'user_id' => $user->id,
            'content_post_id' => $topPost->id,
        ]);
    }
}
