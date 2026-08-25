import type { Remix } from '~/types/product'
import { waitForGeneratedRemix } from '~/utils/remixGeneration'

type PendingRemix = Pick<Remix, 'id' | 'status'>
type RemixOpeningResult = 'opened' | 'failed' | 'cancelled'

export function useRemixOpening() {
  const { apiFetch } = usePersonalApi()
  let active = true
  let pauseTimer: ReturnType<typeof setTimeout> | undefined
  let finishPause: (() => void) | undefined

  function pause() {
    return new Promise<void>((resolve) => {
      finishPause = resolve
      pauseTimer = setTimeout(() => {
        finishPause = undefined
        resolve()
      }, 650)
    })
  }

  async function waitForRemix(id: number): Promise<Remix | null> {
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
    const remix = created.status === 'generating'
      ? await waitForRemix(created.id)
      : created

    if (!remix) return 'cancelled'
    if (remix.status === 'failed') return 'failed'

    await navigateTo(`/remix/${remix.id}`)
    return 'opened'
  }

  onBeforeUnmount(() => {
    active = false
    clearTimeout(pauseTimer)
    finishPause?.()
  })

  return { openWhenReady, waitForRemix }
}
