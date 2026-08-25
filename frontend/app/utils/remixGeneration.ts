import type { Remix } from '~/types/product'

type RemixReader = () => Promise<Remix>
type Pause = () => Promise<void>

export async function waitForGeneratedRemix(
  read: RemixReader,
  pause: Pause,
  isActive: () => boolean
): Promise<Remix | null> {
  while (isActive()) {
    const remix = await read()

    if (!isActive()) return null
    if (remix.status !== 'generating') return remix
    await pause()
  }

  return null
}
