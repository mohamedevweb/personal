<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\User;

class MockContentGenerationService implements ContentGenerationService
{
    public function generate(ContentPost $source, User $user, string $format, ?LifeMoment $moment = null): array
    {
        $profile = $user->creatorProfile;
        $context = $moment?->content
            ?? 'You have been building Personal after studying how creators find ideas that fit their own story.';
        $idea = $moment
            ? $this->firstSentence($moment->content)
            : 'I spent months looking at creator tools before realizing the real problem was not publishing—it was knowing what to say.';

        $base = [
            'original_pattern' => $source->hook,
            'why_it_works' => [
                'A tension-led opening that creates immediate curiosity',
                'Specific experience creates credibility',
                'The story moves from friction to a useful realization',
                'A clear takeaway gives the audience a reason to save it',
            ],
            'your_context' => $context,
            'your_version' => $idea,
            'profile_used' => [
                'niche' => $profile?->niche,
                'tone' => $profile?->tone ?? [],
                'topics' => $profile?->topics ?? [],
            ],
        ];

        return match ($format) {
            'carousel' => $base + ['slides' => [
                ['id' => 1, 'text' => $idea],
                ['id' => 2, 'text' => 'My original assumption was simple: creators needed more tools to make content.'],
                ['id' => 3, 'text' => 'But every conversation revealed the same hidden problem.'],
                ['id' => 4, 'text' => 'They did not need more blank pages. They needed better starting points.'],
                ['id' => 5, 'text' => 'The shift: connect what is working with what is actually happening in your life.'],
                ['id' => 6, 'text' => 'That is the idea I am building now—and it changed how I think about personal branding.'],
            ]],
            'reel' => $base + [
                'hook' => $idea,
                'script' => "I kept assuming the hard part of content was production. It wasn't. The real problem was deciding what was worth saying. After talking to creators, I saw the same pattern: the best ideas happen when a proven format meets a story only you can tell. That insight changed the product I am building.",
                'visual' => 'Talking head at your desk. Cut to notes and product screens when the realization lands.',
                'cta' => 'What is one assumption you changed after talking to customers?',
            ],
            default => $base + [
                'caption' => $idea."\n\nI thought creators needed another way to produce content faster. What I kept hearing was different: the hardest part happens before the blank page.\n\nThe useful content was already there—in customer conversations, mistakes, pivots and small wins. The missing piece was knowing which story to tell, and why now.\n\nThat realization changed what I decided to build.",
            ],
        };
    }

    private function firstSentence(string $content): string
    {
        return rtrim(str($content)->before('.')->toString(), '.').'—and it changed how I think about what to build next.';
    }
}
