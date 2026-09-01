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
    'tech' => 'https://www.blog.agencewaldo.com/classement-des-meilleurs-comptes-instagram-francais-specialises-dans-la-tech/',
    'wellness' => 'https://www.favikon.com/blog/top-wellness-influencers-france',
    'events' => 'https://printsome.com/blog/instagram-event-planning',
    'languages' => 'https://influencersworship.com/language_learning_instagram_influencers/',
    'lifestyle' => 'https://keepface.com/lists/united-states-instagram-micro-lifestyle-daily-life-self-improvement',
    'local-culture' => 'https://www.paris.fr/pages/10-comptes-instagram-qui-racontent-paris-autrement-26530',
    'travel' => 'https://www.clickanalytic.com/find-influencers/united-kingdom/uk-travel-influencers-2/',
    'startup' => 'https://influencers.feedspot.com/startup_instagram_influencers/',
    'business' => 'https://www.marketingscoop.com/marketing/best-business-instagram-accounts/',
];

$catalog = [
    'sport-fitness' => [
        ['tiboinshape', ['musculation', 'fitness', 'motivation'], 'Créateur fitness français grand public.', $sources['sport']],
        ['jujufitcats', ['fitness', 'musculation', 'lifestyle'], 'Créatrice fitness française avec une ligne éditoriale personnelle.', $sources['sport'], 'inactive'],
        ['sissymua', ['fitness', 'coaching', 'bien-être'], 'Créatrice et coach fitness française reconnue.', $sources['sport'], 'inactive'],
        ['justinegallice', ['fitness', 'coaching', 'nutrition sportive'], 'Coach fitness française publiant des entraînements et conseils.', $sources['sport'], 'inactive'],
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
        ['paulinelaigneau', ['entrepreneuriat', 'podcast', 'personal branding'], 'Entrepreneure et créatrice française publiant des conseils business.', $sources['branding']],
        ['antoinebm', ['création de contenu', 'marketing', 'entrepreneuriat'], 'Créateur français spécialisé dans les business de contenu.', $sources['branding']],
        ['anthonybourbon1', ['entrepreneuriat', 'investissement', 'personal branding'], 'Entrepreneur français avec une présence éditoriale incarnée.', $sources['branding']],
        ['yomidenzel', ['entrepreneuriat', 'e-commerce', 'personal branding'], 'Entrepreneur francophone publiant des formats incarnés sur le business en ligne.', 'https://veryimportantpeople.fr/NEWS/Top-10-des-influenceurs-business-et-entrepreneurs-francais-en-2026'],
    ],
    'tech-ai' => [
        ['bprkt', ['produits tech', 'setup', 'tests'], 'Créateur français spécialisé dans les produits et usages tech.', $sources['tech']],
        ['leotechmaker', ['technologie', 'produits tech', 'vulgarisation'], 'Créateur français spécialisé dans l’actualité et les produits tech.', $sources['tech']],
        ['mrjojol67', ['smartphones', 'produits tech', 'tests'], 'Créateur tech français publiant des tests et de la vulgarisation.', $sources['tech']],
        ['jbaptisten', ['produits tech', 'tests', 'actualité tech'], 'Créateur français historique de contenus high-tech.', $sources['tech']],
        ['pulseeon_', ['produits tech', 'astuces', 'tests'], 'Créateur tech français publiant des tests produits et des astuces courtes.', 'https://www.heepsy.com/es/top-instagram/tech/france'],
    ],
    'wellness' => [
        ['chloe___bloom', ['méditation', 'développement personnel', 'bien-être'], 'Créatrice française centrée sur le bien-être et la méditation.', $sources['wellness']],
        ['christophe_andre_officiel', ['méditation', 'santé mentale', 'psychologie'], 'Psychiatre français publiant du contenu incarné sur la santé mentale.', $sources['wellness']],
        ['lilibarbery', ['méditation', 'respiration', 'bien-être'], 'Créatrice française spécialisée dans la méditation et la respiration.', $sources['wellness']],
        ['doc_charlotte_neurologue', ['neurologie', 'santé globale', 'prévention'], 'Neurologue et créatrice française vulgarisant le fonctionnement du cerveau.', 'https://influencers.feedspot.com/france_health_instagram_influencers/'],
        ['anaiswerestchack', ['prévention', 'santé globale', 'accès aux soins'], 'Médecin et créatrice française publiant des conseils de santé et de prévention.', 'https://leclaireur.fnac.com/evenement/662564-dr-anais-werestchack-et-brice-philippon-en-dedicace-a-la-fnac-clermont-ferrand/'],
    ],
];

