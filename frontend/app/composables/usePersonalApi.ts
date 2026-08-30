let developmentBootstrap: Promise<string | null> | null = null

export function usePersonalApi() {
  const config = useRuntimeConfig()
  const { $i18n } = useNuxtApp()
  const authenticated = useState('personal-authenticated', () => false)

  /**
   * Local development mints a token for a demo user so the app is usable without
   * signing in. The endpoint only exists when the API sets ENABLE_DEV_SESSION,
   * so this quietly gives up everywhere else.
   */
  async function bootstrapDevelopmentToken(): Promise<boolean> {
    if (!import.meta.dev) return false

    if (!developmentBootstrap) {
      developmentBootstrap = $fetch('/api/development/session', {
        baseURL: config.public.apiBase,
        credentials: 'include',
        headers: { Accept: 'application/json', 'Accept-Language': $i18n.locale.value }
      }).then((response) => {
        authenticated.value = true
        return true
      }).catch(() => null).finally(() => {
        developmentBootstrap = null
      })
    }

    return (await developmentBootstrap) === true
  }

  async function apiFetch<T>(path: string, options: any = {}, mayRetry = true, redirectOnUnauthorized = true): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Accept-Language': $i18n.locale.value,
      ...(options.headers || {})
    }
    try {
      return await $fetch<T>(path, {
        baseURL: config.public.apiBase,
        credentials: 'include',
        ...options,
        headers
      })
    } catch (exception: any) {
      if (exception?.response?.status !== 401) throw exception

      authenticated.value = false

      if (mayRetry && await bootstrapDevelopmentToken()) {
        return apiFetch<T>(path, options, false, redirectOnUnauthorized)
      }

      if (import.meta.client && redirectOnUnauthorized) await navigateTo('/login')
      throw exception
    }
  }

  return { apiFetch, authenticated, bootstrapDevelopmentToken }
}
