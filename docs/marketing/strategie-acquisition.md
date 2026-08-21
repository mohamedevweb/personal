# Personal — Stratégie marketing & acquisition

*Version 1 · 21 août 2026 · horizon 90 jours*

---

## 0. Le point de départ, sans filtre

| | État réel |
|---|---|
| **Produit** | Backend + landing en prod (`usepersonal.app`). Onboarding Instagram réel, Creator DNA, Moments, Remix opérationnels. For You feed / Why it works / Remix = les 3 chantiers encore ouverts |
| **Données** | Catalogue FR de 30 créateurs, 6 verticales, mesurés par ScrapeCreators. Scoring outlier déterministe et explicable |
| **Audience** | 0. Pas de liste, pas de compte créateur, pas de communauté |
| **Budget** | ~30 €/mois (NDD + VPS) + coûts API. Aucun budget média |
| **Équipe** | 1 personne, qui code aussi |
| **Concurrence** | 1of10, Vamos, Fastlane, Kleo, Socialinsider. Aucun ne relie la niche à **la vie** du créateur |

Trois contraintes structurent tout ce qui suit : **pas d'audience, pas d'argent, pas de temps.** Toute tactique qui suppose l'un des trois est hors sujet ce trimestre.

**La contrainte cachée, et c'est la plus grave :** l'app Meta est en mode développement. Tant que l'App Review n'est pas passée, **seuls les comptes ajoutés manuellement comme testeurs peuvent connecter Instagram.** Cela plafonne l'acquisition à ~25 personnes et ajoute une friction énorme au milieu du funnel ("envoie-moi ton handle, j'te whiteliste"). Ce n'est pas un détail technique : c'est le goulot d'étranglement n°1 de l'acquisition. Voir §13.

---

## 1. Positionnement

**Catégorie revendiquée :** le système d'exploitation de la marque personnelle. Pas un générateur de contenu.

**La phrase (externe, une seule) :**
> Personal trouve ce qui marche dans votre niche et le réécrit avec votre vie à vous.

**La phrase (interne, celle qui guide les arbitrages) :**
> Tout le monde sait générer du texte. Personne ne sait quoi dire. Personal résout le *quoi*, pas le *comment*.

**Les trois piliers de différenciation**, par ordre de force :

1. **Les Moments.** Le concurrent part d'un prompt vide. Personal part de ce qui vous est réellement arrivé. C'est le seul avantage qu'un modèle plus gros ne rattrape pas — c'est de la donnée, pas de l'intelligence.
2. **L'outlier relatif.** Un post ne compte pas parce qu'il vient d'un gros compte, mais parce qu'il bat **la médiane de son propre auteur**. C'est honnête, c'est explicable, et ça se démontre en un screenshot. Aucun concurrent grand public ne raisonne comme ça.
3. **Le refus de publier à votre place.** Contre-positionnement assumé face aux outils d'automatisation. « Personal rédige, vous décidez » est un argument de confiance, pas une limitation.

**Ce qu'on arrête de dire :** « IA », « génération », « automatisation », « gain de temps ». Ces mots rangent Personal dans la catégorie des 400 wrappers ChatGPT. On dit : *trouver*, *comprendre*, *relier*, *se souvenir*.

**L'ennemi désigné** (il en faut un, c'est ce qui rend le message mémorisable) : le contenu IA générique. Le post qui aurait pu être écrit par n'importe qui. Angle éditorial récurrent : *« L'IA qui invente du contenu ne marchera jamais. L'IA qui se souvient de votre vie, oui. »*

---

## 2. ICP — et une distinction qui change tout

### 2.1 Le catalogue n'est pas la liste de prospects

C'est la confusion à éviter. Les 30 créateurs du catalogue (tiboinshape, lenamahfouf, cedricgrolet…) sont un **dataset côté offre** : ils alimentent le feed. Ils ne sont pas des clients. Ils ont des équipes, des agences, aucune douleur, et ne répondent pas aux DM.

- **Supply side** = le catalogue. On l'étend de 30 → 60 → 120 avec `personal:discover-creator-candidates`. Objectif : que le feed soit riche.
- **Demand side** = une liste totalement différente, à construire. Objectif : que quelqu'un s'active.

