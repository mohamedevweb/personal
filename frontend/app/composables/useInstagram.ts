import type { HandleAnalysisStatus, InstagramProgressResponse, InstagramStatusResponse, MediaEnrichmentStatus } from '~/types/instagram'

// Everything between the handle being saved and the profile being understood.
const RUNNING_ANALYSIS: HandleAnalysisStatus[] = [
  'queued',
  'reading_profile',
  'importing_posts',
  'reading_voice',
  'mapping_audience',
  // Kept for profiles that were already running during the deployment which
  // separated initial analysis from background media enrichment.
  'transcribing_reels'
]

const RUNNING_MEDIA_ENRICHMENT: MediaEnrichmentStatus[] = ['queued', 'importing_media', 'processing']

let statusRequest: Promise<void> | null = null
let progressRequest: Promise<void> | null = null
let pollTimer: ReturnType<typeof setTimeout> | undefined
let pollingStartedAt = 0
const pollOwners = new Set<symbol>()

export function useInstagram() {
  const { apiFetch } = usePersonalApi()
  const { t } = useI18n()
  const owner = Symbol('instagram-poll-owner')
  const status = useState<InstagramStatusResponse>('instagram-status', () => ({
    connected: false,
    inspiration_count: 0,
    onboarding_complete: false
  }))
  const statusLoaded = useState('instagram-status-loaded', () => false)
  const loading = useState('instagram-status-loading', () => true)
  const error = useState<string | null>('instagram-status-error', () => null)

  async function loadStatus(force = true) {
    if (!force && statusLoaded.value) return
    if (statusRequest) return statusRequest

    statusRequest = apiFetch<InstagramStatusResponse>('/api/integrations/instagram/status')
      .then((response) => {
        status.value = response
        statusLoaded.value = true
        error.value = null
      })
      .catch((exception: unknown) => {
        error.value = apiErrorMessage(exception, t('instagram.statusError'))
      })
      .finally(() => {
        loading.value = false
        statusRequest = null
      })

    return statusRequest
  }

  async function loadProgress() {
    if (progressRequest) return progressRequest

    progressRequest = apiFetch<InstagramProgressResponse>('/api/integrations/instagram/progress')
      .then((response) => {
        status.value.onboarding_complete = response.onboarding_complete
        status.value.analysis = response.analysis
        status.value.media_enrichment = response.media_enrichment
        if (response.account && status.value.account) {
          Object.assign(status.value.account, response.account)
        }
        error.value = null
      })
      .catch((exception: unknown) => {
        error.value = apiErrorMessage(exception, t('instagram.statusError'))
      })
      .finally(() => {
        progressRequest = null
      })

    return progressRequest
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
  const mediaEnrichmentRunning = computed(() => RUNNING_MEDIA_ENRICHMENT.includes(status.value.media_enrichment?.status ?? 'idle'))

  function importing() {
    const syncStatus = status.value.account?.sync_status
    const syncing = status.value.connected && syncStatus !== 'completed' && syncStatus !== 'failed'

    return syncing || analysisRunning.value || mediaEnrichmentRunning.value
  }

  function startPolling() {
    pollOwners.add(owner)
    if (pollTimer) return

    pollingStartedAt = Date.now()
    schedulePoll(2000)
  }

  function schedulePoll(delay: number) {
    clearTimeout(pollTimer)
    pollTimer = setTimeout(poll, delay)
  }

  async function poll() {
    pollTimer = undefined
    if (pollOwners.size === 0) return

    if (import.meta.client && document.visibilityState === 'hidden') {
      schedulePoll(10_000)
      return
    }

    await loadProgress()
    if (!importing()) return

    const elapsed = Date.now() - pollingStartedAt
    schedulePoll(elapsed < 15_000 ? 2000 : elapsed < 60_000 ? 5000 : 10_000)
  }

  function stopPolling() {
    pollOwners.delete(owner)
    if (pollOwners.size > 0) return

    clearTimeout(pollTimer)
    pollTimer = undefined
  }

  onBeforeUnmount(stopPolling)

  return {
    status,
    loading,
    error,
    analysisRunning,
    mediaEnrichmentRunning,
    connect,
    loadStatus,
    startPolling,
    stopPolling
  }
}
