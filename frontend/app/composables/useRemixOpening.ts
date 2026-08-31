import type { Remix } from '~/types/product'

type PendingRemix = Pick<Remix, 'id' | 'status'>
type RemixOpeningResult = 'opened' | 'failed' | 'cancelled'

export function useRemixOpening() {
  async function openWhenReady(created: PendingRemix): Promise<RemixOpeningResult> {
    // A generating draft is already visible in Drafts, which polls it without
    // replacing the current page with a full-screen progress panel.
    await navigateTo(created.status === 'generating' ? '/drafts' : `/remix/${created.id}`)
    return 'opened'
  }

  return { openWhenReady }
}