// The first Golden Catalog remains approved or inactive as previously reviewed.
// These additions are deliberately pending: the provider audit must confirm
// activity, metrics, safety and the requested Reel/carousel balance before any
// of them can enter the visible feed.
$additional = [
    'sport-fitness' => [
        ['coachkamel', ['fitness', 'coaching', 'musculation'], 'Coach français proposant des entraînements et conseils de progression.', $sources['sport'], 'pending', 'FR'],
        ['chloeting', ['fitness', 'workouts', 'home training'], 'Créatrice internationale connue pour ses entraînements accessibles.', $sources['sport'], 'pending', 'US'],
        ['madfit.ig', ['fitness', 'workouts', 'dance fitness'], 'Créatrice anglophone centrée sur les entraînements à domicile.', $sources['sport'], 'pending', 'US'],
        ['carolinegirvan', ['strength training', 'workouts', 'programs'], 'Créatrice britannique spécialisée dans les programmes de renforcement.', $sources['sport'], 'pending', 'GB'],
        ['natacha.oceane', ['fitness', 'science', 'strength training'], 'Créatrice orientée fitness, entraînement et vulgarisation scientifique.', $sources['sport'], 'pending', 'GB'],
    ],
    'food-cooking' => [
        ['fastgoodcuisine', ['recettes', 'food entertainment', 'cuisine maison'], 'Créateur français produisant des recettes et formats food très visuels.', $sources['food'], 'pending', 'FR'],
        ['norberttarayre', ['cuisine', 'chef', 'recettes'], 'Chef et créateur français publiant des recettes et démonstrations culinaires.', $sources['food'], 'pending', 'FR'],
        ['cyril_lignac', ['recettes', 'chef', 'pâtisserie'], 'Chef français partageant recettes, techniques et coulisses de cuisine.', $sources['food'], 'pending', 'FR'],
        ['alexandracuisine', ['recettes', 'cuisine maison', 'pâtisserie'], 'Créatrice food francophone centrée sur des recettes pratiques.', $sources['food'], 'pending', 'FR'],
        ['marmiton_org', ['recettes', 'cuisine maison', 'astuces'], 'Compte éditorial de recettes et d’idées de cuisine du quotidien.', $sources['food'], 'pending', 'FR'],
    ],
    'personal-branding' => [
        ['matthieu.stefani', ['podcast', 'entrepreneuriat', 'création de contenu'], 'Entrepreneur et animateur français partageant des conversations et apprentissages business.', $sources['branding'], 'pending', 'FR'],
        ['gregisenberg', ['startup', 'création de contenu', 'community'], 'Entrepreneur anglophone partageant idées, communautés et construction de produits.', $sources['branding'], 'pending', 'US'],
        ['aliabdaal', ['productivité', 'création de contenu', 'education'], 'Créateur et entrepreneur partageant méthodes de travail et systèmes de contenu.', $sources['branding'], 'pending', 'GB'],
        ['justinwelsh', ['personal branding', 'solopreneurship', 'content marketing'], 'Créateur anglophone spécialisé dans les systèmes de marque personnelle.', $sources['branding'], 'pending', 'US'],
        ['sahilbloom', ['personal branding', 'business', 'storytelling'], 'Créateur et entrepreneur publiant des idées structurées sur le travail et la croissance.', $sources['branding'], 'pending', 'US'],
    ],
    'tech-ai' => [
        ['micode', ['cybersécurité', 'technologie', 'vulgarisation'], 'Créateur français vulgarisant la technologie et la sécurité numérique.', $sources['tech'], 'pending', 'FR'],
        ['mkbhd', ['consumer tech', 'smartphones', 'product reviews'], 'Créateur américain spécialisé dans les produits et usages technologiques.', $sources['tech'], 'pending', 'US'],
        ['ijustine', ['consumer tech', 'gadgets', 'product reviews'], 'Créatrice américaine publiant des tests et expériences autour de la tech grand public.', $sources['tech'], 'pending', 'US'],
        ['mrwhosetheboss', ['consumer tech', 'smartphones', 'product reviews'], 'Créateur britannique spécialisé dans les tests de produits et innovations tech.', $sources['tech'], 'pending', 'GB'],
        ['yourchatgptguide', ['ai tools', 'productivity', 'artificial intelligence'], 'Créateur anglophone expliquant les outils et usages pratiques de l’IA.', $sources['tech'], 'pending', 'US'],
    ],
    'wellness' => [
        ['jayshetty', ['mental wellness', 'mindfulness', 'relationships'], 'Auteur et créateur partageant des contenus de réflexion et de bien-être mental.', $sources['wellness'], 'pending', 'US'],
        ['melissawoodhealth', ['wellness', 'movement', 'mindfulness'], 'Créatrice américaine autour du mouvement doux et des routines de bien-être.', $sources['wellness'], 'pending', 'US'],
        ['hubermanlab', ['neuroscience', 'sleep', 'mental wellness'], 'Compte pédagogique autour des mécanismes du cerveau, du sommeil et des habitudes.', $sources['wellness'], 'pending', 'US'],
        ['the.holistic.psychologist', ['mental health', 'self-awareness', 'relationships'], 'Psychologue et créatrice autour de la santé mentale et de la connaissance de soi.', $sources['wellness'], 'pending', 'US'],
        ['headspace', ['meditation', 'mindfulness', 'sleep'], 'Compte de référence autour de la méditation guidée et du sommeil.', $sources['wellness'], 'pending', 'US'],
    ],
    'events' => [
        ['mindyweiss', ['event planning', 'weddings', 'party design'], 'Consultante américaine reconnue pour ses événements et mariages haut de gamme.', $sources['events'], 'pending', 'US'],
        ['toddevents', ['event planning', 'party design', 'weddings'], 'Équipe événementielle partageant concepts, scénographies et coulisses de célébrations.', $sources['events'], 'pending', 'US'],
        ['sarahhaywoodweddings', ['weddings', 'event design', 'luxury events'], 'Wedding planner britannique spécialisée dans la conception de mariages et événements premium.', $sources['events'], 'pending', 'GB'],
        ['colincowie', ['event design', 'weddings', 'luxury events'], 'Designer et producteur d’événements internationaux.', $sources['events'], 'pending', 'US'],
        ['prestonbailey', ['event design', 'weddings', 'floral design'], 'Designer américain de mariages et événements avec une forte signature visuelle.', $sources['events'], 'pending', 'US'],
        ['eventuresinc', ['event planning', 'corporate events', 'experiential marketing'], 'Agence événementielle partageant des concepts et réalisations expérientielles.', $sources['events'], 'pending', 'US'],
        ['rockpaperdetails', ['event design', 'weddings', 'styling'], 'Studio événementiel publiant des scénographies, détails et inspirations de mariage.', $sources['events'], 'pending', 'US'],
        ['eventdesigncollective', ['event design', 'weddings', 'decor'], 'Collectif de designers partageant des méthodes et inspirations de scénographie.', $sources['events'], 'pending', 'US'],
        ['laurenfair_weddings', ['weddings', 'event planning', 'bridal design'], 'Créatrice événementielle partageant organisation et direction artistique de mariages.', $sources['events'], 'pending', 'US'],
        ['weddingchicks', ['weddings', 'event planning', 'bridal design'], 'Publication spécialisée dans les idées, outils et inspirations de mariage.', $sources['events'], 'pending', 'US'],
    ],
    'languages' => [
        ['frenchwithvincent', ['french learning', 'pronunciation', 'vocabulary'], 'Créateur pédagogique spécialisé dans l’apprentissage du français.', $sources['languages'], 'pending', 'FR'],
        ['innerfrench', ['french learning', 'culture', 'listening'], 'Créateur francophone proposant du contenu accessible pour comprendre le français.', $sources['languages'], 'pending', 'FR'],
        ['learnfrenchwithalexa', ['french learning', 'grammar', 'vocabulary'], 'Professeure et créatrice anglophone dédiée à l’apprentissage du français.', $sources['languages'], 'pending', 'GB'],
        ['commeunefrancaise', ['french learning', 'culture', 'expressions'], 'Créatrice partageant langue, expressions et culture françaises.', $sources['languages'], 'pending', 'FR'],
        ['french_mornings', ['french learning', 'vocabulary', 'pronunciation'], 'Créatrice proposant des leçons courtes de français pour adultes.', $sources['languages'], 'pending', 'US'],
        ['englishwithlucy', ['english learning', 'pronunciation', 'vocabulary'], 'Créatrice britannique spécialisée dans l’anglais et la prononciation.', $sources['languages'], 'pending', 'GB'],
        ['speakenglishwithvanessa', ['english learning', 'conversation', 'pronunciation'], 'Professeure américaine axée sur la conversation et la compréhension orale.', $sources['languages'], 'pending', 'US'],
        ['spanishafterhours', ['spanish learning', 'conversation', 'culture'], 'Créatrice partageant des leçons d’espagnol et des situations de conversation.', $sources['languages'], 'pending', 'US'],
        ['easyspanish', ['spanish learning', 'conversation', 'culture'], 'Équipe pédagogique utilisant des conversations réelles pour apprendre l’espagnol.', $sources['languages'], 'pending', 'US'],
        ['street_french', ['french learning', 'conversation', 'expressions'], 'Créateur anglophone enseignant le français parlé et les expressions du quotidien.', $sources['languages'], 'pending', 'US'],
    ],
    'lifestyle' => [
        ['mattdavella', ['habits', 'minimalism', 'self-development'], 'Créateur américain autour des habitudes, du minimalisme et du développement personnel.', $sources['lifestyle'], 'pending', 'US'],
        ['muchelleb', ['habits', 'self-development', 'productivity'], 'Créatrice australienne partageant des méthodes de changement d’habitudes et d’organisation.', $sources['lifestyle'], 'pending', 'US'],
        ['lavendaire', ['self-development', 'journaling', 'lifestyle'], 'Créatrice américaine autour du journaling, de l’identité et du développement personnel.', $sources['lifestyle'], 'pending', 'US'],
        ['theannaedit', ['lifestyle', 'routine', 'wellness'], 'Créatrice britannique partageant routines, organisation et style de vie.', $sources['lifestyle'], 'pending', 'GB'],
        ['thefrugality', ['lifestyle', 'home', 'personal finance'], 'Créatrice britannique autour du quotidien, de la maison et d’une vie plus intentionnelle.', $sources['lifestyle'], 'pending', 'GB'],
        ['jamesclear', ['habits', 'self-development', 'productivity'], 'Auteur partageant des cadres pratiques autour des habitudes et du comportement.', $sources['lifestyle'], 'pending', 'US'],
        ['sarahs_day', ['routine', 'wellness', 'lifestyle'], 'Créatrice australienne partageant routines, mouvement et quotidien.', $sources['lifestyle'], 'pending', 'US'],
        ['struthless', ['self-development', 'creativity', 'mental wellness'], 'Créateur australien utilisant le dessin et le récit pour parler de développement personnel.', $sources['lifestyle'], 'pending', 'US'],
        ['thesorrygirls', ['home', 'diy', 'lifestyle'], 'Créatrices partageant projets maison, décoration et transformations accessibles.', $sources['lifestyle'], 'pending', 'US'],
        ['becomingminimalist', ['minimalism', 'habits', 'home'], 'Créateur éditorial autour du minimalisme et de la simplification du quotidien.', $sources['lifestyle'], 'pending', 'US'],
    ],
    'local-culture' => [
        ['voulezvousparisavemoi', ['paris', 'local discovery', 'culture locale'], 'Compte racontant Paris à travers ses lieux, ses rues et ses histoires.', $sources['local-culture'], 'pending', 'FR'],
        ['parismusees', ['paris', 'museums', 'culture'], 'Réseau de musées parisiens partageant œuvres, expositions et patrimoine.', $sources['local-culture'], 'pending', 'FR'],
        ['quefaireaparis', ['paris', 'local discovery', 'events'], 'Compte culturel proposant des idées de sorties et découvertes à Paris.', $sources['local-culture'], 'pending', 'FR'],
        ['livinglondonhistory', ['london', 'local history', 'architecture'], 'Créateur britannique racontant l’histoire et les détails cachés de Londres.', $sources['local-culture'], 'pending', 'GB'],
        ['bowlofchalk', ['london', 'local history', 'city guides'], 'Créateur britannique partageant anecdotes et visites thématiques de Londres.', $sources['local-culture'], 'pending', 'GB'],
        ['samaspeaks_', ['london', 'food', 'local discovery'], 'Créatrice londonienne explorant les cultures, lieux et histoires de la ville.', $sources['local-culture'], 'pending', 'GB'],
        ['nycgo', ['new york', 'city guides', 'local discovery'], 'Compte de découverte urbaine consacré aux lieux et expériences de New York.', $sources['local-culture'], 'pending', 'US'],
        ['secret_nyc', ['new york', 'local discovery', 'culture'], 'Publication locale partageant lieux, expériences et histoires de New York.', $sources['local-culture'], 'pending', 'US'],
        ['parisjetaime', ['paris', 'tourism', 'culture'], 'Office de tourisme partageant patrimoine, culture et expériences parisiennes.', $sources['local-culture'], 'pending', 'FR'],
        ['theculturetrip', ['local culture', 'city guides', 'travel'], 'Publication internationale consacrée aux cultures et découvertes locales.', $sources['local-culture'], 'pending', 'GB'],
    ],
    'travel' => [
        ['brunomaltor', ['travel', 'adventure', 'travel tips'], 'Créateur français partageant voyages, conseils pratiques et découvertes.', $sources['travel'], 'pending', 'FR'],
        ['lostleblanc', ['travel', 'filmmaking', 'adventure'], 'Créateur américain spécialisé dans les récits et images de voyage.', $sources['travel'], 'pending', 'US'],
        ['expertvagabond', ['travel', 'adventure', 'travel tips'], 'Créateur américain partageant itinéraires et conseils de voyage aventureux.', $sources['travel'], 'pending', 'US'],
        ['drewbinsky', ['travel', 'culture', 'local discovery'], 'Créateur américain explorant destinations, cultures et histoires locales.', $sources['travel'], 'pending', 'US'],
        ['karaandnate', ['travel', 'couples travel', 'adventure'], 'Couple de créateurs partageant voyages et expériences à travers le monde.', $sources['travel'], 'pending', 'US'],
        ['thebucketlistfamily', ['family travel', 'adventure', 'travel'], 'Famille de créateurs racontant des voyages et aventures internationales.', $sources['travel'], 'pending', 'US'],
        ['oneikatraveller', ['travel', 'culture', 'solo travel'], 'Créatrice britannique partageant voyages, culture et conseils pour voyager seule.', $sources['travel'], 'pending', 'GB'],
        ['candaceabroad', ['travel', 'city guides', 'travel tips'], 'Créatrice britannique partageant des guides de villes et conseils de voyage.', $sources['travel'], 'pending', 'GB'],
        ['framedbytheworld', ['travel', 'photography', 'adventure'], 'Créateur partageant des récits visuels et expériences de voyage.', $sources['travel'], 'pending', 'US'],
        ['finduslost', ['travel', 'photography', 'destinations'], 'Couple de créateurs produisant des guides et récits visuels de destinations.', $sources['travel'], 'pending', 'US'],
    ],
    'startup' => [
        ['buildwithmaya', ['saas', 'founders', 'product building'], 'Créatrice partageant la construction et le développement d’un produit SaaS.', $sources['startup'], 'pending', 'US'],
        ['founderframes', ['startup', 'building in public', 'founder journey'], 'Créateur partageant les coulisses de la construction d’une startup.', $sources['startup'], 'pending', 'US'],
        ['saaswithsam', ['saas', 'product building', 'founders'], 'Créateur spécialisé dans les apprentissages liés aux produits SaaS.', $sources['startup'], 'pending', 'US'],
        ['bootstrappedben', ['bootstrapping', 'solopreneurship', 'founders'], 'Entrepreneur partageant une construction de business autofinancée.', $sources['startup'], 'pending', 'US'],
        ['lucaslaunches', ['launch strategy', 'startup', 'product building'], 'Créateur autour des lancements, produits et apprentissages de startup.', $sources['startup'], 'pending', 'US'],
        ['honestfounder', ['founder journey', 'startup', 'entrepreneurship'], 'Créatrice partageant les réalités et décisions de la vie de fondatrice.', $sources['startup'], 'pending', 'US'],
        ['danmartell', ['saas', 'founders', 'productivity'], 'Entrepreneur et investisseur partageant des cadres pratiques pour les fondateurs.', $sources['startup'], 'pending', 'US'],
        ['levelsio', ['indie hacker', 'bootstrapping', 'product building'], 'Entrepreneur indépendant partageant construction et lancement de produits numériques.', $sources['startup'], 'pending', 'US'],
        ['tinawells', ['entrepreneurship', 'founders', 'career'], 'Entrepreneure et auteure partageant carrière, création et développement de projets.', $sources['startup'], 'pending', 'US'],
        ['rajshamani', ['entrepreneurship', 'founders', 'business'], 'Entrepreneur et animateur partageant conversations et apprentissages business.', $sources['startup'], 'pending', 'US'],
    ],
    'business' => [
        ['alexbuilds', ['entrepreneurship', 'business', 'founders'], 'Entrepreneur partageant décisions, systèmes et apprentissages de construction de business.', $sources['business'], 'pending', 'US'],
        ['growthnotes', ['marketing', 'growth', 'business'], 'Créatrice autour du marketing, de la croissance et des systèmes d’acquisition.', $sources['business'], 'pending', 'US'],
        ['theoperator', ['entrepreneurship', 'operations', 'business'], 'Créateur partageant opérations, management et décisions d’entreprise.', $sources['business'], 'pending', 'FR'],
        ['marketwithmina', ['marketing', 'strategy', 'business'], 'Créatrice partageant stratégies marketing et apprentissages de marché.', $sources['business'], 'pending', 'US'],
        ['onepersonstudio', ['solopreneurship', 'business', 'content marketing'], 'Créateur autour de la construction d’une activité indépendante et rentable.', $sources['business'], 'pending', 'US'],
        ['marketingharry', ['marketing', 'personal branding', 'content creation'], 'Créateur britannique spécialisé dans le marketing et la croissance par le contenu.', $sources['business'], 'pending', 'GB'],
        ['alexhormozi', ['business', 'sales', 'offers'], 'Entrepreneur partageant des cadres de vente, d’offre et de croissance.', $sources['business'], 'pending', 'US'],
        ['codie_sanchez', ['business', 'investing', 'entrepreneurship'], 'Investisseuse et créatrice autour de l’acquisition et de la croissance de business.', $sources['business'], 'pending', 'US'],
        ['lewishowes', ['business', 'entrepreneurship', 'interviews'], 'Entrepreneur et animateur partageant conversations et apprentissages professionnels.', $sources['business'], 'pending', 'US'],
        ['garyvee', ['marketing', 'entrepreneurship', 'business'], 'Entrepreneur et créateur autour du marketing, des médias et de la croissance.', $sources['business'], 'pending', 'US'],
    ],
];

foreach ($additional as $vertical => $creators) {
    $catalog[$vertical] = [...($catalog[$vertical] ?? []), ...$creators];
}

$entries = [];
foreach ($catalog as $vertical => $creators) {
    foreach ($creators as $creator) {
        [$handle, $topics, $rationale, $editorialSource] = $creator;
        $status = $creator[4] ?? 'approved';
        $market = $creator[5] ?? 'FR';
        $entries[] = [
            'handle' => $handle,
            'instagram_url' => "https://www.instagram.com/{$handle}/",
            'market' => $market,
            'vertical' => $vertical,
            'topics' => $topics,
            'rationale' => $rationale,
            'source_urls' => ["https://www.instagram.com/{$handle}/", $editorialSource],
            'editorially_verified_at' => '2026-08-21',
            'status' => $status,
        ];
    }
}

return $entries;