### 2.2 L'ICP réel

**Créateur ou créateur-entrepreneur francophone, 3 000 – 40 000 abonnés Instagram, qui poste au moins 3×/semaine, et qui monétise déjà quelque chose.**

Pourquoi ces bornes :

- **< 3 000 abonnés** : pas assez d'historique pour que le Creator DNA soit impressionnant, et surtout aucune douleur économique. Il ne paiera jamais.
- **> 40 000 abonnés** : il a déjà un monteur, un ghostwriter ou une agence. Personal remplace un humain qu'il aime bien — vente longue, produit pas prêt.
- **Poste < 3×/semaine** : la douleur « quoi poster demain » n'existe pas. Personal résout un problème qu'il n'a pas.
- **Ne monétise rien** : pas de budget, et pas d'enjeu. La rétention sera nulle.

**Le sous-segment prioritaire (là où je concentre 70 % des DM) :** le **créateur-entrepreneur** — coach, consultant, fondateur de petite marque, restaurateur, salle de sport, agence. Raison : il a des choses à raconter (sa vie *est* son métier), il a un budget, il mesure son ROI en clients et pas en likes, et il est joignable ailleurs que sur Instagram. C'est aussi le persona « SaaS creators / founder-led marketing » déjà identifié dans le dashboard — je le remonte de priorité *haute* à priorité *n°1*.

### 2.3 Le filtre de qualification non négociable

> **La niche du prospect doit être couverte par le catalogue.**

Six verticales, et rien d'autre : `sport-fitness`, `food-cooking`, `personal-branding`, `tech-ai`, `beauty-fashion`, `wellness`. FR uniquement.

Un créateur hors catalogue voit un feed vide ou hors-sujet, et l'activation est ratée avant d'avoir commencé. Le README le dit lui-même : *« il n'y a délibérément pas de fallback »*. Mieux vaut 10 créateurs dans la cible que 40 dont 30 voient un écran vide. Ce filtre s'assouplira quand le catalogue passera à 120 créateurs.

### 2.4 Anti-ICP

Agences, community managers, marques sans visage, comptes de mèmes/repost, e-commerce dropshipping, et tout compte non francophone. Poliment déclinés jusqu'au T2.

---

## 3. Ce qu'on mesure : l'activation avant tout

La définition d'activation de la roadmap est bonne, je la garde et j'y ajoute une 6ᵉ étape — parce que les cinq premières se terminent dans l'app, et que la seule preuve qui compte se produit dehors.

