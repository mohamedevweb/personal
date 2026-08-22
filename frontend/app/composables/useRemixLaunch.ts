export type RemixLaunchFormat = 'reel' | 'carousel' | 'caption'

export interface RemixLaunchContext {
  format: RemixLaunchFormat
  sourceHook: string | null
  moment: string | null
  startedAt: number
  remixId: number | null
}

export function useRemixLaunch() {
  const launch = useState<RemixLaunchContext | null>('personal-remix-launch', () => null)

  function begin(context: Pick<RemixLaunchContext, 'format' | 'sourceHook' | 'moment'>) {
    launch.value = {
      ...context,
      startedAt: Date.now(),
      remixId: null
    }
  }

  function attach(remixId: number) {
    if (launch.value) launch.value.remixId = remixId
  }

  function clear() {
    launch.value = null
  }

  return { launch, begin, attach, clear }
}
