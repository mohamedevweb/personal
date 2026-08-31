import type { Remix } from '~/types/product'
import { waitForGeneratedRemix } from '~/utils/remixGeneration'

type PendingRemix = Pick<Remix, 'id' | 'status'>
type RemixOpeningResult = 'opened' | 'failed' | 'cancelled'

export function useRemixOpening() {
  const { apiFetch } = usePersonalApi()
  let active = true
  let pauseTimer: ReturnType<typeof setTimeout> | undefined
  let finishPause: (() => void) | undefined
  let pollDelay = 800

  function pause() {
    return new Promise<void>((resolve) => {
      finishPause = resolve
      const delay = import.meta.client && document.visibilityState === 'hidden'
        ? 10_000
        : pollDelay
      pollDelay = Math.min(Math.round(pollDelay * 1.5), 5000)
      pauseTimer = setTimeout(() => {
        finishPause = undefined
        resolve()
      }, delay)
    })
  }

  async function waitForRemix(id: number): Promise<Remix | null> {
    pollDelay = 800
    return waitForGeneratedRemix(
      async () => {
        const response = await apiFetch<{ remix: Remix }>(`/api/remixes/${id}`)
        return response.remix
      },
      pause,
      () => active
    )
  }

  async function openWhenReady(created: PendingRemix): Promise<RemixOpeningResult> {
    // The draft page is also the progress screen. Opening it immediately makes
    // the click feel complete while the queue and provider work continue in the
    // background, instead of holding the user on the source post for minutes.
    await navigateTo(`/remix/${created.id}`)
    return 'opened'
  }

  onBeforeUnmount(() => {
    active = false
    clearTimeout(pauseTimer)
    finishPause?.()
  })

  return { openWhenReady, waitForRemix }
}
