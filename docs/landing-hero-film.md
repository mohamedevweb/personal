# Hero film — brief de production

## Ce qu'est réellement la vidéo de lassie.ai

J'ai téléchargé et décomposé `cdn.lassie.ai/assets/media/website/hero/hero.mp4`.

**Ce n'est pas une animation d'interface.** C'est un film live-action, tourné avec des vrais gens. Aucune UI, aucun texte à l'écran, aucun logo. 16,8 s · 1920 × 1080 · 8,6 Mo · muet · en boucle · h264. Affiché plein cadre en 1280 × 720 sous le titre.

L'interface produit n'apparaît **nulle part** dans le hero. Elle arrive plus bas, dans trois cadres de 657 × 414, plus des SVG d'infographie. Exactement la répartition qu'on a codée : émotion en haut, produit en dessous.

### Le montage, plan par plan

| Temps | Plan | Ce que ça dit |
|---|---|---|
| 0 – 2,9 s | Un médecin de dos, marchant sous la coursive de sa clinique, lumière du matin, plantes | On entre dans sa journée, par ses yeux |
| 2,9 – 5,7 s | Macro : deux mains tapent sur un clavier, faible profondeur de champ | L'admin. Le truc qu'il déteste |
| 5,7 – 9,9 s | Une dentiste explique quelque chose à son patient, cabinet lumineux, elle rit presque | Le métier. Le truc qu'il aime |
| 9,9 – 13 s | Abstrait : une silhouette derrière du verre dépoli, bandes graphiques bleues | Une respiration, et la seule marque de la marque |
| 13 – 16,8 s | Golden hour, la famille dans l'allée du garage, basket avec les enfants | La vie rendue. La promesse |

### La grammaire à copier

- Lumière naturelle uniquement, palette chaude, Californie fin d'après-midi
- Faible profondeur de champ partout, caméra à l'épaule, micro-mouvements
- Presque personne ne regarde l'objectif — on observe une vie, on ne la présente pas
- Un seul plan abstrait au milieu, qui porte la couleur de la marque
- Le dernier plan est le seul large et immobile : c'est la chute

---

## La traduction pour Personal

Le titre est « Vous êtes créateur. Pas une machine à contenu. » et la page finit sur « Allez vivre une vie intéressante. Personal fera attention pour vous. »

Le film doit donc raconter **une vie de créateur, pas un écran de créateur**. Même arc que Lassie : on entre, on voit la corvée, on voit le métier, on respire, on rend la vie.

| # | Durée | Plan | Ce que ça dit |
|---|---|---|---|
| 1 | 3 s | De dos, un créateur sort de chez lui au petit matin, café à la main, téléphone dans la poche — il ne filme rien | On entre dans sa journée |
| 2 | 3 s | Macro : le pouce scrolle un feed la nuit, visage éclairé par l'écran, regard vide | La machine à contenu. Le truc qu'il déteste |
| 3 | 4 s | Deux personnes à une table de café, l'une rit au milieu d'une phrase, l'autre écoute vraiment | La vraie matière. Ce qui deviendra un post |
| 4 | 3 s | Abstrait : une silhouette derrière un rideau au soleil, bandes chaudes ivoire et olive | La respiration, et la couleur de la marque |
| 5 | 4 s | Golden hour, large et fixe : il marche avec des amis sur un toit, téléphone rangé | La vie rendue |

**Specs de sortie** — 1920 × 1080, h264, muet, boucle propre (le dernier plan doit pouvoir enchaîner sur le premier), 15 à 17 s, viser 8 Mo. Aucun texte, aucune UI, aucun logo dans l'image.

**Casting** — un seul visage récurrent sur les plans 1, 2 et 5, comme Lassie garde son médecin. Les plans 3 et 4 peuvent être d'autres personnes.

---

## Trois façons de le produire

| Route | Coût | Délai | Le vrai risque |
|---|---|---|---|
| **Stock** (Artgrid, Filmsupply, Pexels pour tester) | 0 à 300 € | une soirée | Trouver cinq plans qui semblent tournés le même jour par la même personne. C'est là que ça se joue |
| **IA** (Higgsfield, Veo, Kling) | crédits | quelques heures | Cinq plans de 3-4 s à générer séparément puis à monter. Le plan 3 — deux personnes qui se parlent vraiment — est celui que l'IA rate le plus souvent. Le même visage sur trois plans demande une image de référence |
| **Tournage** | 1 500 à 4 000 € | 1 jour + montage | Le seul qui donne exactement les cinq plans, et le seul où le visage est vraiment le tien |

Pour un hero qui porte toute la page, la route stock est le meilleur rapport risque/résultat : Lassie a tourné, mais leurs cinq plans sont tous trouvables en stock.
