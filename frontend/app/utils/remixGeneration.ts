import type { Remix } from '~/types/product'

type RemixReader = () => Promise<Pick<Remix, 'status'>>
type Pause = (attempt: number) => Promise<void>

export async function waitForGeneratedRemix(
  read: RemixReader,
  pause: Pause,
  isActive: () => boolean
): Promise<Pick<Remix, 'status'> | null> {
  let attempt = 0

  while (isActive()) {
    const remix = await read()

    if (!isActive()) return null
    if (remix.status !== 'generating') return remix
    await pause(++attempt)
  }

  return null
}
