let developmentBootstrap: Promise<string | null> | null = null

export function usePersonalApi() {
  const config = useRuntimeConfig()
  const { $i18n } = useNuxtApp()
  const token = useCookie<string | null>('personal_token', {
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24 * 30
  })

  /**
   * Local development mints a token for a demo user so the app is usable without
   * signing in. The endpoint only exists when the API sets ENABLE_DEV_SESSION,
   * so this quietly gives up everywhere else.
   */
  async function bootstrapDevelopmentToken(): Promise<string | null> {
    if (!import.meta.dev) return null
    if (token.value) return token.value

    if (!developmentBootstrap) {
      developmentBootstrap = $fetch<{ token: string }>('/api/development/session', {
        baseURL: config.public.apiBase,
        headers: { Accept: 'application/json', 'Accept-Language': $i18n.locale.value }
      }).then((response) => {
        token.value = response.token
        return response.token
      }).catch(() => null).finally(() => {
        developmentBootstrap = null
      })
    }

    return developmentBootstrap
  }

  async function apiFetch<T>(path: string, options: any = {}, mayRetry = true): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Accept-Language': $i18n.locale.value,
      ...(options.headers || {})
    }
    if (token.value) headers.Authorization = `Bearer ${token.value}`

    try {
      return await $fetch<T>(path, {
        baseURL: config.public.apiBase,
        ...options,
        headers
      })
    } catch (exception: any) {
      if (exception?.response?.status !== 401) throw exception

      token.value = null

      if (mayRetry && await bootstrapDevelopmentToken()) {
        return apiFetch<T>(path, options, false)
      }

      if (import.meta.client) await navigateTo('/login')
      throw exception
    }
  }

  return { apiFetch, token, bootstrapDevelopmentToken }
}
