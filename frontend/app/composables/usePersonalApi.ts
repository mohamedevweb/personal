let developmentBootstrap: Promise<string> | null = null

export function usePersonalApi() {
  const config = useRuntimeConfig()
  const developmentToken = useState<string | null>('personal-development-token', () => null)

  async function bootstrapDevelopmentToken() {
    if (!import.meta.dev || developmentToken.value) return

    if (!developmentBootstrap) {
      developmentBootstrap = $fetch<{ token: string }>('/api/development/session', {
        baseURL: config.public.apiBase,
        credentials: 'include',
        headers: { Accept: 'application/json' }
      }).then((response) => {
        developmentToken.value = response.token
        return response.token
      }).finally(() => {
        developmentBootstrap = null
      })
    }

    await developmentBootstrap
  }

  async function apiFetch<T>(path: string, options: any = {}, mayBootstrap = true): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(options.headers || {})
    }
    if (developmentToken.value) headers.Authorization = `Bearer ${developmentToken.value}`

    try {
      return await $fetch<T>(path, {
        baseURL: config.public.apiBase,
        credentials: 'include',
        ...options,
        headers
      })
    } catch (exception: any) {
      if (exception?.response?.status === 401 && mayBootstrap && import.meta.dev) {
        await bootstrapDevelopmentToken()
        return apiFetch<T>(path, options, false)
      }
      throw exception
    }
  }

  return { apiFetch }
}
