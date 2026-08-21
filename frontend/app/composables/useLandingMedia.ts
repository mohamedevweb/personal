/**
 * The launch page is built for motion: every section frames one short, silent
 * product loop. Until a clip has been filmed, its frame falls back to the
 * static mock underneath, so the page is complete either way.
 *
 * Turning a section into video is one line here: drop the file in
 * `public/landing/` and name it below.
 */
export type LandingClip = 'hero' | 'understand' | 'discover' | 'connect' | 'write'

export const LANDING_CLIPS: Record<LandingClip, string | null> = {
  hero: null,
  understand: null,
  discover: null,
  connect: null,
  write: null
}
