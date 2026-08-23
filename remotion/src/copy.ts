/**
 * Every word in the film. A French cut is a change to this file and nothing
 * else — scenes read from `copy`, never from a string literal.
 */

export type UnderstandRow = {label: string; value: string};

export type OutlierRow = {
  title: string;
  views: string;
  multiple: string;
  handle: string;
  age: string;
};

export type Copy = {
  coldOpen: {
    /** The category, stated once, before anything is argued. */
    category: string;
  };
  claim: {
    lineOneBefore: string;
    lineOneLit: string;
    lineOneAfter: string;
    lineTwo: string;
    subtitle: string;
  };
  understand: {
    step: string;
    title: string;
    body: string;
    /** The account being connected. */
    handle: string;
    /** Stamped once the connection is made. */
    connected: string;
    /** While the read runs. `{n}` is replaced by the running count. */
    reading: string;
    /** And once it has finished. */
    cardLabel: string;
    rows: UnderstandRow[];
  };
  discover: {
    step: string;
    title: string;
    body: string;
    chartCaption: string;
    baseline: string;
    badgeValue: string;
    badgeLabel: string;
    outliers: OutlierRow[];
    footnote: string;
  };
  connect: {
    step: string;
    title: string;
    body: string;
    patternLabel: string;
    patternQuote: string;
    momentLabel: string;
    momentQuote: string;
  };
  write: {
    step: string;
    title: string;
    body: string;
    formats: string[];
    activeFormat: string;
    hook: string;
    beats: string[];
    captionLabel: string;
    caption: string;
    stamp: string;
  };
  endCard: {
    wordmark: string;
    line: string;
    url: string;
  };
};

const en: Copy = {
  coldOpen: {
    category: 'Claude for personal brand.',
  },
  claim: {
    /** The word that takes the signature red. It is the only red in the shot. */
    lineOneBefore: 'You’re a ',
    lineOneLit: 'creator',
    lineOneAfter: '.',
    /** Set in italic ivory. The turn takes the italic, never a colour. */
    lineTwo: 'Not a content machine.',
    subtitle:
      'Personal finds what’s working in your niche and rewrites it with your life. Your voice, your week, one minute.',
  },

  understand: {
    step: '01',
    title: 'Understand',
    body: 'Connect Instagram once. Personal reads your last 40 posts and tells you, in writing, what it understood.',
    handle: '@mohamed.chettahh',
    connected: 'Connected',
    reading: 'Reading {n} of 40',
    cardLabel: '40 posts read',
    rows: [
      {label: 'Niche', value: 'Independent product building'},
      {label: 'Audience', value: 'Solo founders, 22 to 34'},
      {label: 'Tone', value: 'Direct · honest · deadpan'},
      {label: 'Positioning', value: 'Builds in public, shows the mess'},
    ],
  },

  discover: {
    step: '02',
    title: 'Discover',
    body: 'Every morning, the posts beating their own creator’s average — in your niche, at your size, while they are still climbing.',
    chartCaption: 'One account · last 14 posts, indexed to its own average',
    baseline: 'Its average',
    badgeValue: '8.4×',
    badgeLabel: 'Outlier detected',
    outliers: [
      {
        title: 'The pricing mistake that cost me 8 months',
        views: '352K',
        multiple: '8.4×',
        handle: '@founderframes',
        age: '2d',
      },
      {
        title: 'Nobody tells you this about your first 1,000',
        views: '180K',
        multiple: '5.1×',
        handle: '@lena.builds',
        age: '4d',
      },
      {
        title: 'I rebuilt my whole onboarding in a weekend',
        views: '121K',
        multiple: '3.7×',
        handle: '@marc.oss',
        age: '5d',
      },
    ],
    footnote:
      'Not the biggest post on Instagram. The one beating the account that published it.',
  },

  connect: {
    step: '03',
    title: 'Connect',
    body: 'The format that is working right now, held against a story you already lived.',
    patternLabel: 'Pattern in your niche',
    patternQuote: '“The decision I almost didn’t make…”',
    momentLabel: 'Your week',
    momentQuote:
      '“I almost turned down my first €10k client because I didn’t feel ready.”',
  },

  write: {
    step: '04',
    title: 'Write',
    body: 'Reel, carousel or caption. Your structure, your voice, your story.',
    formats: ['Reel', 'Carousel', 'Caption'],
    activeFormat: 'Reel',
    hook: 'I almost said no to the biggest client I’d ever had.',
    beats: [
      'The email that sat unopened for two days',
      'What I told myself I wasn’t ready for',
      'What saying yes actually cost — and paid',
    ],
    captionLabel: 'Caption',
    caption:
      'Ready is a story you tell yourself after. I said yes at 40% confidence and learned the other 60% on the job.',
    stamp: 'A draft, never a post.',
  },

  endCard: {
    wordmark: 'Personal',
    line: 'Content only you could post.',
    url: 'usepersonal.app · free during early access',
  },
};

