<?php

/*
| Version 1, editorial candidates only.
|
| Every entry starts pending. The audit report is the source used by a human
| editor to promote an entry to approved. A handle being present here is never
| enough to write it to production.
*/

$catalog = [
    'sport-fitness' => [
        ['tiboinshape', 'FR', 'leader'], ['sissymua', 'FR', 'leader'], ['juju_fitcats', 'FR', 'leader'], ['major_mouvement', 'FR', 'leader'],
        ['lucilewoodward', 'FR', 'established'], ['marinelecoq', 'FR', 'established'], ['karoline.ro', 'FR', 'established'], ['fitbyclem', 'FR', 'established'], ['coach_benjamin', 'FR', 'established'], ['mathieu_fit', 'FR', 'expert'],
        ['thebodycoach', 'GB', 'established'], ['natacha.oceane', 'GB', 'established'], ['courtneydblack', 'GB', 'established'], ['james_smith_pt', 'GB', 'expert'], ['lucydavis_fit', 'GB', 'expert'],
        ['therock', 'US', 'established'], ['jeffnippard', 'US', 'established'], ['megsquats', 'US', 'expert'], ['syattfitness', 'US', 'expert'], ['soheefit', 'US', 'expert'],
    ],
    'food-cooking' => [
        ['cyril_lignac', 'FR', 'leader'], ['philippe_etchebest', 'FR', 'leader'], ['hervecuisine', 'FR', 'leader'], ['whoogyss', 'FR', 'leader'],
        ['fastgoodcuisine', 'FR', 'established'], ['laurentmariotte', 'FR', 'established'], ['louloukitchen_', 'FR', 'established'], ['les_patisseries_de_mama', 'FR', 'established'], ['healthyfood_creation', 'FR', 'established'], ['charlesetava', 'FR', 'expert'],
        ['jamieoliver', 'GB', 'established'], ['nigellalawson', 'GB', 'established'], ['deliciouslyella', 'GB', 'established'], ['fitwaffle', 'GB', 'expert'], ['mob', 'GB', 'expert'],
        ['halfbakedharvest', 'US', 'established'], ['bingingwithbabish', 'US', 'established'], ['wishbonekitchen', 'US', 'expert'], ['justine_snacks', 'US', 'expert'], ['salt_hank', 'US', 'expert'],
    ],
    'personal-branding' => [
        ['yomidenzel', 'FR', 'leader'], ['alexhitchens', 'FR', 'leader'], ['caroline.mignaux', 'FR', 'leader'], ['stanleloup', 'FR', 'leader'],
        ['antoinebm', 'FR', 'established'], ['marketingmania', 'FR', 'established'], ['matthieustefani', 'FR', 'established'], ['paulduchemin', 'FR', 'established'], ['daniloduchesnes', 'FR', 'established'], ['audreytips', 'FR', 'expert'],
        ['aliabdaal', 'GB', 'established'], ['steven', 'GB', 'established'], ['danielpriestley', 'GB', 'established'], ['gracebeverley', 'GB', 'expert'], ['chrisducker', 'GB', 'expert'],
        ['garyvee', 'US', 'established'], ['alexhormozi', 'US', 'established'], ['codie_sanchez', 'US', 'expert'], ['justinwelsh', 'US', 'expert'], ['jasminestar', 'US', 'expert'],
    ],
    'tech-ai' => [
        ['micode', 'FR', 'leader'], ['leo_techmaker', 'FR', 'leader'], ['jojol', 'FR', 'leader'], ['ppgarcia75', 'FR', 'leader'],
        ['underscore_', 'FR', 'established'], ['benjamincode', 'FR', 'established'], ['grafikart.fr', 'FR', 'established'], ['theo', 'FR', 'established'], ['defendintelligence', 'FR', 'established'], ['luc_julia', 'FR', 'expert'],
        ['mrwhosetheboss', 'GB', 'established'], ['tomscottgo', 'GB', 'established'], ['techspurt', 'GB', 'established'], ['mmitchelldavies', 'GB', 'expert'], ['techflow', 'GB', 'expert'],
        ['mkbhd', 'US', 'established'], ['ijustine', 'US', 'established'], ['fireship_dev', 'US', 'expert'], ['cleoabram', 'US', 'expert'], ['lexfridman', 'US', 'expert'],
    ],
    'beauty-fashion' => [
        ['lenamahfouf', 'FR', 'leader'], ['enjoyphoenix', 'FR', 'leader'], ['sananas2106', 'FR', 'leader'], ['noholita', 'FR', 'leader'],
        ['paolalct', 'FR', 'established'], ['romy', 'FR', 'established'], ['mayadorable', 'FR', 'established'], ['gaelleprudencio', 'FR', 'established'], ['sulivangwed', 'FR', 'established'], ['chloebbbb', 'FR', 'expert'],
        ['patmcgrathreal', 'GB', 'established'], ['victoriabeckham', 'GB', 'established'], ['alexachung', 'GB', 'established'], ['trinnywoodall', 'GB', 'expert'], ['tamaramory', 'GB', 'expert'],
        ['haileybieber', 'US', 'established'], ['mikaylajmakeup', 'US', 'established'], ['wisdm', 'US', 'expert'], ['chrissyford', 'US', 'expert'], ['katiejanehughes', 'US', 'expert'],
    ],
    'wellness' => [
        ['fabienolicard', 'FR', 'leader'], ['christopheandreofficiel', 'FR', 'leader'], ['lilareinhart_fr', 'FR', 'leader'], ['georgianasegar', 'FR', 'leader'],
        ['healthy_lalou', 'FR', 'established'], ['healthy_life_mary', 'FR', 'established'], ['jennifer_martin_officiel', 'FR', 'established'], ['charlotte_saintjean', 'FR', 'established'], ['elodiegaramond', 'FR', 'established'], ['gaellepiton', 'FR', 'expert'],
        ['drchatterjee', 'GB', 'established'], ['thefoodmedic', 'GB', 'established'], ['fearnecotton', 'GB', 'established'], ['mindfulchefuk', 'GB', 'expert'], ['drjuliesmith', 'GB', 'expert'],
        ['melrobbins', 'US', 'established'], ['hubermanlab', 'US', 'established'], ['gabbybernstein', 'US', 'expert'], ['drmarkhyman', 'US', 'expert'], ['yung_pueblo', 'US', 'expert'],
    ],
];

$topics = [
    'sport-fitness' => ['training', 'coaching', 'performance'],
    'food-cooking' => ['recipes', 'cooking', 'food culture'],
    'personal-branding' => ['content creation', 'marketing', 'entrepreneurship'],
    'tech-ai' => ['technology', 'AI', 'productivity'],
    'beauty-fashion' => ['beauty', 'fashion', 'style'],
    'wellness' => ['mental health', 'mindfulness', 'recovery'],
];

$entries = [];

foreach ($catalog as $vertical => $creators) {
    foreach ($creators as [$handle, $market, $tier]) {
        $entries[] = [
            'handle' => $handle,
            'market' => $market,
            'vertical' => $vertical,
            'topics' => $topics[$vertical],
            'recognition_tier' => $tier,
            'rationale' => 'Editorial candidate with an established public presence in '.$vertical.' for the '.$market.' market.',
            'status' => 'pending',
        ];
    }
}

return $entries;
