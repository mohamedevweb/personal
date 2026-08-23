export type AnalyticsConsent = 'granted' | 'denied'

type GoogleAnalyticsWindow = Window & {
  dataLayer?: unknown[]
  gtag?: (...command: unknown[]) => void
}

const CONSENT_COOKIE = 'personal_analytics_consent'
const GOOGLE_ANALYTICS_SCRIPT_ID = 'personal-google-analytics'
const CONSENT_MAX_AGE = 60 * 60 * 24 * 180

function queueGoogleCommand(...command: unknown[]) {
  const analyticsWindow = window as GoogleAnalyticsWindow
  analyticsWindow.dataLayer ||= []
  analyticsWindow.gtag ||= (...queuedCommand: unknown[]) => {
    analyticsWindow.dataLayer?.push(queuedCommand)
  }
  analyticsWindow.gtag(...command)
}

function googleConsent(status: AnalyticsConsent) {
  queueGoogleCommand('consent', 'update', {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: status
  })
}

function removeGoogleAnalyticsCookies() {
  const hostname = window.location.hostname

  document.cookie.split(';').forEach((storedCookie) => {
    const name = storedCookie.split('=')[0]?.trim()
    if (name?.startsWith('_ga')) {
      document.cookie = `${name}=; Max-Age=0; path=/; SameSite=Lax`
      document.cookie = `${name}=; Max-Age=0; path=/; domain=${hostname}; SameSite=Lax`
      document.cookie = `${name}=; Max-Age=0; path=/; domain=.${hostname}; SameSite=Lax`
    }
  })
}

export function useGoogleAnalytics() {
  const config = useRuntimeConfig()
  const consent = useCookie<AnalyticsConsent | null>(CONSENT_COOKIE, {
    default: () => null,
    maxAge: CONSENT_MAX_AGE,
    sameSite: 'lax',
    secure: !import.meta.dev
  })
  const preferencesOpen = useState('analytics-consent-preferences-open', () => false)
  const initialized = useState('google-analytics-initialized', () => false)
  const measurementId = computed(() => config.public.googleAnalyticsId.trim())
  const isConfigured = computed(() => measurementId.value.length > 0)

  function loadGoogleAnalytics() {
    if (!import.meta.client || !isConfigured.value) return

    googleConsent('granted')

    if (document.getElementById(GOOGLE_ANALYTICS_SCRIPT_ID)) return

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

  function initialize() {
    if (!import.meta.client || !isConfigured.value || initialized.value) return

    queueGoogleCommand('consent', 'default', {
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      analytics_storage: 'denied'
    })
    initialized.value = true

    if (consent.value === 'granted') loadGoogleAnalytics()
  }

  function accept() {
    consent.value = 'granted'
    preferencesOpen.value = false
    loadGoogleAnalytics()
  }

  function deny() {
    const analyticsWasLoaded = document.getElementById(GOOGLE_ANALYTICS_SCRIPT_ID) !== null
    consent.value = 'denied'
    preferencesOpen.value = false
    googleConsent('denied')
    removeGoogleAnalyticsCookies()

    // Reload after withdrawing consent so the Google script is no longer
    // present and cannot emit cookieless measurements for later navigation.
    if (analyticsWasLoaded) window.location.reload()
  }

  function openPreferences() {
    preferencesOpen.value = true
  }

  return {
    consent,
    preferencesOpen,
    isConfigured,
    initialize,
    accept,
    deny,
    openPreferences
  }
}
