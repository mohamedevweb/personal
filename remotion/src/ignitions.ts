import type {Ignition} from './components';
import {beats} from './timing';

/**
 * Which points in the particle field light, and when. Shared by the cold open
 * and the claim so the field carries straight through the cut instead of
 * restarting under it — the ground is one continuous shot even though the copy
 * on top of it changes twice.
 *
 * The indices are fixed rather than sampled, so the lit points are spread across
 * the frame instead of clustering wherever the seed happened to put them.
 */
const PARTICLES = [7, 23, 41, 58] as const;

export const IGNITIONS: readonly Ignition[] = beats.coldOpen.ignitions.map(
  (frame, index) => ({particle: PARTICLES[index] ?? 0, frame}),
);
