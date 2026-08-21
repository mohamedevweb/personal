# Personal — Landing page v2 (copy deck)

Objectif : passer de **10 blocs éditoriaux** à **6 blocs**, chaque section = 1 titre court + 1 ligne + 1 vidéo produit animée (direction meuze.ai / lassie.ai).

Règle de la v2 : *une section ne peut pas dire deux choses.* Si elle a besoin d'un paragraphe pour s'expliquer, c'est la vidéo qui n'est pas bonne.

---

## Structure

| # | Bloc | Rôle | Vidéo |
|---|------|------|-------|
| 1 | Hero | La promesse | Loop produit 6-8 s, muet, autoplay |
| 2 | Stat 8,4× | La preuve | Compteur animé |
| 3 | Comment ça marche (4 temps) | Le mécanisme | 4 clips scroll-synchronisés (sticky) |
| 4 | Moments | Le différenciateur | Capture / note vocale qui devient un Reel |
| 5 | FAQ (4 questions) | Les objections | — |
| 6 | CTA | La sortie | — |

Supprimés : `Understand` / `Outliers` / `Connection` / `Remix` en tant que sections longues (fusionnés dans le bloc 3), `Loop` (redondant avec le bloc 3), 3 questions de FAQ.

---

## 1. Hero

**EN**
> ### You're a creator. Not a content machine.
> Personal finds what's working in your niche and rewrites it with your life.
>
> `[ Get early access → ]`  `[ Watch 40s ]`
>
> Free during early access · Personal never posts for you

**FR**
> ### Vous êtes créateur. Pas une machine à contenu.
> Personal trouve ce qui marche dans votre niche et le réécrit avec votre vie à vous.
>
> `[ Accès anticipé → ]`  `[ Voir en 40 s ]`
>
> Gratuit en accès anticipé · Personal ne publie jamais à votre place

**Vidéo** — plein cadre sous le fold, coins arrondis, ombre douce. Le produit qui travaille : le feed du matin se remplit, une carte outlier s'ouvre, un Moment se colle dessus, un Reel s'écrit. Aucun curseur, aucune UI factice. 6-8 s en boucle.

### Variantes de headline

- **B (recommandée)** — celle ci-dessus. Humaine, elle tient sans la vidéo, elle laisse la vidéo raconter le produit.
- **C — revendication de catégorie (meuze)** : *« Le cerveau de votre marque personnelle. »* / *"The brain behind your personal brand."* Plus ambitieuse, moins chaleureuse.
- **D — angle vie** : *« Vos contenus existent déjà. Dans votre vie. »* / *"Your best posts already happened. To you."*

---

## 2. Stat

**EN** — **8.4×** · Above its own creator's average. In your niche. While it's still climbing.
**FR** — **8,4×** · Au-dessus de la moyenne de son propre créateur. Dans votre niche. Pendant qu'il monte encore.

---

## 3. Comment ça marche

**EN** — ### We learn you by heart. Then we write.
**FR** — ### On vous apprend par cœur. Puis on écrit.

| | EN | FR |
|---|---|---|
| **01 Understand / Comprendre** | Connect Instagram once. Personal reads your last 40 posts and tells you what it understood. | Connectez Instagram une fois. Personal lit vos 40 derniers posts et vous dit ce qu'il a compris. |
| **02 Discover / Trouver** | Every morning, the posts beating their own creator's average — at your size. | Chaque matin, les posts qui battent la moyenne de leur propre créateur, à votre taille. |
| **03 Connect / Relier** | The format that's working, plus the story you already lived. | Le format qui marche, plus l'histoire que vous avez déjà vécue. |
| **04 Write / Écrire** | Reel, carousel or caption. Your voice. Ten minutes. | Reel, carrousel ou légende. Votre voix. Dix minutes. |

**Vidéo** — un seul écran produit sticky à droite, 4 clips qui s'enchaînent au scroll pendant que les 4 temps défilent à gauche.

---

## 4. Moments

**EN**
> ### You live. Personal takes notes.
> A client call, a bad week, a 1am realization. Write it down and forget it — Personal remembers it at the right moment.
>
> *Nothing is content until you say it is.*

