import {Easing, interpolate, spring} from 'remotion';
import {bezier} from './theme';

/**
 * One spring for the whole film. Damping is high enough that nothing overshoots:
 * things arrive, they do not bounce.
 */
export const ENTER_SPRING = {damping: 200, stiffness: 90} as const;

export const ease = Easing.bezier(bezier[0], bezier[1], bezier[2], bezier[3]);

/**
 * The house curve arrives almost immediately, which is right for a card sliding
 * into place and wrong for a value climbing: it would cross 2× and 4× in the
 * first three frames and the crossing is the whole point. This one starts at
 * pace and eases out at the top.
 */
export const climbEase = Easing.out(Easing.cubic);

/**
 * The wipe. The house curve is almost finished in its first third, which is
 * right for a card arriving and wrong for an edge crossing 1920px: it would
 * cover the frame in half a second and leave the rest of the shot holding on
 * blank paper. This one accelerates and lands, using the whole cut.
 */
export const wipeEase = Easing.inOut(Easing.quad);

/** A 0→1 entrance value. Used for opacity, translate and scale on arrival. */
export const enter = ({
  frame,
  fps,
  delay = 0,
}: {
  frame: number;
  fps: number;
  delay?: number;
}) => spring({frame, fps, delay, config: ENTER_SPRING, durationInFrames: 30});

/** A 0→1 travel value on the house curve, clamped at both ends. */
export const travel = ({
  frame,
  start,
  duration,
  easing = ease,
}: {
  frame: number;
  start: number;
  duration: number;
  easing?: (input: number) => number;
}) =>
  interpolate(frame, [start, start + duration], [0, 1], {
    easing,
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

/** A plain fade, clamped. Linear on purpose — light does not need a curve. */
export const fade = ({
  frame,
  start,
  duration,
  from = 0,
  to = 1,
}: {
  frame: number;
  start: number;
  duration: number;
  from?: number;
  to?: number;
}) =>
  interpolate(frame, [start, start + duration], [from, to], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

/**
 * Deterministic pseudo-random in [0, 1). Every frame must render identically in
 * isolation, so nothing in this project may call Math.random().
 */
export const seeded = (seed: number) => {
  const x = Math.sin(seed * 127.1 + 311.7) * 43758.5453;
  return x - Math.floor(x);
};
