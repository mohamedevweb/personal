import * as Sentry from '@sentry/nuxt'

const dsn = process.env.NUXT_PUBLIC_SENTRY_DSN
const configuredTracesSampleRate = Number(process.env.NUXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE ?? '0.1')

Sentry.init({
  dsn,
  enabled: Boolean(dsn),
  environment: process.env.NUXT_PUBLIC_SENTRY_ENVIRONMENT || process.env.NODE_ENV,
  release: process.env.NUXT_PUBLIC_SENTRY_RELEASE || undefined,
  sendDefaultPii: false,
  tracesSampleRate: Number.isFinite(configuredTracesSampleRate) ? configuredTracesSampleRate : 0.1
})
