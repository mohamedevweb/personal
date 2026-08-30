<?php

namespace App\Services\Content;

use App\Models\ContentPost;
use App\Models\LifeMoment;
use App\Models\Remix;
use App\Models\User;

class MockContentGenerationService implements ContentGenerationService
{
    public function regenerateBlock(Remix $remix, string $block, ?int $slideIndex = null): string
    {
        $content = $remix->generated_content;
        $current = $block === 'slide'
            ? (string) ($content['slides'][$slideIndex]['text'] ?? '')
            : (string) ($content[$block] ?? '');

        return app()->getLocale() === 'fr'
            ? rtrim($current, '.').' Autrement dit, le vrai déclic était déjà là.'
            : rtrim($current, '.').' In other words, the real turning point was already there.';
    }

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
            : 'I spent months looking at creator tools before realizing the real problem was not publishing. It was knowing what to say.';

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
            'carousel' => $base + ['slides' => $this->slides($source, [
                [$idea, 'A photo of you at the desk where this started, shot straight on, the line across the top.'],
                ['My original assumption was simple: creators needed more tools to make content.', 'A screenshot of the tools you were comparing, text over the empty half.'],
                ['But every conversation revealed the same hidden problem.', 'A close-up of your notes from one of those conversations.'],
                ['They did not need more blank pages. They needed better starting points.', 'A blank page next to a page you already filled, side by side.'],
                ['The shift: connect what is working with what is actually happening in your life.', 'A photo of the thing you are working on right now, held in your hand.'],
                ['That is the idea I am building now, and it changed how I think about personal branding.', 'You looking into the camera, the closing line under your face.'],
            ])],
            'reel' => $base + [
                'hook' => $idea,
                'script' => "I kept assuming the hard part of content was production. It wasn't. The real problem was deciding what was worth saying. After talking to creators, I saw the same pattern: the best ideas happen when a proven format meets a story only you can tell. That insight changed the product I am building.",
                'visual' => 'Talking head at your desk. Cut to notes and product screens when the realization lands.',
                'ending' => 'The hardest part was never production. It was choosing the story only I could tell.',
                'cta' => 'What is one assumption you changed after talking to customers?',
            ],
            default => $base + [
                'caption' => $idea."\n\nI thought creators needed another way to produce content faster. What I kept hearing was different: the hardest part happens before the blank page.\n\nThe useful content was already there, in customer conversations, mistakes, pivots and small wins. The missing piece was knowing which story to tell, and why now.\n\nThat realization changed what I decided to build.",
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
            'carousel' => $base + ['slides' => $this->slides($source, [
                [$idea, 'Une photo de toi au bureau où tout a commencé, de face, la phrase en haut.'],
                ["Mon idée de départ était simple. Les créateurs avaient besoin de plus d'outils pour produire du contenu.", 'Une capture des outils que tu comparais, le texte sur la moitié vide.'],
                ['Mais chaque conversation révélait le même problème caché.', 'Un gros plan sur tes notes prises pendant une de ces conversations.'],
                ["Ils n'avaient pas besoin de plus de pages blanches. Ils avaient besoin de meilleurs points de départ.", 'Une page blanche à côté d\'une page déjà remplie, côte à côte.'],
                ['Le déclic a été de relier ce qui fonctionne à ce qui se passe réellement dans ta vie.', 'Une photo de ce que tu construis en ce moment, tenu dans ta main.'],
                ["C'est ce que je construis maintenant. Cette idée a changé ma vision du personal branding.", 'Toi face caméra, la phrase de fin sous ton visage.'],
            ])],
            'reel' => $base + [
                'hook' => $idea,
                'script' => "Je pensais que la partie difficile était la production. Ce n'était pas le cas. Le vrai problème était de décider ce qui méritait d'être raconté. En parlant avec des créateurs, j'ai vu le même schéma. Les meilleures idées apparaissent quand un format éprouvé rencontre une histoire que toi seul peux raconter. Cette découverte a changé le produit que je construis.",
                'visual' => 'Filme-toi face caméra à ton bureau. Coupe vers tes notes et les écrans du produit au moment de la prise de conscience.',
                'ending' => "Le plus difficile n'était pas de produire. C'était de choisir l'histoire que moi seul pouvais raconter.",
                'cta' => 'Quelle croyance as-tu changée après avoir parlé à tes clients ?',
            ],
            default => $base + [
                'caption' => $idea."\n\nJe pensais que les créateurs avaient besoin d'un nouvel outil pour produire plus vite. Mais leurs retours disaient autre chose. La partie la plus difficile arrive avant la page blanche.\n\nLa matière utile était déjà là, dans les conversations clients, les erreurs, les pivots et les petites victoires. Il manquait seulement la bonne histoire à raconter au bon moment.\n\nCette prise de conscience a changé ce que j'ai décidé de construire.",
            ],
        };
    }

    /**
     * The deck the real providers are asked for: exactly as many slides as the
     * source has, each one a line and the picture to put behind it. The written
     * slides repeat when the source is longer than this canned story.
     *
     * @param  list<array{0: string, 1: string}>  $written
     * @return list<array<string, mixed>>
     */
    private function slides(ContentPost $source, array $written): array
    {
        $count = RemixFormat::slideCount($source);

        return array_map(function (int $index) use ($written): array {
            [$text, $image] = $written[$index % count($written)];

            return [
                'id' => $index + 1,
                'text' => $text,
                'image' => $image,
                'source_position' => $index + 1,
            ];
        }, range(0, $count - 1));
    }

    private function firstSentence(string $content): string
    {
        return rtrim(str($content)->before('.')->toString(), '.').', and it changed how I think about what to build next.';
    }
}
