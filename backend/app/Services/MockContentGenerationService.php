<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\User;

class MockContentGenerationService implements ContentGenerationService
{
    public function generate(ContentPost $source, User $user, string $format, ?LifeMoment $moment = null): array
    {
        if (app()->getLocale() === 'fr') {
            return $this->generateFrench($source, $user, $format, $moment);
        }

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

    /** @return array<string, mixed> */
    private function generateFrench(ContentPost $source, User $user, string $format, ?LifeMoment $moment): array
    {
        $profile = $user->creatorProfile;
        $context = $moment?->content
            ?? 'Tu construis Personal après avoir étudié la manière dont les créateurs trouvent des idées liées à leur propre histoire.';
        $idea = $moment
            ? rtrim(str($moment->content)->before('.')->toString(), '.').', et cela a changé ma façon de voir la suite.'
            : "J'ai passé des mois à étudier les outils pour créateurs avant de comprendre que le vrai problème n'était pas de publier, mais de savoir quoi raconter.";

        $base = [
            'original_pattern' => $source->hook,
            'why_it_works' => [
                'Une ouverture sous tension qui éveille immédiatement la curiosité',
                'Une expérience précise qui renforce la crédibilité',
                'Un récit qui mène du problème à une prise de conscience utile',
                "Une conclusion claire qui donne envie d'enregistrer le contenu",
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
                ['id' => 2, 'text' => "Mon idée de départ était simple. Les créateurs avaient besoin de plus d'outils pour produire du contenu."],
                ['id' => 3, 'text' => 'Mais chaque conversation révélait le même problème caché.'],
                ['id' => 4, 'text' => "Ils n'avaient pas besoin de plus de pages blanches. Ils avaient besoin de meilleurs points de départ."],
                ['id' => 5, 'text' => 'Le déclic a été de relier ce qui fonctionne à ce qui se passe réellement dans ta vie.'],
                ['id' => 6, 'text' => "C'est ce que je construis maintenant. Cette idée a changé ma vision du personal branding."],
            ]],
            'reel' => $base + [
                'hook' => $idea,
                'script' => "Je pensais que la partie difficile était la production. Ce n'était pas le cas. Le vrai problème était de décider ce qui méritait d'être raconté. En parlant avec des créateurs, j'ai vu le même schéma. Les meilleures idées apparaissent quand un format éprouvé rencontre une histoire que toi seul peux raconter. Cette découverte a changé le produit que je construis.",
                'visual' => 'Filme-toi face caméra à ton bureau. Coupe vers tes notes et les écrans du produit au moment de la prise de conscience.',
                'cta' => 'Quelle croyance as-tu changée après avoir parlé à tes clients ?',
            ],
            default => $base + [
                'caption' => $idea."\n\nJe pensais que les créateurs avaient besoin d'un nouvel outil pour produire plus vite. Mais leurs retours disaient autre chose. La partie la plus difficile arrive avant la page blanche.\n\nLa matière utile était déjà là, dans les conversations clients, les erreurs, les pivots et les petites victoires. Il manquait seulement la bonne histoire à raconter au bon moment.\n\nCette prise de conscience a changé ce que j'ai décidé de construire.",
            ],
        };
    }

    private function firstSentence(string $content): string
    {
        return rtrim(str($content)->before('.')->toString(), '.').'—and it changed how I think about what to build next.';
    }
}
