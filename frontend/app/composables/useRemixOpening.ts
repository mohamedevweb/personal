import type { Remix } from '~/types/product'

type PendingRemix = Pick<Remix, 'id' | 'status'>
type RemixOpeningResult = 'opened' | 'failed' | 'cancelled'

export function useRemixOpening() {
  async function openWhenReady(created: PendingRemix): Promise<RemixOpeningResult> {
    // The remix page owns the generation state and keeps the creator in the
    // flow they just started, instead of sending them through Drafts first.
    await navigateTo(`/remix/${created.id}`)
    return 'opened'
  }

  return { openWhenReady }
}
