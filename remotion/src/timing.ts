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
 * How long each shot runs. Cut down twice from the first assembly: every shot
 * now leaves only as long as it takes to read what is on it after the last
 * thing has arrived, rather than sitting on a settled frame. The two shots with
 * the most words on screen — Discover's list and Write's draft — keep the
 * longest tails, because those are the frames a viewer actually has to read.
 */
const durations = {
  coldOpen: 88,
  claim: 92,
  wipe: 42,
  understand: 138,
  discover: 190,
  connect: 158,
  write: 168,
  endCard: 100,
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
    matrixFadeIn: 5,
    /** Frames at which each particle ignites. Deliberately off-rhythm. */
    ignitions: [8, 17, 28, 37] as const,
    /**
     * The category line. It arrives after the last ignition has held a beat,
     * sits still long enough to be read, and is gone before the claim enters —
     * the two statements never share the screen.
     */
    category: 44,
    categoryOut: 76,
    categoryOutDuration: 12,
  },
  claim: {
    lineOne: 4,
    lineTwo: 13,
    subtitle: 30,
  },
  wipe: {
    start: 2,
    duration: 34,
  },
  understand: {
    heading: 4,
    card: 12,
    /** The account arrives first: this is the thing being connected. */
    account: 14,
    connected: 22,
    /** The read itself — a count and a rail, running to 40. */
    readStart: 28,
    readDuration: 24,
    /** Then what it understood, one line at a time. */
    firstRow: 56,
    rowStagger: 13,
    /** How long a value takes to reveal itself, character by character. */
    rowReveal: 10,
  },
  discover: {
    heading: 4,
    card: 12,
    barsGrow: 20,
    barsGrowDuration: 22,
    outlierClimb: 46,
    outlierClimbDuration: 28,
    /** Snaps in once the climb has topped out, and then the shot holds. */
    badge: 78,
    /**
     * The chart leaves and the list arrives across the same frames, so there is
     * never a frame where the card is empty.
     */
    collapse: 116,
    collapseDuration: 14,
    firstRow: 118,
    rowStagger: 9,
    footnote: 152,
  },
  connect: {
    heading: 4,
    cardsEnter: 10,
    cardsTravel: 38,
    /** The join: hairline draws, then both cards settle half a pixel closer. */
    join: 58,
    joinDuration: 20,
    settle: 86,
    /** Air. Nothing new arrives between here and the cut. */
    hold: 112,
  },
  write: {
    heading: 4,
    resolve: 8,
    chips: 20,
    hook: 30,
    beatsStart: 48,
    beatStagger: 13,
    caption: 94,
    stamp: 120,
  },
  endCard: {
    lift: 0,
    liftDuration: 20,
    mark: 12,
    markDuration: 24,
    /**
     * Each of these is a spring entrance, so it settles 30 frames after it
     * starts. The last one starts at 50 and is finished at 80 — which is what
     * buys the final 20 frames of complete stillness.
     */
    wordmark: 30,
    line: 40,
    url: 50,
    stillFrom: 80,
  },
} as const;
