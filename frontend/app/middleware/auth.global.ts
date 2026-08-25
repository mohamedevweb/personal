import type { InstagramStatusResponse } from '~/types/instagram'

const publicRoutes = new Set([
  '/',
  '/login',
  '/forgot-password',
  '/reset-password',
  '/verify-email',
  '/privacy',
  '/terms',
  '/blog',
  '/story'
])

// Sign-in routes: reaching one with a session already in hand means the user is
// simply back on the app, so we send them straight in instead of asking again.
const signedInRedirects = new Set(['/login', '/forgot-password'])

export default defineNuxtRouteMiddleware(async (to) => {
  const { token, bootstrapDevelopmentToken, apiFetch } = usePersonalApi()

  if (signedInRedirects.has(to.path) && token.value) {
    return navigateTo('/feed')
  }

  if (publicRoutes.has(to.path)) return

  // Local development can mint a demo token; everywhere else this is a sign-in.
  if (!token.value && !(await bootstrapDevelopmentToken())) {
    return navigateTo('/login')
  }

  // Email verification gate: an account has to confirm its address before any of
  // the app is reachable. We resolve the user once and reuse the cached state.
  const user = useState<{
    email_verified_at: string | null
  } | null>('personal-user', () => null)
  if (!user.value) {
    try {
      const response = await apiFetch<{
        user: {
          email_verified_at: string | null
        }
      }>('/api/auth/me')
      user.value = response.user
    } catch {
      // If we cannot resolve the user, fall through rather than trapping them.
      return
    }
  }

  if (user.value && !user.value.email_verified_at) {
    return navigateTo('/verify-email')
  }

  // First-login onboarding gate: the creator connects Instagram, completes the
  // import and chooses the private inspiration set that seeds their first feed.
  const onboarded = useState('personal-onboarded', () => false)

  // The user can choose to skip connecting Instagram; that choice (persisted in
  // a cookie) is enough to let them past the gate.
  if (to.query.qa_new === '1') {
    onboarded.value = false
  } else if (useCookie<boolean>('personal-onboarding-skipped').value) {
    onboarded.value = true
  }

  if (!onboarded.value) {
    try {
      const status = await apiFetch<InstagramStatusResponse>('/api/integrations/instagram/status')
      onboarded.value = status.onboarding_complete
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
