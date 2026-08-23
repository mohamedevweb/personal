/**
 * The Personal palette, verbatim from the brand system. Two grounds — night for
 * the stage moments, ivory for the product moments — and one red that is
 * rationed to three jobs: the primary action, a live indicator, and the single
 * mark on a chart that is the reason the chart exists.
 *
 * Nothing else in this project may write a hex literal. If a colour is needed
 * and it is not here, it does not belong in the film.
 */

export const palette = {
  night: '#0d0c0a',
  ivory: '#f7f5f0',
  surface: '#fefdfb',
  ink: '#171715',
  muted: '#77736d',
  line: '#e4dfd4',
  signature: '#e04f36',
  redLit: '#ea7a5e',
  gold: '#e3b862',
  positive: '#3f7a55',
} as const;

export type PaletteColor = (typeof palette)[keyof typeof palette];

/**
 * Translucent inks. On night the type is ivory dimmed rather than grey, so the
 * warmth of the ground carries through instead of being covered by it.
 */
export const alpha = {
  onNightPrimary: 'rgba(247, 245, 240, 0.94)',
  onNightSecondary: 'rgba(247, 245, 240, 0.60)',
  onNightFaint: 'rgba(247, 245, 240, 0.34)',
  /** Full strength; the matrix is dimmed by the opacity it is drawn at. */
  matrixDot: 'rgba(247, 245, 240, 1)',
  guideRail: 'rgba(247, 245, 240, 0.05)',
  particle: 'rgba(247, 245, 240, 0.22)',
  cardShadow: 'rgba(23, 23, 21, 0.05)',
  cardShadowLift: 'rgba(23, 23, 21, 0.08)',
  /** The account's own average. Reads across the bars, not under them. */
  baseline: 'rgba(119, 115, 109, 0.45)',
  bar: 'rgba(119, 115, 109, 0.38)',
  chipIdle: 'rgba(119, 115, 109, 0.10)',
  scrim: 'rgba(13, 12, 10, 0)',
} as const;

export const font = {
  /** Display face. Set tight; the turn in a sentence takes the italic. */
  display: '"Instrument Serif", "Iowan Old Style", Georgia, serif',
  /** Body and UI face. */
  body: '"Inter", system-ui, -apple-system, "Helvetica Neue", sans-serif',
} as const;

export const type = {
  displayTracking: '-0.03em',
  displayLeading: 0.98,
  eyebrowTracking: '0.16em',
  bodyLeading: 1.5,
} as const;

export const radius = {
  card: 14,
  chip: 999,
  badge: 8,
} as const;

/**
 * The easing used for anything that travels or wipes. Entrances use springs
 * instead; see `motion.ts` for the single spring configuration.
 */
export const bezier = [0.22, 1, 0.36, 1] as const;

export const theme = {palette, alpha, font, type, radius, bezier} as const;
