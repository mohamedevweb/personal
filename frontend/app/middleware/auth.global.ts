import type { InstagramStatusResponse } from '~/types/instagram'

const publicRoutes = new Set(['/', '/login'])

export default defineNuxtRouteMiddleware(async (to) => {
  if (publicRoutes.has(to.path)) return

  const { token, bootstrapDevelopmentToken, apiFetch } = usePersonalApi()

  // Local development can mint a demo token; everywhere else this is a sign-in.
  if (!token.value && !(await bootstrapDevelopmentToken())) {
    return navigateTo('/login')
  }

  // First-login onboarding gate: connecting Instagram and finishing the import
  // is the first thing a new account does. Until it completes, the rest of the
  // app stays out of reach. Once done we cache it so we stop re-checking.
  const onboarded = useState('personal-onboarded', () => false)

  // The user can choose to skip connecting Instagram; that choice (persisted in
  // a cookie) is enough to let them past the gate.
  if (useCookie<boolean>('personal-onboarding-skipped').value) {
    onboarded.value = true
  }

  if (!onboarded.value) {
    try {
      const status = await apiFetch<InstagramStatusResponse>('/api/integrations/instagram/status')
      onboarded.value = status.connected && status.account?.sync_status === 'completed'
    } catch {
      // If we cannot verify the connection, don't trap the user on a blank gate.
      return
    }
  }

  if (!onboarded.value && to.path !== '/onboarding') {
    return navigateTo('/onboarding')
  }

  if (onboarded.value && to.path === '/onboarding') {
    return navigateTo('/feed')
  }
})
