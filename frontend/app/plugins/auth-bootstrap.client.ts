export default defineNuxtPlugin(async () => {
  const { $i18n } = useNuxtApp()
  const config = useRuntimeConfig()
  const authenticated = useState('personal-authenticated', () => false)

  if (authenticated.value) return

  try {
    await $fetch('/api/auth/me', {
      baseURL: config.public.apiBase,
      credentials: 'include',
      headers: { Accept: 'application/json', 'Accept-Language': $i18n.locale.value }
    })
    authenticated.value = true
  } catch {
    authenticated.value = false
  }
})
