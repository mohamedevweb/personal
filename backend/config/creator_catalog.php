<?php

return [
    'manifest' => database_path('catalog/instagram_creators.php'),
    'markets' => ['FR', 'GB', 'US'],
    'languages' => ['fr', 'en', 'mixed', 'unknown'],
    'statuses' => ['pending', 'approved'],
    'curation_statuses' => ['discovered', 'approved', 'inactive'],
    'recognition_tiers' => ['expert', 'established', 'leader'],

    'verticals' => [
        'sport-fitness' => ['name' => 'Sport & Fitness', 'aliases' => ['sport', 'fitness', 'musculation', 'running', 'coaching sportif', 'nutrition sportive', 'workout', 'strength training']],
        'food-cooking' => ['name' => 'Food & Cooking', 'aliases' => ['food', 'cuisine', 'recettes', 'alimentation saine', 'patisserie', 'pâtisserie', 'healthy food', 'cooking', 'baking']],
        'personal-branding' => ['name' => 'Personal Branding', 'aliases' => ['marque personnelle', 'création de contenu', 'creation de contenu', 'marketing', 'creator economy', 'entrepreneuriat', 'entrepreneurship', 'content creation']],
        'tech-ai' => ['name' => 'Tech & AI', 'aliases' => ['tech', 'technologie', 'ia', 'ai', 'intelligence artificielle', 'développement', 'developpement', 'development', 'saas', 'productivité', 'productivity']],
        'beauty-fashion' => ['name' => 'Beauty & Fashion', 'aliases' => ['beauté', 'beaute', 'beauty', 'skincare', 'maquillage', 'makeup', 'mode', 'fashion', 'style', 'luxe', 'luxury']],
        'wellness' => ['name' => 'Wellness', 'aliases' => ['bien-être', 'bien etre', 'wellbeing', 'santé mentale', 'sante mentale', 'mental health', 'mindfulness', 'méditation', 'meditation', 'sommeil', 'sleep', 'récupération', 'recovery', 'santé globale']],
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
