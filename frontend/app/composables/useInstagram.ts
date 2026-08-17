import type { InstagramStatusResponse } from '~/types/instagram'

export function useInstagram() {
  const { apiFetch } = usePersonalApi()
  const status = ref<InstagramStatusResponse>({ connected: false })
  const loading = ref(true)
  const error = ref<string | null>(null)
  let pollTimer: ReturnType<typeof setTimeout> | undefined

  async function loadStatus() {
    try {
      status.value = await apiFetch<InstagramStatusResponse>('/api/integrations/instagram/status')
      error.value = null
    } catch (exception: any) {
      error.value = exception?.data?.message || 'We could not check your Instagram connection.'
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
    } catch (exception: any) {
      error.value = exception?.data?.message || 'Instagram could not be started. Please try again.'
      loading.value = false
    }
  }

  function startPolling() {
    clearTimeout(pollTimer)
    const poll = async () => {
      await loadStatus()
      const syncStatus = status.value.account?.sync_status
      if (status.value.connected && syncStatus !== 'completed' && syncStatus !== 'failed') {
        pollTimer = setTimeout(poll, 1400)
      }
    }
    void poll()
  }

  onBeforeUnmount(() => clearTimeout(pollTimer))

  return { status, loading, error, connect, loadStatus, startPolling }
}
