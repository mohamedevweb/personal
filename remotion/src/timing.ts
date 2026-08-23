/**
 * Every cut point in the film, in frames at 30fps. This is the only place a
 * duration is written down: scenes ask for their own length, and the running
 * order in LaunchFilm.tsx is assembled from this table. Retune the film here.
 *
 * Durations are the source of truth and the absolute marks are derived from
 * them, so shortening one shot moves everything after it without any other
 * number needing to be touched.
 */

export const FPS = 30;

/**
 * How long each shot runs. These were cut down from the first assembly, which
 * held on settled frames longer than it needed to — every shot now leaves
 * roughly a second after its last arrival rather than two or three.
 */
const durations = {
  coldOpen: 105,
  claim: 118,
  wipe: 52,
  understand: 160,
  discover: 215,
  connect: 205,
  write: 190,
  endCard: 115,
} as const;

export type SceneName = keyof typeof durations;

const order = Object.keys(durations) as SceneName[];

const marks = order.reduce<Record<SceneName, number>>(
  (acc, name, index) => {
    const previous = order[index - 1];
    acc[name] = previous ? acc[previous] + durations[previous] : 0;
    return acc;
  },
  {} as Record<SceneName, number>,
);

export const DURATION_IN_FRAMES = order.reduce((total, name) => total + durations[name], 0);

export const startOf = (name: SceneName) => marks[name];

export const durationOf = (name: SceneName) => durations[name];

export const SCENE_ORDER: ReadonlyArray<SceneName> = order;

/**
 * Beats inside scenes, relative to the scene's own first frame. Named after
 * what happens rather than what moves, so the shot list stays readable.
 */
export const beats = {
  coldOpen: {
    matrixFadeIn: 6,
    /** Frames at which each particle ignites. Deliberately off-rhythm. */
    ignitions: [10, 21, 34, 44] as const,
    /**
     * The category line. It arrives after the last ignition has held a beat,
     * sits still long enough to be read, and is gone before the claim enters —
     * the two statements never share the screen.
     */
    category: 50,
    categoryOut: 90,
    categoryOutDuration: 12,
  },
  claim: {
    lineOne: 6,
    lineTwo: 16,
    subtitle: 36,
  },
  wipe: {
    start: 3,
    duration: 40,
  },
  understand: {
    heading: 4,
    card: 12,
    /** The account arrives first: this is the thing being connected. */
    account: 18,
    connected: 28,
    /** The read itself — a count and a rail, running to 40. */
    readStart: 34,
    readDuration: 28,
    /** Then what it understood, one line at a time. */
    firstRow: 66,
    rowStagger: 16,
    /** How long a value takes to reveal itself, character by character. */
    rowReveal: 12,
  },
  discover: {
    heading: 4,
    card: 12,
    barsGrow: 26,
    barsGrowDuration: 26,
    outlierClimb: 58,
    outlierClimbDuration: 34,
    /** Snaps in once the climb has topped out, and then the shot holds. */
    badge: 96,
    /**
     * The chart leaves and the list arrives across the same frames, so there is
     * never a frame where the card is empty.
     */
    collapse: 132,
    collapseDuration: 16,
    firstRow: 134,
    rowStagger: 10,
    footnote: 176,
  },
  connect: {
    heading: 4,
    cardsEnter: 14,
    cardsTravel: 46,
    /** The join: hairline draws, then both cards settle half a pixel closer. */
    join: 74,
    joinDuration: 24,
    settle: 108,
    /** Air. Nothing new arrives between here and the cut. */
    hold: 140,
  },
  write: {
    heading: 4,
    resolve: 10,
    chips: 26,
    hook: 38,
    beatsStart: 56,
    beatStagger: 16,
    caption: 112,
    stamp: 140,
  },
  endCard: {
    lift: 0,
    liftDuration: 22,
    mark: 14,
    markDuration: 28,
    /**
     * Each of these is a spring entrance, so it settles 30 frames after it
     * starts. The last one starts at 62 and is finished at 92 — which is what
     * buys the final 23 frames of complete stillness.
     */
    wordmark: 38,
    line: 50,
    url: 62,
    stillFrom: 92,
  },
} as const;
