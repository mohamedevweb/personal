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
                'source_breakdown' => [
                    'hook' => 'The source opens with a first-person tension that makes the viewer wait for the explanation. This version keeps that immediate admission and changes the subject to your own experience.',
                    'development' => 'The source moves from the initial problem to the realization that resolves it. This version follows the same progression from production friction to the story worth telling.',
                    'cta' => 'The source closes on a useful takeaway before inviting a response. This version keeps that order and asks the audience to share a changed assumption.',
                ],
                'hook' => $idea,
                'script' => "I kept assuming the hard part of content was production. It wasn't. The real problem was deciding what was worth saying. After talking to creators, I saw the same pattern: the best ideas happen when a proven format meets a story only you can tell. That insight changed the product I am building.",
                'ending' => 'The hardest part was never production. It was choosing the story only I could tell.',
                'cta' => 'What is one assumption you changed after talking to customers?',
                'tone' => 'Direct and reflective, because the source earns attention through a candid admission before delivering its lesson, while your voice makes the lesson concrete.',
                'filming' => 'Film the opening and closing as a steady talking head at your desk. Use one deliberate cut in the middle when the story moves from the problem to the realization, following the source transition instead of adding constant edits.',
                'visuals' => [
                    ['type' => 'face_camera', 'timing' => '0:00 to 0:05, the admission', 'shot' => 'Look straight into the lens at your desk and deliver the hook without an intro.', 'source_link' => 'The source starts with a direct first-person hook, so the version keeps the viewer on your face for the stop-scroll moment.'],
                    ['type' => 'b_roll', 'timing' => 'During the problem and evidence', 'shot' => 'Cut to your notes from creator conversations and the product screen you changed.', 'source_link' => 'The source develops its tension with concrete context, so these shots make the middle beat visible rather than leaving it as abstract narration.'],
                    ['type' => 'cutaway', 'timing' => 'At the realization', 'shot' => 'Show your hand moving one note into a new column, then return to the lens.', 'source_link' => 'The source turns on a clear realization, so this cutaway marks the same turning point before the spoken takeaway.'],
                ],
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
                'source_breakdown' => [
                    'hook' => 'Le Reel source commence par une tension racontée à la première personne. Cette version garde cette confession immédiate, mais la relie à ton expérience.',
                    'development' => "Le Reel source passe du problème initial à la prise de conscience qui le résout. Cette version suit le même mouvement, de la difficulté de produire à l'histoire qui mérite d'être racontée.",
                    'cta' => "Le Reel source termine par une idée utile avant d'ouvrir la conversation. Cette version garde cet ordre et invite ton audience à partager une croyance qu'elle a changée.",
                ],
                'hook' => $idea,
                'script' => "Je pensais que la partie difficile était la production. Ce n'était pas le cas. Le vrai problème était de décider ce qui méritait d'être raconté. En parlant avec des créateurs, j'ai vu le même schéma. Les meilleures idées apparaissent quand un format éprouvé rencontre une histoire que toi seul peux raconter. Cette découverte a changé le produit que je construis.",
                'ending' => "Le plus difficile n'était pas de produire. C'était de choisir l'histoire que moi seul pouvais raconter.",
                'cta' => 'Quelle croyance as-tu changée après avoir parlé à tes clients ?',
                'tone' => 'Direct et réflexif. Le Reel source accroche par une confession, puis gagne en force avec une leçon concrète, ce qui correspond à ta voix pédagogique.',
                'filming' => "Filme l'ouverture et la conclusion face caméra, à ton bureau. Fais une coupe volontaire au milieu, au moment où le récit passe du problème à la prise de conscience, comme le changement de séquence du Reel source.",
                'visuals' => [
                    ['type' => 'face_camera', 'timing' => '0:00 à 0:05, la confession', 'shot' => "Regarde l'objectif à ton bureau et dis l'accroche sans introduction.", 'source_link' => "Le Reel source commence par une accroche directe à la première personne. Ta version garde ton visage à l'image pour le moment d'arrêt."],
                    ['type' => 'b_roll', 'timing' => 'Pendant le problème et le contexte', 'shot' => 'Montre tes notes de conversations avec des créateurs et l’écran du produit que tu as fait évoluer.', 'source_link' => 'Le Reel source développe sa tension avec un contexte concret. Ces plans rendent ton développement visible au lieu de le laisser abstrait.'],
                    ['type' => 'cutaway', 'timing' => 'Au moment de la prise de conscience', 'shot' => 'Montre ta main qui déplace une note dans une nouvelle colonne, puis reviens face caméra.', 'source_link' => 'Le Reel source bascule sur une prise de conscience claire. Ce plan de coupe signale le même tournant avant ta conclusion.'],
                ],
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
