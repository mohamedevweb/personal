const publicRoutes = new Set(['/', '/login'])

export default defineNuxtRouteMiddleware(async (to) => {
  if (publicRoutes.has(to.path)) return

  const { token, bootstrapDevelopmentToken } = usePersonalApi()
  if (token.value) return

  // Local development can mint a demo token; everywhere else this is a sign-in.
  if (await bootstrapDevelopmentToken()) return

  return navigateTo('/login')
})
