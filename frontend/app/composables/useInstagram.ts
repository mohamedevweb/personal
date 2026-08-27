import type { HandleAnalysisStatus, InstagramStatusResponse } from '~/types/instagram'

// Everything between the handle being saved and the profile being understood.
const RUNNING_ANALYSIS: HandleAnalysisStatus[] = [
  'queued',
  'reading_profile',
  'importing_posts',
  'reading_voice',
  'mapping_audience',
  'transcribing_reels'
]

export function useInstagram() {
  const { apiFetch } = usePersonalApi()
  const { t } = useI18n()
  const status = ref<InstagramStatusResponse>({
    connected: false,
    inspiration_count: 0,
    onboarding_complete: false
  })
  const loading = ref(true)
  const error = ref<string | null>(null)
  let pollTimer: ReturnType<typeof setTimeout> | undefined

  async function loadStatus() {
    try {
      status.value = await apiFetch<InstagramStatusResponse>('/api/integrations/instagram/status')
      error.value = null
    } catch (exception: unknown) {
      error.value = apiErrorMessage(exception, t('instagram.statusError'))
    } finally {
      loading.value = false
    }
  }

  async function connect() {
    loading.value = true
    error.value = null

    try {
      const response = await apiFetch<{ authorization_url: string }>('/api/integrations/instagram/authorize')
      window.location.assign(response.authorization_url)
    } catch (exception: unknown) {
      error.value = apiErrorMessage(exception, t('instagram.connectError'))
      loading.value = false
    }
  }

  // True while the public profile behind a saved handle is still being read.
  const analysisRunning = computed(() => RUNNING_ANALYSIS.includes(status.value.analysis?.status ?? 'idle'))

  function importing() {
    const syncStatus = status.value.account?.sync_status
    const syncing = status.value.connected && syncStatus !== 'completed' && syncStatus !== 'failed'

    return syncing || analysisRunning.value
  }

  function startPolling() {
    clearTimeout(pollTimer)
    const poll = async () => {
      await loadStatus()
      if (importing()) pollTimer = setTimeout(poll, 1400)
    }
    void poll()
  }

  function stopPolling() {
    clearTimeout(pollTimer)
  }

  onBeforeUnmount(stopPolling)

  return { status, loading, error, analysisRunning, connect, loadStatus, startPolling, stopPolling }
}
