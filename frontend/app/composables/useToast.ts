export type ToastKind = 'success' | 'error'

export interface ToastNotice {
  id: number
  kind: ToastKind
  message: string
  duration: number
}

interface ApiErrorPayload {
  message?: unknown
  errors?: Record<string, unknown>
}

interface ApiError {
  data?: ApiErrorPayload
}

let toastSequence = 0

export function apiErrorMessage(exception: unknown, fallback: string): string {
  if (!exception || typeof exception !== 'object') return fallback

  const payload = (exception as ApiError).data
  if (!payload || typeof payload !== 'object') return fallback

  if (payload.errors && typeof payload.errors === 'object') {
    for (const messages of Object.values(payload.errors)) {
      if (Array.isArray(messages) && typeof messages[0] === 'string') return messages[0]
    }
  }

  return typeof payload.message === 'string' ? payload.message : fallback
}

export function useToast() {
  const toasts = useState<ToastNotice[]>('personal-toasts', () => [])

  function remove(id: number) {
    toasts.value = toasts.value.filter(toast => toast.id !== id)
  }

  function show(message: string, kind: ToastKind, duration = 5000) {
    const id = Date.now() * 100 + toastSequence++
    const toast = { id, kind, message, duration }
    toasts.value = [...toasts.value.slice(-2), toast]

    if (import.meta.client) window.setTimeout(() => remove(id), duration)
    return id
  }

  return {
    toasts,
    remove,
    success: (message: string, duration?: number) => show(message, 'success', duration),
    error: (message: string, duration?: number) => show(message, 'error', duration)
  }
}
