<?php

return [
    'manifest' => database_path('catalog/instagram_creators.php'),
    'markets' => ['FR', 'GB', 'US'],
    'languages' => ['fr', 'en', 'mixed', 'unknown'],
    'statuses' => ['pending', 'approved', 'inactive'],
    'curation_statuses' => ['discovered', 'approved', 'inactive'],
    'recognition_tiers' => ['expert', 'established', 'leader'],
    'manifest_version' => 'golden-fr-v1',
    'target_total' => 25,
    'target_per_vertical' => 5,

    'verticals' => [
        'sport-fitness' => ['name' => 'Sport & Fitness', 'aliases' => ['sport', 'fitness', 'football', 'soccer', 'athlete', 'athlète', 'musculation', 'running', 'coaching sportif', 'nutrition sportive', 'workout', 'strength training']],
        'food-cooking' => ['name' => 'Food & Cooking', 'aliases' => ['food', 'cuisine', 'recettes', 'alimentation saine', 'patisserie', 'pâtisserie', 'healthy food', 'cooking', 'baking']],
        'personal-branding' => ['name' => 'Personal Branding', 'aliases' => ['marque personnelle', 'création de contenu', 'creation de contenu', 'marketing', 'creator economy', 'entrepreneuriat', 'entrepreneurship', 'content creation']],
        'tech-ai' => ['name' => 'Tech & AI', 'aliases' => ['tech', 'technologie', 'ia', 'ai', 'intelligence artificielle', 'développement', 'developpement', 'development', 'saas', 'productivité', 'productivity']],
        'wellness' => ['name' => 'Wellness', 'aliases' => ['bien-être', 'bien etre', 'wellbeing', 'santé mentale', 'sante mentale', 'mental health', 'mindfulness', 'méditation', 'meditation', 'sommeil', 'sleep', 'récupération', 'recovery', 'santé globale']],
    ],

    // Adjacent subjects may be useful, but they never fill the personalised
    // feed. They live in their own exploration section so relevance remains an
    // entry rule rather than a soft ordering preference.
    'adjacent_verticals' => [
        'sport-fitness' => ['wellness'],
        'food-cooking' => ['wellness'],
        'personal-branding' => ['tech-ai'],
        'tech-ai' => ['personal-branding'],
        'wellness' => ['sport-fitness', 'food-cooking'],
    ],

    // These narrower clusters disambiguate broad verticals. In particular,
    // startup/SaaS content must not be treated as equivalent to gadget reviews
    // merely because both happen to sit under Tech & AI.
    'semantic_clusters' => [
        'startup-saas' => ['startup', 'startups', 'saas', 'software as a service', 'founder', 'founders', 'fondateur', 'fondateurs', 'fondatrice', 'fondatrices', 'indie hacker', 'indie hackers', 'build in public', 'entrepreneurship', 'entrepreneuriat', 'early stage', 'early-stage'],
        'creator-marketing' => ['personal branding', 'marque personnelle', 'content creation', 'création de contenu', 'creation de contenu', 'creator economy', 'marketing', 'audience building', 'copywriting'],
        'consumer-tech' => ['smartphone', 'smartphones', 'gadget', 'gadgets', 'setup', 'hardware', 'high tech', 'high-tech', 'tech review', 'product review', 'produits tech'],
        'product-building' => ['product design', 'design produit', 'product management', 'développement produit', 'developpement produit', 'software development', 'développement logiciel', 'developpement logiciel', 'developer tools', 'outils de développement', 'outils de developpement'],
        'strength-training' => ['musculation', 'strength training', 'powerlifting', 'bodybuilding', 'workout', 'gym'],
        'endurance' => ['running', 'course à pied', 'course a pied', 'marathon', 'cycling', 'cyclisme', 'triathlon'],
        'recipes' => ['recipe', 'recipes', 'recette', 'recettes', 'meal prep', 'vegan cooking', 'cuisine maison'],
        'baking' => ['baking', 'patisserie', 'pâtisserie', 'pastry', 'bread', 'dessert', 'desserts'],
        'mental-wellness' => ['mental health', 'santé mentale', 'sante mentale', 'mindfulness', 'méditation', 'meditation', 'stress', 'burnout'],
    ],

    'audit' => [
        'min_followers' => (int) env('CATALOG_MIN_FOLLOWERS', 25000),
        'active_within_days' => (int) env('CATALOG_ACTIVE_WITHIN_DAYS', 30),
        'posts_window_days' => (int) env('CATALOG_POSTS_WINDOW_DAYS', 90),
        'min_posts' => (int) env('CATALOG_MIN_POSTS', 6),
        'min_metric_coverage' => (float) env('CATALOG_MIN_METRIC_COVERAGE', 0.70),
        'min_median_engagement' => (int) env('CATALOG_MIN_MEDIAN_ENGAGEMENT', 500),
    ],

    'retention_days' => (int) env('DISCOVERY_CONTENT_RETENTION_DAYS', 90),
    'curated_only' => (bool) env('DISCOVERY_CURATED_CATALOG_ONLY', false),
];
