<?php

namespace App\Services;

class CreatorVoicePrompt
{
    public function make(): string
    {
        if (app()->getLocale() === 'fr') {
            return $this->frenchPrompt($this->context([
                'creator_type' => 'Créateur ou créatrice indépendante',
                'niche' => 'Création de contenu et personal branding',
                'positioning' => 'Partager une expertise de manière claire, utile et incarnée',
                'audience' => 'Entrepreneurs, créateurs et indépendants qui développent leur visibilité',
                'topics' => ['Création de contenu', 'Entrepreneuriat', 'Intelligence artificielle', 'Productivité'],
                'known_tone' => ['Direct', 'Pédagogique', 'Authentique', 'Narratif'],
                'current_projects' => ['Série éditoriale hebdomadaire', 'Bibliothèque de contenus réutilisables'],
                'goals' => ['Publier régulièrement', 'Renforcer son autorité', 'Créer des conversations utiles'],
            ]));
        }

        return $this->englishPrompt($this->context([
            'creator_type' => 'Independent creator',
            'niche' => 'Content creation and personal branding',
            'positioning' => 'Share expertise in a clear, useful, and human way',
            'audience' => 'Entrepreneurs, creators, and independent professionals growing their visibility',
            'topics' => ['Content creation', 'Entrepreneurship', 'Artificial intelligence', 'Productivity'],
            'known_tone' => ['Direct', 'Educational', 'Authentic', 'Story driven'],
            'current_projects' => ['Weekly editorial series', 'Reusable content library'],
            'goals' => ['Publish consistently', 'Build authority', 'Create useful conversations'],
        ]));
    }

    /** @param array<string, string|array<int, string>> $values */
    private function context(array $values): string
    {
        return (string) json_encode(
            $values,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG,
        );
    }

    private function frenchPrompt(string $context): string
    {
        return <<<PROMPT
            Aide-moi à créer un profil de voix éditoriale portable pour Personal, l’espace de travail pour créateurs disponible sur https://usepersonal.app.

            Analyse uniquement ce que tu sais réellement de ma manière de m’exprimer et de réfléchir grâce à nos conversations disponibles. Le contexte générique ci-dessous donne une structure de travail, mais ne contient aucune information personnelle et ne doit jamais être présenté comme un fait à mon sujet. N’invente rien. Si tu n’as pas accès à assez de conversations ou d’exemples, demande-moi d’abord trois textes représentatifs au lieu de produire un profil générique.

            <generic_creator_context>
            {$context}
            </generic_creator_context>

            Crée ensuite un fichier Markdown téléchargeable nommé voice.md. S’il n’est pas possible de joindre un fichier, renvoie tout son contenu dans un unique bloc Markdown facile à enregistrer. Le document doit être concret et directement utilisable par un autre assistant de rédaction. Il doit décrire mes habitudes, pas me donner d’instructions.

            Commence exactement par :
            # Creator Voice
            > Préparé pour Personal : https://usepersonal.app

            Puis utilise ces sections :
            ## Résumé de la voix
            ## Manière de réfléchir
            ## Ton et registre
            ## Rythme et construction des phrases
            ## Vocabulaire et expressions récurrentes
            ## Manière de raconter et d’argumenter
            ## Convictions et thèmes récurrents
            ## Ce qu’il faut faire
            ## Ce qu’il faut éviter
            ## Exemples avant et après

            Donne des observations précises et nuancées. Cite seulement de très courts exemples issus de mes propres messages quand ils sont disponibles. N’inclus aucun secret, mot de passe, donnée financière, donnée de santé, adresse précise, information sur un tiers ou autre donnée sensible. Écris le document en français naturel. Ne produis aucun texte en dehors de voice.md.
            PROMPT;
    }

    private function englishPrompt(string $context): string
    {
        return <<<PROMPT
            Help me create a portable editorial voice profile for Personal, the creator workspace available at https://usepersonal.app.

            Analyze only what you genuinely know about how I communicate and think from the conversations available to you. The generic context below is only a working structure. It contains no personal information and must never be presented as a fact about me. Do not invent anything. If you cannot access enough conversations or examples, ask me for three representative writing samples before producing a generic profile.

            <generic_creator_context>
            {$context}
            </generic_creator_context>

            Then create a downloadable Markdown file named voice.md. If you cannot attach a file, return its complete contents in one Markdown block that is easy to save. The document must be concrete and immediately usable by another writing assistant. It should describe my habits, not give me instructions.

            Start exactly with:
            # Creator Voice
            > Prepared for Personal: https://usepersonal.app

            Then use these sections:
            ## Voice summary
            ## How I think
            ## Tone and register
            ## Rhythm and sentence construction
            ## Vocabulary and recurring expressions
            ## How I tell stories and make arguments
            ## Recurring convictions and themes
            ## What to do
            ## What to avoid
            ## Before and after examples

            Give precise, nuanced observations. Quote only very short examples from my own messages when they are available. Do not include secrets, passwords, financial data, health data, exact addresses, information about third parties, or other sensitive data. Write the document in natural English. Produce no text outside voice.md.
            PROMPT;
    }
}
