let developmentBootstrap: Promise<boolean | null> | null = null

export function usePersonalApi() {
  const config = useRuntimeConfig()
  const { $i18n } = useNuxtApp()
  const authenticated = useState('personal-authenticated', () => false)
  // The browser must not be able to read the bearer token. Nuxt can still read
  // this cookie during SSR and forward it to the API as an Authorization header.
  const token = useCookie<string | null>('personal_token', {
    httpOnly: true,
    secure: !import.meta.dev,
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24 * 30
  })

  /**
   * Local development mints a token for a demo user so the app is usable without
   * signing in. The endpoint only exists when the API sets ENABLE_DEV_SESSION,
   * so this quietly gives up everywhere else.
   */
  async function bootstrapDevelopmentToken(): Promise<boolean> {
    if (!import.meta.dev) return false

    if (!developmentBootstrap) {
      developmentBootstrap = $fetch<{ token?: string }>('/api/development/session', {
        baseURL: config.public.apiBase,
        credentials: 'include',
        headers: { Accept: 'application/json', 'Accept-Language': $i18n.locale.value }
      }).then((response) => {
        if (import.meta.server && response.token) token.value = response.token
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

    if (import.meta.server && token.value && !headers.Authorization) {
      headers.Authorization = `Bearer ${token.value}`
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
