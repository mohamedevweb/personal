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
  // The session cookie is issued by the API subdomain. It is available to the
  // browser's API request, but the frontend SSR request may not receive it when
  // production uses a host-only cookie. Waiting for the client avoids rendering
  // a false /login redirect during a hard reload; the middleware still runs
  // before the protected page is hydrated.
  if (import.meta.server) return

  const { authenticated, bootstrapDevelopmentToken, apiFetch } = usePersonalApi()
  const { user, loadUser } = useAuth()

  if (signedInRedirects.has(to.path)) {
    try {
      if (!user.value) await loadUser()
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

  // If auth isn't set yet, resolve the user and let that request establish the
  // authenticated state for the rest of the app.
  if (!authenticated.value) {
    try {
      await loadUser()
    } catch {
      return navigateTo('/login')
    }
  }

  // This fallback covers a development token minted by the middleware itself
  // and routes that already marked the session as authenticated.
  if (!user.value) {
    try {
      await loadUser()
    } catch {
      // If we cannot resolve the user, fall through rather than trapping them.
      return
    }
  }

  if (user.value && !user.value.email_verified_at) {
    return navigateTo('/verify-email')
  }

  // Internal operational pages do not depend on the creator onboarding flow.
  // The API still applies the administrator allowlist for these routes.
  if (to.path.startsWith('/admin/')) {
    const allowed = to.path.startsWith('/admin/catalog')
      ? user.value?.catalog_admin_available
      : user.value?.queue_dashboard_available

    if (!allowed) return navigateTo('/feed')
    return
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
