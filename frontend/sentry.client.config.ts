import * as Sentry from '@sentry/nuxt'

const config = useRuntimeConfig()
const tracesSampleRate = Number(config.public.sentry.tracesSampleRate)

Sentry.init({
  dsn: config.public.sentry.dsn,
  enabled: Boolean(config.public.sentry.dsn),
  environment: config.public.sentry.environment || undefined,
  release: config.public.sentry.release || undefined,
  sendDefaultPii: false,
  tracesSampleRate: Number.isFinite(tracesSampleRate) ? tracesSampleRate : 0.1,
  tracePropagationTargets: [config.public.apiBase]
})
