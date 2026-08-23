type GoogleAnalyticsWindow = Window & {
  dataLayer?: unknown[]
  gtag?: (...command: unknown[]) => void
}

const GOOGLE_ANALYTICS_SCRIPT_ID = 'personal-google-analytics'

function queueGoogleCommand(...command: unknown[]) {
  const analyticsWindow = window as GoogleAnalyticsWindow
  analyticsWindow.dataLayer ||= []
  analyticsWindow.gtag ||= (...queuedCommand: unknown[]) => {
    analyticsWindow.dataLayer?.push(queuedCommand)
  }
  analyticsWindow.gtag(...command)
}

export function useGoogleAnalytics() {
  const config = useRuntimeConfig()
  const initialized = useState('google-analytics-initialized', () => false)
  const measurementId = computed(() => config.public.googleAnalyticsId.trim())
  const isConfigured = computed(() => measurementId.value.length > 0)

  function initialize() {
    if (!import.meta.client || !isConfigured.value || initialized.value) return

    queueGoogleCommand('consent', 'default', {
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      analytics_storage: 'denied'
    })
    initialized.value = true

    queueGoogleCommand('js', new Date())
    queueGoogleCommand('config', measurementId.value, {
      allow_google_signals: false,
      allow_ad_personalization_signals: false
    })

    const script = document.createElement('script')
    script.id = GOOGLE_ANALYTICS_SCRIPT_ID
    script.async = true
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId.value)}`
    document.head.appendChild(script)
  }

  return {
    isConfigured,
    initialize
  }
}