const fr: Copy = {
  coldOpen: {
    category: 'Claude pour la marque personnelle.',
  },
  claim: {
    lineOneBefore: 'Tu es ',
    lineOneLit: 'créateur',
    lineOneAfter: '.',
    lineTwo: 'Pas une machine à contenu.',
    subtitle:
      'Personal trouve ce qui marche dans ta niche et le réécrit avec ta vie. Ta voix, ta semaine, une minute.',
  },
  understand: {
    step: '01',
    title: 'Comprendre',
    body: 'Connecte Instagram une fois. Personal lit tes 40 derniers posts et t’écrit ce qu’il a compris.',
    handle: '@mohamed.chettahh',
    connected: 'Connecté',
    reading: 'Lecture de {n} sur 40',
    cardLabel: '40 posts lus',
    rows: [
      {label: 'Niche', value: 'Création de produits indépendants'},
      {label: 'Audience', value: 'Fondateurs solo, 22 à 34 ans'},
      {label: 'Ton', value: 'Direct · honnête · pince-sans-rire'},
      {label: 'Positionnement', value: 'Construit en public, montre le bazar'},
    ],
  },
  discover: {
    step: '02',
    title: 'Découvrir',
    body: 'Chaque matin, les posts qui battent la moyenne de leur propre créateur — dans ta niche, à ta taille, pendant qu’ils montent encore.',
    chartCaption: 'Un compte · 14 derniers posts, indexés sur sa propre moyenne',
    baseline: 'Sa moyenne',
    badgeValue: '8,4×',
    badgeLabel: 'Outlier détecté',
    outliers: [
      {
        title: 'L’erreur de prix qui m’a coûté 8 mois',
        views: '352K',
        multiple: '8,4×',
        handle: '@founderframes',
        age: '2j',
      },
      {
        title: 'Personne ne te dit ça sur tes 1 000 premiers',
        views: '180K',
        multiple: '5,1×',
        handle: '@lena.builds',
        age: '4j',
      },
      {
        title: 'J’ai refait tout mon onboarding en un week-end',
        views: '121K',
        multiple: '3,7×',
        handle: '@marc.oss',
        age: '5j',
      },
    ],
    footnote:
      'Pas le plus gros post d’Instagram. Celui qui bat le compte qui l’a publié.',
  },
  connect: {
    step: '03',
    title: 'Relier',
    body: 'Le format qui marche en ce moment, tenu contre une histoire que tu as déjà vécue.',
    patternLabel: 'Format qui marche dans ta niche',
    patternQuote: '« La décision que j’ai failli ne pas prendre… »',
    momentLabel: 'Ta semaine',
    momentQuote:
      '« J’ai failli refuser mon premier client à 10 k€ parce que je ne me sentais pas prêt. »',
  },
  write: {
    step: '04',
    title: 'Écrire',
    body: 'Reel, carrousel ou légende. Ta structure, ta voix, ton histoire.',
    formats: ['Reel', 'Carrousel', 'Légende'],
    activeFormat: 'Reel',
    hook: 'J’ai failli dire non au plus gros client de ma vie.',
    beats: [
      'L’e-mail resté fermé pendant deux jours',
      'Ce pour quoi je me croyais pas prêt',
      'Ce que dire oui m’a coûté — et rapporté',
    ],
    captionLabel: 'Légende',
    caption:
      'Être prêt, c’est une histoire qu’on se raconte après. J’ai dit oui à 40 % de confiance et j’ai appris les 60 % restants en route.',
    stamp: 'Un brouillon, jamais un post.',
  },
  endCard: {
    wordmark: 'Personal',
    line: 'Le contenu que toi seul pouvais publier.',
    url: 'usepersonal.app · gratuit pendant l’accès anticipé',
  },
};

export const cuts = {en, fr};

export type CutLanguage = keyof typeof cuts;

export const copyFor = (language: CutLanguage): Copy => cuts[language];
