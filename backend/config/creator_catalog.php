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

    // The versioned Golden Catalog still contains the original five French
    // verticals. Discovery and feed classification also support the broader
    // verticals below, which are populated by the production seed batches.
    'manifest_verticals' => [
        'sport-fitness',
        'food-cooking',
        'personal-branding',
        'tech-ai',
        'wellness',
    ],

    'verticals' => [
        'sport-fitness' => ['name' => 'Sport & Fitness', 'fallback_alias' => 'fitness', 'aliases' => ['sport', 'fitness', 'football', 'soccer', 'athlete', 'athlète', 'musculation', 'running', 'coaching sportif', 'nutrition sportive', 'workout', 'strength training']],
        'food-cooking' => ['name' => 'Food & Cooking', 'fallback_alias' => 'food', 'aliases' => ['food', 'cuisine', 'recettes', 'recipe', 'recipes', 'alimentation saine', 'patisserie', 'pâtisserie', 'healthy food', 'cooking', 'baking']],
        'personal-branding' => ['name' => 'Personal Branding', 'fallback_alias' => 'création de contenu', 'aliases' => ['marque personnelle', 'création de contenu', 'creation de contenu', 'marketing', 'creator economy', 'entrepreneuriat', 'entrepreneurship', 'content creation', 'motivation', 'mindset', 'inspiration', 'success', 'leadership', 'founder', 'founders']],
        'tech-ai' => ['name' => 'Tech & AI', 'fallback_alias' => 'tech', 'aliases' => ['tech', 'technologie', 'ia', 'ai', 'intelligence artificielle', 'développement', 'developpement', 'development', 'saas', 'productivité', 'productivity']],
        'wellness' => ['name' => 'Wellness', 'fallback_alias' => 'bien-être', 'aliases' => ['bien-être', 'bien etre', 'wellbeing', 'santé mentale', 'sante mentale', 'mental health', 'mindfulness', 'méditation', 'meditation', 'sommeil', 'sleep', 'récupération', 'recovery', 'santé globale']],
        'events' => ['name' => 'Events', 'fallback_alias' => 'events', 'aliases' => ['events', 'event', 'événementiel', 'evenementiel', 'wedding', 'weddings', 'mariage', 'mariages', 'event planning', 'wedding planner', 'conference', 'conference planning', 'concert', 'festival']],
        'languages' => ['name' => 'Languages', 'fallback_alias' => 'languages', 'aliases' => ['languages', 'langues', 'language learning', 'french learning', 'english learning', 'apprendre le français', 'apprentissage des langues', 'grammar', 'pronunciation', 'ielts']],
        'lifestyle' => ['name' => 'Lifestyle', 'fallback_alias' => 'lifestyle', 'aliases' => ['lifestyle', 'mode de vie', 'habits', 'habitudes', 'routines', 'routine', 'discipline', 'self-development', 'self development', 'personal development', 'développement personnel', 'minimalism', 'personal growth']],
        'local-culture' => ['name' => 'Local & Culture', 'fallback_alias' => 'local culture', 'aliases' => ['local culture', 'local / culture', 'culture locale', 'local discovery', 'city guide', 'city guides', 'paris', 'lyon', 'london', 'new york', 'nyc', 'sorties', 'addresses']],
        'travel' => ['name' => 'Travel', 'fallback_alias' => 'travel', 'aliases' => ['travel', 'voyage', 'tourism', 'tourisme', 'destination', 'destinations', 'travel tips', 'adventure', 'road trip', 'backpacking']],
        'business' => ['name' => 'Business', 'fallback_alias' => 'business', 'aliases' => ['business', 'startup', 'startups', 'founder', 'founders', 'entrepreneur', 'entrepreneurs', 'entrepreneurship', 'entrepreneuriat', 'investing', 'investment', 'business owner']],
    ],

    // Adjacent subjects may be useful, but they never fill the personalised
    // feed. They live in their own exploration section so relevance remains an
    // entry rule rather than a soft ordering preference.
    'adjacent_verticals' => [
        'sport-fitness' => ['wellness'],
        'food-cooking' => ['wellness'],
        'personal-branding' => ['tech-ai', 'business'],
        'tech-ai' => ['personal-branding', 'business'],
        'business' => ['tech-ai', 'personal-branding'],
        'wellness' => ['sport-fitness', 'food-cooking'],
        'events' => ['local-culture', 'lifestyle'],
        'languages' => ['lifestyle'],
        'lifestyle' => ['wellness', 'personal-branding', 'languages'],
        'local-culture' => ['events', 'travel'],
        'travel' => ['local-culture', 'events'],
    ],

    // These narrower clusters disambiguate broad verticals. In particular,
    // startup/SaaS content must not be treated as equivalent to gadget reviews
    // merely because both happen to sit under Tech & AI.
    'semantic_clusters' => [
        'startup-saas' => ['startup', 'startups', 'saas', 'software as a service', 'founder', 'founders', 'fondateur', 'fondateurs', 'fondatrice', 'fondatrices', 'indie hacker', 'indie hackers', 'build in public', 'building in public', 'entrepreneurship', 'entrepreneuriat', 'early stage', 'early-stage', 'bootstrapping', 'solopreneurship', 'founder journey', 'founder-journey'],
        'ai-entrepreneurship' => ['ai entrepreneurship', 'ai-entrepreneurship', 'ai entrepreneur', 'ai startup', 'ai startups', 'entrepreneurial ai'],
        'ai-agents' => ['ai agents', 'ai-agents', 'ai agent', 'agentic ai', 'agents ia', 'agents ai'],
        'software' => ['software', 'software tools', 'developer tools', 'outils de développement', 'outils de developpement'],
        'creator-marketing' => ['personal branding', 'marque personnelle', 'content creation', 'création de contenu', 'creation de contenu', 'creator economy', 'marketing', 'audience building', 'copywriting'],
        'consumer-tech' => ['smartphone', 'smartphones', 'gadget', 'gadgets', 'setup', 'hardware', 'high tech', 'high-tech', 'tech review', 'product review', 'produits tech'],
        'product-building' => ['product design', 'design produit', 'product management', 'développement produit', 'developpement produit', 'software development', 'développement logiciel', 'developpement logiciel', 'developer tools', 'outils de développement', 'outils de developpement'],
        'strength-training' => ['musculation', 'strength training', 'powerlifting', 'bodybuilding', 'workout', 'gym'],
        'endurance' => ['running', 'course à pied', 'course a pied', 'marathon', 'cycling', 'cyclisme', 'triathlon'],
        'recipes' => ['recipe', 'recipes', 'recette', 'recettes', 'meal prep', 'vegan cooking', 'cuisine maison'],
        'baking' => ['baking', 'patisserie', 'pâtisserie', 'pastry', 'bread', 'dessert', 'desserts'],
        'mental-wellness' => ['mental health', 'santé mentale', 'sante mentale', 'mindfulness', 'méditation', 'meditation', 'stress', 'burnout'],
        'events' => ['event', 'events', 'event planning', 'wedding', 'weddings', 'mariage', 'mariages', 'conference', 'concert', 'festival'],
        'languages' => ['language learning', 'french learning', 'english learning', 'grammar', 'pronunciation', 'ielts', 'langues'],
        'lifestyle' => ['lifestyle', 'habits', 'habitudes', 'routine', 'routines', 'discipline', 'self-development', 'personal development', 'développement personnel', 'minimalism'],
        'local-culture' => ['local culture', 'local discovery', 'city guide', 'city guides', 'culture locale', 'paris', 'lyon', 'london', 'new york', 'nyc', 'sorties'],
        'travel' => ['travel', 'voyage', 'tourism', 'tourisme', 'destination', 'destinations', 'travel tips', 'adventure', 'road trip', 'backpacking'],
        'real-estate' => ['real estate', 'immobilier', 'property investing', 'investissement immobilier', 'rental property'],
        'crypto-trading' => ['crypto trading', 'crypto', 'cryptocurrency', 'trading crypto', 'bitcoin trading'],
        'generic-motivation' => ['generic motivation', 'motivation quotes', 'citation motivation', 'mindset motivation'],
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