**FR**
> ### Vous vivez. Personal prend des notes.
> Un appel client, une mauvaise semaine, une prise de conscience à 1h du matin. Notez-le et oubliez-le — Personal s'en souvient au bon moment.
>
> *Rien n'est un contenu tant que vous ne l'avez pas décidé.*

**Vidéo** — une note vocale de 40 s qui devient une carte Moment, puis, deux semaines plus tard, un Reel.

---

## 5. FAQ — 4 questions

| EN | FR |
|---|---|
| **Will it sound like me?** That's the whole point. Personal learns your tone from your own captions and shows you what it understood before writing a line. | **Est-ce que ça va me ressembler ?** C'est toute l'idée. Personal apprend votre ton à partir de vos propres légendes et vous montre ce qu'il a compris avant d'écrire une ligne. |
| **Another AI content generator?** No. A generator invents from nothing. Personal starts from two things that already exist: what's working in your niche, and what actually happened to you. | **Encore un générateur de contenu IA ?** Non. Un générateur invente à partir de rien. Personal part de deux choses qui existent déjà : ce qui marche dans votre niche, et ce qui vous est vraiment arrivé. |
| **Does Personal post for me?** Never. Personal drafts, you decide. Your Moments stay yours, are never shown to other creators, and you can delete them anytime. | **Est-ce que Personal publie à ma place ?** Jamais. Personal rédige, vous décidez. Vos Moments vous appartiennent, ne sont jamais montrés à d'autres créateurs, et vous pouvez les supprimer à tout moment. |
| **What does it cost?** Nothing during early access. We're onboarding a small group of creators by hand. | **Combien ça coûte ?** Rien pendant l'accès anticipé. Nous accompagnons un petit groupe de créateurs à la main. |

---

## 6. CTA

**EN**
> ### Go live an interesting life.
> Personal will pay attention for you.
> `[ Get early access → ]`
> Free during early access · Personal never posts for you

**FR**
> ### Allez vivre une vie intéressante.
> Personal fera attention pour vous.
> `[ Accès anticipé → ]`
> Gratuit en accès anticipé · Personal ne publie jamais à votre place

---

## Meta

**EN** — Personal — the AI that turns your life into content only you could post. Finds what's working in your niche, connects it to what actually happened to you, writes it in your voice.
**FR** — Personal — l'IA qui transforme votre vie en contenus que vous seul pouviez publier. Trouve ce qui marche dans votre niche, le relie à ce que vous avez vécu, l'écrit dans votre voix.

---

## Implémentation

`app/pages/index.vue` — 6 blocs : `LandingHero` · `LandingStat` · `LandingHow` · `LandingMoments` · `LandingFaq` · `LandingCta`.

**Les vidéos.** Chaque cadre est un `<LandingMedia>` : il affiche un mock statique (`app/components/landing/mock/`) tant qu'aucun clip n'est fourni, et le clip par-dessus dès qu'il existe. Les clips ne tournent que lorsqu'ils sont à l'écran, et jamais en `prefers-reduced-motion`.

Pour activer une vidéo : déposer le fichier dans `frontend/public/landing/` et le nommer dans `app/composables/useLandingMedia.ts` :

```ts
export const LANDING_CLIPS = {
  hero: '/landing/hero.mp4',   // au lieu de null
  understand: null,
  discover: null,
  connect: null,
  write: null
}
```

**Formats à filmer** — muet, en boucle, 6-10 s, sans curseur ni chrome de navigateur :

| Clip | Cadre | Ce qu'on voit |
|---|---|---|
| `hero` | 940 × 456 (~2:1) | Le feed du matin se remplit, une carte outlier s'ouvre, un Moment s'y colle, un Reel s'écrit |
| `understand` | 704 × 432 (~16:10) | Instagram se connecte, le profil se remplit tout seul, une correction en une phrase |
| `discover` | 704 × 432 | Les outliers remontent un par un avec leur ratio |
| `connect` | 704 × 432 | Le motif et le Moment se rejoignent |
| `write` | 704 × 432 | Le Reel s'écrit temps par temps, puis bascule en carrousel |
