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
  '/story',
  '/tmp-carousel-check'
])

// Sign-in routes: reaching one with a session already in hand means the user is
// simply back on the app, so we send them straight in instead of asking again.
const signedInRedirects = new Set(['/login', '/forgot-password'])

export default defineNuxtRouteMiddleware(async (to) => {
  const { authenticated, bootstrapDevelopmentToken, apiFetch } = usePersonalApi()

  if (signedInRedirects.has(to.path)) {
    try {
      await apiFetch('/api/auth/me', {}, false, false)
      return navigateTo('/feed')
    } catch {
      // No authenticated cookie, so the sign-in page remains reachable.
    }
  }

  if (publicRoutes.has(to.path)) return

  // Local development can mint a demo token; everywhere else this is a sign-in.
  if (!authenticated.value && await bootstrapDevelopmentToken()) {
    authenticated.value = true
  }

  // If auth isn't set yet (plugin hasn't run), verify it
  if (!authenticated.value) {
    try {
      await apiFetch('/api/auth/me', {}, false, false)
      authenticated.value = true
    } catch {
      return navigateTo('/login')
    }
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

  // First-login onboarding gate: the creator connects Instagram and completes
  // the import, or lets Personal read the public profile behind their handle.
  const onboarded = useState('personal-onboarded', () => false)
  const instagramStatus = useState<InstagramStatusResponse>('instagram-status', () => ({
    connected: false,
    inspiration_count: 0,
    onboarding_complete: false
  }))
  const instagramStatusLoaded = useState('instagram-status-loaded', () => false)

  if (!onboarded.value) {
    try {
      const status = await apiFetch<InstagramStatusResponse>('/api/integrations/instagram/status')
      instagramStatus.value = status
      instagramStatusLoaded.value = true
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
