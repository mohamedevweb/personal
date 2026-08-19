interface AuthUser {
  id: number
  name: string
  email: string
  avatar_url: string | null
  email_verified_at: string | null
}

interface AuthResponse {
  user: AuthUser
  token: string
}

export function useAuth() {
  const { apiFetch, token } = usePersonalApi()
  const user = useState<AuthUser | null>('personal-user', () => null)

  async function register(payload: { name: string, email: string, password: string, password_confirmation: string }) {
    const response = await apiFetch<AuthResponse>('/api/auth/register', { method: 'POST', body: payload })
    token.value = response.token
    user.value = response.user
    return response.user
  }

  async function login(payload: { email: string, password: string }) {
    const response = await apiFetch<AuthResponse>('/api/auth/login', { method: 'POST', body: payload })
    token.value = response.token
    user.value = response.user
    return response.user
  }

  async function logout() {
    try {
      await apiFetch('/api/auth/logout', { method: 'POST' })
    } finally {
      token.value = null
      user.value = null
      // Clear the cached onboarding gate so the next account is re-checked.
      useState('personal-onboarded', () => false).value = false
      await navigateTo('/login')
    }
  }

  async function loadUser() {
    if (!token.value) return null
    const response = await apiFetch<{ user: AuthUser }>('/api/auth/me')
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

  return { user, token, register, login, logout, loadUser, updateAccount, updatePassword, resendVerification }
}
