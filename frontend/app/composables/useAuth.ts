interface AuthUser {
  id: number
  name: string
  email: string
  avatar_url: string | null
  instagram_username: string | null
  email_verified_at: string | null
  queue_dashboard_available: boolean
  catalog_admin_available: boolean
}

interface AuthResponse {
  user: AuthUser
  token: string
}

export function useAuth() {
  const { apiFetch, authenticated, token } = usePersonalApi()
  const user = useState<AuthUser | null>('personal-user', () => null)

  async function register(payload: { name: string, email: string, password: string, password_confirmation: string }) {
    const response = await apiFetch<AuthResponse>('/api/auth/register', { method: 'POST', body: payload })
    authenticated.value = true
    user.value = response.user
    token.value = response.token
    return response.user
  }

  async function login(payload: { email: string, password: string }) {
    const response = await apiFetch<AuthResponse>('/api/auth/login', { method: 'POST', body: payload })
    authenticated.value = true
    user.value = response.user
    token.value = response.token
    return response.user
  }

  async function requestPasswordReset(email: string) {
    await apiFetch('/api/auth/forgot-password', { method: 'POST', body: { email } })
  }

  async function resetPassword(payload: { token: string, email: string, password: string, password_confirmation: string }) {
    await apiFetch('/api/auth/reset-password', { method: 'POST', body: payload })
  }

  async function logout() {
    try {
      await apiFetch('/api/auth/logout', { method: 'POST' })
    } finally {
      authenticated.value = false
      user.value = null
      token.value = null
      // Clear the cached onboarding gate so the next account is re-checked.
      useState('personal-onboarded', () => false).value = false
      await navigateTo('/login')
    }
  }

  async function loadUser() {
    const response = await apiFetch<{ user: AuthUser }>('/api/auth/me', {}, true, false)
    authenticated.value = true
    user.value = response.user
    return response.user
  }

  async function updateAccount(payload: { name?: string, email?: string }) {
    const response = await apiFetch<{ user: AuthUser }>('/api/me/account', { method: 'PATCH', body: payload })
    user.value = response.user
    return response.user
  }

  async function updatePassword(payload: { current_password: string, password: string, password_confirmation: string }) {
    await apiFetch('/api/me/password', { method: 'PUT', body: payload })
  }

  async function resendVerification() {
    await apiFetch('/api/email/verification-notification', { method: 'POST' })
  }

  return {
    user,
    authenticated,
    register,
    login,
    requestPasswordReset,
    resetPassword,
    logout,
    loadUser,
    updateAccount,
    updatePassword,
    resendVerification
  }
}
