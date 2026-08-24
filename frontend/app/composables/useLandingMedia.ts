/**
 * The launch page is built for motion: every section frames one short, silent
 * product loop. Until a clip has been filmed, its frame falls back to the
 * static mock underneath, so the page is complete either way.
 *
 * Turning a section into video is one line here: drop the file in
 * `public/landing/` and name it below.
 */
export type LandingClip = 'understand' | 'discover' | 'connect' | 'write'

export const LANDING_CLIPS: Record<LandingClip, string | null> = {
  understand: null,
  discover: null,
  connect: null,
  write: null
}

/**
 * Whether the screen a mock is drawn in has been reached.
 *
 * Each mock in "how it works" runs its own read — fields filling in, a feed
 * dealing itself out, a fit score arriving — and it has to run when the
 * visitor is looking at *that* screen. The frame around it is the only thing
 * that knows: it provides this, and the mock inside injects it.
 *
 * It latches. A screen that has already played does not replay when it scrolls
 * back past, and one that has never been reached never plays to an empty room.
 */
export const LANDING_SCREEN_LIVE = Symbol('landing-screen-live') as InjectionKey<Ref<boolean>>

/** Read from inside a mock. Outside a frame — a mock rendered on its own — the
 *  screen counts as reached, so nothing is ever stuck waiting. */
export function useScreenLive() {
  return inject(LANDING_SCREEN_LIVE, ref(true))
}
