<?php

/*
| Golden Catalog FR, version 1.
|
| Handles are copied from the Instagram URLs below and supported by a public
| editorial source. Metrics and recognition tiers deliberately do not live in
| this file: ScrapeCreators measures them after the human review.
*/

$sources = [
    'sport' => 'https://www.clickanalytic.com/fr/find-influencers/france/top-25-french-fitness-influencers/',
    'food' => 'https://www.demotivateur.fr/influence-food/top-influenceurs-food-france',
    'branding' => 'https://www.favikon.com/blog/top-business-influencers-france',
    'tech' => 'https://tytopr.com/wp-content/uploads/2025-Tyto-Tech-500.pdf',
    'beauty' => 'https://www.kolsquare.com/fr/top-influencers/top-10-influenceuses-beaute-francaises',
    'wellness' => 'https://www.favikon.com/blog/top-wellness-influencers-france',
];

$catalog = [
    'sport-fitness' => [
        ['tiboinshape', ['musculation', 'fitness', 'motivation'], 'Créateur fitness français grand public.', $sources['sport']],
        ['jujufitcats', ['fitness', 'musculation', 'lifestyle'], 'Créatrice fitness française avec une ligne éditoriale personnelle.', $sources['sport']],
        ['sissymua', ['fitness', 'coaching', 'bien-être'], 'Créatrice et coach fitness française reconnue.', $sources['sport']],
        ['justinegallice', ['fitness', 'coaching', 'nutrition sportive'], 'Coach fitness française publiant des entraînements et conseils.', $sources['sport']],
        ['majormouvement', ['mobilité', 'santé', 'coaching sportif'], 'Kinésithérapeute créateur français centré sur le mouvement.', $sources['sport']],
    ],
    'food-cooking' => [
        ['cedricgrolet', ['pâtisserie', 'gastronomie', 'technique'], 'Chef pâtissier français dont le contenu met en scène ses créations.', $sources['food']],
        ['louloukitchen_', ['recettes', 'cuisine méditerranéenne', 'food lifestyle'], 'Créatrice food française aux recettes courtes et accessibles.', $sources['food']],
        ['hervecuisine', ['recettes', 'pâtisserie', 'cuisine maison'], 'Créateur français historique de recettes pédagogiques.', $sources['food']],
        ['diegoalary', ['recettes', 'chef', 'food entertainment'], 'Chef et créateur français publiant principalement de la cuisine.', $sources['food']],
        ['not_so_superflu', ['recettes', 'anti-gaspillage', 'cuisine maison'], 'Créatrice française reconnue pour ses recettes anti-gaspillage.', $sources['food']],
    ],
    'personal-branding' => [
        ['caroline.mignaux', ['création de contenu', 'marketing', 'podcast'], 'Entrepreneure et créatrice française centrée sur le marketing.', $sources['branding']],
        ['matthieustefani', ['entrepreneuriat', 'podcast', 'business'], 'Créateur et intervieweur français de l’écosystème entrepreneurial.', $sources['branding']],
        ['antoinebm', ['création de contenu', 'marketing', 'entrepreneuriat'], 'Créateur français spécialisé dans les business de contenu.', $sources['branding']],
        ['anthonybourbon1', ['entrepreneuriat', 'investissement', 'personal branding'], 'Entrepreneur français avec une présence éditoriale incarnée.', $sources['branding']],
        ['alexhitchens', ['entrepreneuriat', 'vente', 'personal branding'], 'Créateur français orienté vente, business et développement personnel.', $sources['branding']],
    ],
    'tech-ai' => [
        ['micode', ['cybersécurité', 'développement', 'tech'], 'Créateur français de vulgarisation informatique.', $sources['tech']],
        ['leotechmaker', ['technologie', 'produits tech', 'vulgarisation'], 'Créateur français spécialisé dans l’actualité et les produits tech.', $sources['tech']],
        ['mrjojol67', ['smartphones', 'produits tech', 'tests'], 'Créateur tech français publiant des tests et de la vulgarisation.', $sources['tech']],
        ['benjamincode', ['développement', 'design', 'carrière tech'], 'Développeur et créateur français centré sur le code et le design.', $sources['tech']],
        ['leoduffoff', ['technologie', 'produits tech', 'analyse'], 'Créateur français proposant des analyses et critiques tech.', $sources['tech']],
    ],
    'beauty-fashion' => [
        ['lenamahfouf', ['mode', 'lifestyle', 'luxe'], 'Créatrice française reconnue dans la mode et le lifestyle.', $sources['beauty']],
        ['sananas2106', ['maquillage', 'skincare', 'beauté'], 'Créatrice beauté française spécialisée dans le maquillage.', $sources['beauty']],
        ['romy', ['beauté', 'mode', 'lifestyle'], 'Créatrice française avec une ligne éditoriale beauté et mode.', $sources['beauty']],
        ['noholita', ['mode', 'style', 'lifestyle'], 'Créatrice mode française connue pour son contenu de style personnel.', $sources['beauty']],
        ['paolalct', ['mode', 'beauté', 'lifestyle'], 'Créatrice française active sur les formats mode et beauté.', $sources['beauty']],
    ],
    'wellness' => [
        ['chloe___bloom', ['méditation', 'développement personnel', 'bien-être'], 'Créatrice française centrée sur le bien-être et la méditation.', $sources['wellness']],
        ['christophe_andre_officiel', ['méditation', 'santé mentale', 'psychologie'], 'Psychiatre français publiant du contenu incarné sur la santé mentale.', $sources['wellness']],
        ['lilibarbery', ['méditation', 'respiration', 'bien-être'], 'Créatrice française spécialisée dans la méditation et la respiration.', $sources['wellness']],
        ['jonathanlehmann', ['méditation', 'développement personnel', 'santé mentale'], 'Créateur français de contenus de méditation et de développement personnel.', $sources['wellness']],
        ['fabienolicard', ['cerveau', 'mémoire', 'bien-être mental'], 'Créateur français de vulgarisation autour du cerveau et des capacités mentales.', $sources['wellness']],
    ],
];

$entries = [];

foreach ($catalog as $vertical => $creators) {
    foreach ($creators as [$handle, $topics, $rationale, $editorialSource]) {
        $entries[] = [
            'handle' => $handle,
            'instagram_url' => "https://www.instagram.com/{$handle}/",
            'market' => 'FR',
            'vertical' => $vertical,
            'topics' => $topics,
            'rationale' => $rationale,
            'source_urls' => ["https://www.instagram.com/{$handle}/", $editorialSource],
            'editorially_verified_at' => '2026-08-21',
            'status' => 'pending',
        ];
    }
}

return $entries;