**Un créateur est activé quand :**
1. il connecte son compte
2. Personal comprend son profil (et il valide ce qu'il lit)
3. il trouve une opportunité de contenu
4. il clique sur **Remix for me**
5. il obtient un contenu publiable
6. **il publie.**

L'étape 6 exige un bouton produit qui n'existe pas encore : **« J'ai publié ça »** sur un draft. Trois lignes de code, et c'est le moteur de toute la preuve sociale du trimestre — le compteur « X contenus publiés grâce à Personal », les études de cas, et le signal de rétention le plus fiable dont on disposera. À shipper cette semaine.

**Les événements à instrumenter (PostHog), dans l'ordre :**

```
signup → instagram_connected → dna_ready → dna_confirmed → feed_viewed
→ opportunity_opened → why_it_works_read → remix_clicked → draft_generated
→ draft_copied → draft_published → returned_d7
```

**La métrique nord :** nombre de créateurs ayant atteint `draft_published` au moins deux semaines de suite. Rien d'autre. Pas les signups, pas le trafic, pas les impressions.

**Les seuils de décision :**

| Horizon | Seuil | Si atteint | Si non atteint |
|---|---|---|---|
| J+14 | 10 activés (étape 5) | On continue | Le problème est le produit, pas l'acquisition. On arrête les DM et on répare |
| J+30 | 5 en rétention S2 | On ouvre les canaux publics | On refait 20 interviews avant d'écrire une ligne de plus |
| J+60 | 3 « je paierais » spontanés | On construit le paiement | On n'a pas encore trouvé le vrai problème |

---

## 4. Les canaux, priorisés

| # | Canal | Coût | Délai 1ᵉʳ résultat | Ce qu'on en attend | Quand |
|---|---|---|---|---|---|
| **1** | **DM 1:1 avec audit personnalisé** | 0 € · 45 min/j | 48 h | Les 10 premiers activés + toute la connaissance client | **Maintenant → J+30** |
| **2** | **Réseau chaud + intros** | 0 € | 24 h | 5-6 activés, taux de conversion 5× le cold | **Maintenant** |
| **3** | **Build in public (X + LinkedIn FR)** | 0 € · 1 h/j | 3-4 semaines | Inbound, crédibilité, premiers ambassadeurs | J+3 → continu |
| **4** | **Le Cas n°1 (mon propre compte IG)** | 0 € · 30 min/j | 6-8 semaines | La preuve produit irréfutable | J+7 → continu |
| **5** | **Ambassadeurs (10) + affiliation** | Gratuité produit | 4-6 semaines | Preuve sociale, distribution empruntée | J+30 |
| **6** | **Lead magnet public (audit sans compte)** | 1 semaine de dev | 6 semaines | Top of funnel scalable, emails | J+45 |
| **7** | **SEO programmatique sur la donnée outlier** | 1-2 semaines de dev | 4-6 mois | Le seul canal composé et défendable | J+60 |
| — | Product Hunt | 0 € | — | *Une seule cartouche. À garder.* | Pas avant 50 users + 5 témoignages |
| — | Paid ads | € | — | Aucun sens sans rétention prouvée | Pas ce trimestre |

**Le canal n°1 est le DM, pas le launch post.** À 0 utilisateur et 0 preuve, un post de lancement ne convertit personne : il n'a ni audience ni social proof pour s'appuyer. Le DM, lui, ne dépend d'aucune des trois contraintes. Le launch post existe quand même dimanche — mais son rôle est de **documenter**, pas d'acquérir.

---

## 5. Playbook n°1 — Le Rapport du matin

C'est le cœur de l'acquisition des 30 prochains jours, et c'est le seul vrai lead magnet dont Personal dispose : **le produit lui-même génère gratuitement un objet de valeur, personnalisé, envoyable en DM.**

### Le principe

On n'envoie pas « salut je lance un outil ». On envoie **le résultat**, avant toute demande. Le créateur reçoit trois choses qu'il ne peut pas obtenir seul :

1. **Son ratio à lui.** « Tes carrousels font 3,2× ta médiane, tes Reels 0,6×. » Il ne le sait pas. Instagram ne le lui dit pas.
2. **Trois outliers de sa niche cette semaine**, avec leur ratio et leur mécanisme (le *Why it works*).
3. **Un remix prêt à poster**, écrit pour lui.

Coût marginal : quelques appels API. Effet : on passe d'un taux de réponse de 3-5 % (DM générique) à 20-30 %.

### Les scripts

**DM froid — créateur 3k-40k, dans une des 6 verticales**

> Salut [Prénom] — je construis un truc qui compare les posts d'une niche à la moyenne de leur propre auteur, et j'ai fait tourner ton compte dedans.
>
> Deux trucs sont sortis : [insight précis et vérifiable, ex. « tes posts où tu montres ton visage font 2,8× ta médiane, ceux avec une citation en fond font 0,5× »].
>
> J'ai aussi les 4 posts qui explosent en [niche] cette semaine. Je te les envoie ? Aucune contrepartie, je cherche juste 5 créateurs pour casser le truc.

Règles : moins de 60 mots, l'insight avant la demande, aucun lien dans le premier message, aucun mot du champ « IA / génération / automatisation ». La demande est une question fermée à laquelle « oui » coûte zéro.

**DM chaud — réseau, connaissances (Said, Mikael, Oussama, Guillaume, Rayane, Newlense, Circuit Lyon…)**

> J'ai passé 3 mois à construire un truc pour les créateurs : ça trouve ce qui marche dans ta niche et le réécrit avec ce que t'as vraiment vécu.
>
> Je cherche 5 personnes pour le casser cette semaine. 20 min avec moi, je te connecte et je te fais tes contenus de la semaine en direct. Tu me dis si c'est nul.
>
> (Et si tu penses à quelqu'un de plus pertinent que toi, je prends l'intro.)

La demande d'intro à la fin est ce qui fait passer une liste de 13 personnes à 30. C'est le meilleur ratio effort/résultat du plan.

**Relance J+3 — sans réponse**

> Pas de souci si c'est pas le moment. Je te laisse le rapport quand même, il est à toi : [3 outliers + le ratio].
>
> Si un jour tu veux la version qui arrive toute seule le matin, je suis là.

Donner sans condition à la relance retourne une partie des non-réponses, et ne coûte rien. Une seule relance. Jamais deux.

**Message d'activation — après un « oui, envoie »**

> Voilà. [screenshot du feed + du remix]
>
> Si tu veux, je te connecte ce soir : 2 min, ça lit tes 40 derniers posts et tu reçois ça tous les matins. Je reste avec toi pendant l'install.

### Le rythme

**10 DM par jour, 5 jours sur 7.** 45 minutes, dont 30 à préparer les audits. Non négociable, y compris les jours où il y a un bug en prod. Le calcul honnête :

| Source | Volume | Réponse | Connexion | Activés |
|---|---|---|---|---|
| Réseau chaud + intros | 30 | 60 % | 30 % | **6-8** |
| Cold ciblé (audit) | 100 | 25 % | 12 % | **4-6** |
| **Total sur 30 jours** | **130** | | | **10-14** |

Autrement dit : **le chaud porte le lancement, le froid porte l'apprentissage.** Les 40-50 contacts prévus dans la roadmap suffisent pour la semaine 1, pas pour l'objectif de 10 activés. Il faut viser 130 sur le mois.

### Le tracking

Un seul tableau (Notion ou Sheet), colonnes : handle · niche · abonnés · source (chaud/froid/intro) · date DM · réponse O/N · audit envoyé · connecté · activé · **verbatim**. La colonne verbatim est la plus précieuse des dix : c'est la matière des landing pages, des posts et du positionnement des six prochains mois.

---

## 6. Playbook n°2 — Founder-led marketing

### Le paradoxe à résoudre

Personal est un outil pour créateurs Instagram, construit par quelqu'un qui n'est pas créateur Instagram. Aucune audience à emprunter, aucune légitimité à afficher.

**La résolution, et c'est le meilleur narratif disponible :**

> « Je n'ai jamais réussi à créer du contenu. J'ai construit l'outil dont j'avais besoin. Je pars de 0 abonné et je vais construire une audience uniquement avec Personal, en public, en publiant tout — les chiffres, les ratios, les échecs. »

Ce récit fait trois choses en même temps : il produit la preuve produit qui manque, il génère du contenu de distribution, et il rend l'échec intéressant. C'est le seul actif marketing gratuit, défendable et impossible à copier.

### Deux terrains, deux rôles

- **X et LinkedIn (FR) — le terrain d'acquisition.** C'est là que le build in public fonctionne sans audience préalable : l'algorithme y récompense le contenu, pas le compte. Cible : fondateurs, créateurs-entrepreneurs, early adopters. C'est aussi là que se trouve le sous-segment prioritaire.
- **Instagram — le terrain de la preuve.** Pas pour acquérir (trop lent, il faut de l'audience), mais parce que c'est le seul endroit où « je me suis construit une audience avec mon outil » est démontrable. Chaque post publié est une capture d'écran de plus.

### Les cinq piliers éditoriaux

| Pilier | Ce que c'est | Fréquence | Pourquoi |
|---|---|---|---|
| **La donnée** | « J'ai analysé 400 posts fitness FR. Voilà ce qui bat la moyenne. » | 1×/sem | Le plus partageable. Personne d'autre n'a cette donnée |
| **Le build** | Ce que j'ai cassé, ce que ça m'a coûté, ce que j'ai appris | 2×/sem | Attire les fondateurs, crée la sympathie |
| **Le Cas n°1** | Mon compte, semaine par semaine, chiffres bruts | 1×/sem | La preuve. Et le feuilleton fait revenir |
| **Les créateurs** | Ce que [X] a publié grâce à Personal, et ce que ça a fait | 1×/sem dès J+21 | Preuve sociale — le seul contenu qui vend vraiment |
| **L'opinion** | « L'IA qui invente du contenu ne marchera jamais » | 1×/2 sem | Positionne la catégorie, crée le débat |

Le pilier **Donnée** est très sous-exploité et c'est le plus fort. La base contient déjà des outliers mesurés et explicables par niche. Un post hebdomadaire « Les 5 posts qui ont le plus surperformé en [verticale] cette semaine, et pourquoi » est : gratuit à produire (une requête), impossible à copier, utile même sans le produit, et il alimente directement le SEO programmatique du §8.

### La règle du dogfooding

**Chaque contenu publié doit avoir été produit avec Personal.** Le launch post de dimanche compris — et il faut le dire dans le post. « Ce post a été écrit avec l'outil que ce post présente » est l'argument le plus court et le plus convaincant qui existe.

---

## 7. Playbook n°3 — Ambassadeurs & Concierge

### Le Concierge (semaines 1 à 6)

Pour chacun des 10 premiers activés, **chaque lundi matin, un DM manuel** : 3 opportunités de leur niche + 1 remix prêt à poster. À la main s'il le faut.

C'est du « do things that don't scale » assumé, et ça remplit trois fonctions à la fois : ça compense un produit qui n'est pas encore assez bon pour retenir seul, ça crée un rituel hebdomadaire (l'ancrage de rétention), et ça fait dire au créateur ce qu'il pense en direct. 10 personnes × 15 min = 2h30 par semaine. Le meilleur investissement du trimestre.

Le signal à guetter : le jour où un créateur écrit *« t'as pas envoyé le truc ce matin ? »*, il y a un produit.

### Les 10 ambassadeurs (à partir de J+30)

Recrutés parmi les activés qui ont publié au moins 4 fois. Profil idéal : créateur qui **enseigne la création de contenu** à sa communauté (type Jun Yuh) — son audience est exactement l'ICP, et parler d'outils est déjà son sujet.

**La contrepartie — et il faut être lucide : « accès gratuit » ne vaut rien quand le produit est gratuit pour tout le monde.** Le deal réel :

- Gratuité **à vie**, garantie par écrit, même après le lancement du payant
- **30 % récurrent** sur chaque abonnement référé (à activer avec le paiement)
- Leur nom et leur compte sur la landing page — de la visibilité, pas juste une remise
- Un call hebdo de 30 min avec le fondateur : ils voient la roadmap, ils l'orientent
- **Le concierge premium** : leurs contenus de la semaine préparés à la main

En échange, une seule chose demandée : **1 contenu par mois** où Personal apparaît naturellement (pas un placement — un « voilà comment je trouve mes idées »). Un contenu par mois × 10 ambassadeurs = 10 preuves sociales mensuelles avec de l'audience empruntée.

---

## 8. Les boucles de croissance

Quatre boucles, par ordre de mise en place. Une boucle est un mécanisme qui produit de l'acquisition **sans effort marginal** — c'est ce qui remplace le budget qu'on n'a pas.

**Boucle 1 — Le referral au moment de la valeur (J+30).**
Pas au signup. Juste après `draft_copied`, quand la valeur vient d'être ressentie : *« Un créateur que tu connais galère avec ça ? Invite-le, vous passez tous les deux en illimité. »* Le timing est tout : demander au signup c'est demander avant d'avoir donné.

**Boucle 2 — Le dogfooding (déjà en cours).**
Personal produit le contenu qui vend Personal, qui amène des créateurs, dont les résultats deviennent du contenu. Cette boucle est déjà armée et ne coûte rien — elle demande juste de la discipline de publication.

**Boucle 3 — Le lead magnet public (J+45).**
Une page publique : « Colle ton @, reçois ton rapport. » Le Creator DNA + le ratio par format + 3 outliers de la niche, sans créer de compte, contre un email. C'est le haut de funnel scalable — le DM du §5, industrialisé. À ne construire qu'une fois l'App Review passée, sinon on collecte des emails qu'on ne peut pas convertir.

**Boucle 4 — Le SEO programmatique (J+60, effet à 6 mois).**
C'est le canal composé que la base de données rend possible et que personne d'autre ne peut copier. Des pages générées et rafraîchies chaque semaine :

- `/outliers/fitness` — « Les posts Instagram qui surperforment en fitness cette semaine »
- `/creators/[handle]` — la fiche publique d'un créateur du catalogue, avec ses ratios
- `/niches/[niche]/formats` — quel format marche dans quelle niche

Contenu unique, factuel, régénéré automatiquement, adossé à une donnée propriétaire. C'est exactement ce qui a construit 1of10. Six mois de latence, mais c'est le seul canal qui travaille encore quand on dort.

---

## 9. Pricing & monétisation

**Ne rien facturer avant d'avoir entendu trois « je paierais » spontanés.** Le prix testé trop tôt mesure la politesse, pas la valeur.

**L'ancrage à utiliser dans les conversations :** un ghostwriter FR coûte 800 à 2 000 €/mois. Un monteur, 500 €. Le prix de Personal doit être manifestement en dessous du « je paie quelqu'un », et clairement au-dessus du « encore un abonnement IA à 9 € » — parce qu'à 9 €, on est rangé dans la catégorie wrapper et on perd le positionnement.

| Plan | Prix | Contenu |
|---|---|---|
| **Free** | 0 € | Le feed du matin, 3 remix/mois. Le feed seul justifie de revenir — et c'est ce qui fait le retour quotidien |
| **Creator** | **29 €/mois** | Remix illimités, Moments illimités, 1 compte |
| **Pro** | **79 €/mois** | 3 comptes, crédits image/vidéo, priorité support |

29 € est le point où un créateur qui monétise déjà ne réfléchit pas. Les crédits image/vidéo restent hors abonnement (coût variable réel) — c'est le modèle déjà prévu, il est bon.

**Le levier d'urgence, à annoncer dès maintenant :** *« Les 50 premiers créateurs gardent 50 % à vie. »* Ça donne une raison de s'inscrire aujourd'hui plutôt que dans trois mois, ça récompense les premiers, ça crée un palier qui se raconte (« il reste 12 places »), et ça ne coûte rien tant que rien n'est facturé.

---

## 10. Le plan

### J-2 → J+2 : le lancement (21-23 août)

**Priorité absolue, avant tout le reste : soumettre l'App Review Meta.** Chaque jour de retard est un jour de plafond à 25 comptes.

| | |
|---|---|
| **Vendredi** | App Review soumise · liste de 30 prospects qualifiés (6 verticales, 3k-40k) · 13 DM chauds envoyés au réseau · bouton « J'ai publié » shippé |
| **Samedi** | Produire un vrai contenu **avec** Personal et le publier · capturer la chaîne complète insight → output · finaliser les screenshots · écrire le launch post |
| **Dimanche** | Launch post (X + LinkedIn + story IG) · envoi à tous les leads chauds · 10 premiers DM froids avec audit |

**Structure du launch post** — l'ordre compte :
1. Le problème, incarné en une phrase vécue *(« Je voulais devenir créateur. J'ai abandonné au bout de 3 semaines : je ne savais jamais quoi poster. »)*
2. Ce que j'ai construit, en une phrase
3. **Le moment magique, en vidéo ou en 3 captures** — un outlier réel, un Moment réel, un Reel réel. C'est 80 % du post
4. Comment ça marche, en 4 temps (le bloc de la landing)
5. **Ce post a été écrit avec Personal**
6. « Je cherche 10 créateurs. Gratuit, je vous installe moi-même. Commentez ou DM. »

### J+3 → J+30 : les 10 activés

Le seul objectif du mois. Rythme quotidien : **10 DM · 1 post · 1 call créateur.** Rituel du lundi : le Concierge pour tous les activés. Rituel du vendredi : le scoreboard, et un post « la donnée ».

Jalons : J+7 → 3 activés et 3 interviews · J+14 → 10 activés (sinon on arrête l'acquisition et on répare le produit) · J+21 → premiers verbatims sur la landing · J+30 → 5 en rétention S2.

### J+31 → J+60 : la preuve

Recrutement des 10 ambassadeurs · première étude de cas chiffrée · boucle de referral en production · lead magnet public en développement · le Cas n°1 devient un feuilleton hebdo · premières conversations de prix.

### J+61 → J+90 : les canaux composés

Paiement en ligne + grandfathering des 50 premiers · affiliation ambassadeurs à 30 % · SEO programmatique en ligne · catalogue étendu à 120 créateurs (ce qui lève enfin le filtre de qualification) · **et seulement là, Product Hunt**, avec 50 users, 5 témoignages et une démo qui tient.

---

## 11. Le scoreboard

Un tableau, relevé chaque vendredi. Cinq lignes, pas quinze.

| Métrique | S1 | S2 | S3 | S4 | Cible J+30 |
|---|---|---|---|---|---|
| Créateurs contactés | | | | | 130 |
| Taux de réponse | | | | | > 20 % |
| **Créateurs activés (étape 5)** | | | | | **10** |
| **Contenus publiés via Personal** | | | | | **30** |
| **Rétention S2** | | | | | **5** |

Ce qu'on ne mesure pas ce trimestre : visiteurs, impressions, followers, signups. Ce sont des métriques de vanité tant qu'il n'y a pas 10 personnes qui reviennent.

---

## 12. Ce qu'on ne fait pas

Product Hunt maintenant · publicité payante · TikTok / LinkedIn / X en intégration produit · marketplace et clones de créateurs · features agences · blog SEO classique (rédigé à la main, sans données) · communauté Discord · newsletter · webinaires · cold email · prospection hors des 6 verticales · prospection hors FR.

Chacune de ces lignes est une bonne idée. Aucune ne sert les 10 premiers activés. La règle de la roadmap s'applique au marketing exactement comme au produit : *tout ce qui ne sert pas le core loop ou les premiers utilisateurs = pas ce trimestre.*

---

## 13. Risques

| # | Risque | Impact | Ce qu'on fait |
|---|---|---|---|
| **1** | **Meta App Review non obtenue** — acquisition plafonnée à ~25 comptes testeurs, friction majeure au milieu du funnel | **Bloquant** | Soumettre **cette semaine**. En attendant : dire la vérité dans le DM (« je t'ajoute à la main, 2 min ») et en faire un argument d'exclusivité plutôt qu'une excuse |
| **2** | **Feed pauvre à l'activation** — 30 créateurs FR, 6 verticales, pas de fallback par design | Élevé | Filtre de qualification strict (§2.3) · étendre à 120 créateurs · vérifier le feed **avant** d'envoyer l'invitation, jamais après |
| **3** | **Coût scraping/LLM par utilisateur** non borné à mesure que les comptes arrivent | Moyen | Les cooldowns et batches existent déjà. Calculer le coût réel par créateur actif dès le 5ᵉ, avant d'ouvrir plus grand |
| **4** | **Rétention nulle après la nouveauté** — le feed est consulté une fois puis oublié | Élevé | Le Concierge du lundi crée le rituel à la main pendant que le produit apprend à le créer seul. Un email/DM quotidien du feed est la vraie feature de rétention |
| **5** | **Le fondateur n'a pas d'audience** — le build in public met 6 semaines à produire quoi que ce soit | Moyen | C'est précisément pourquoi le canal n°1 est le DM. Le contenu est un investissement à moyen terme, pas le plan de lancement |
| **6** | **Un concurrent financé copie les Moments** | Faible ce trimestre | Le moat est la donnée accumulée, pas la feature. Chaque semaine d'usage creuse l'écart — d'où la priorité absolue à la rétention plutôt qu'au volume |

---

## 14. Si je ne devais retenir que cinq choses

1. **Soumettre l'App Review Meta aujourd'hui.** Tout le reste en dépend.
2. **Le catalogue n'est pas la liste de prospects.** Trente créateurs pour remplir le feed, une liste différente pour trouver des utilisateurs.
3. **Ne jamais envoyer un DM sans avoir donné le résultat avant.** L'audit personnalisé fait passer le taux de réponse de 4 % à 25 %.
4. **Dix activés valent mieux que mille visiteurs.** Le mois se juge sur `draft_published`, pas sur les signups.
5. **Devenir le Cas n°1.** Zéro abonné, une audience construite uniquement avec Personal, en public. C'est le seul actif marketing gratuit que personne ne peut copier.
