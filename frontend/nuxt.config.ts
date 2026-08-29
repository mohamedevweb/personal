// https://nuxt.com/docs/api/configuration/nuxt-config
const sentrySourceMapsEnabled = Boolean(
  process.env.SENTRY_AUTH_TOKEN
  && process.env.SENTRY_ORG
  && process.env.SENTRY_PROJECT
)

export default defineNuxtConfig({
  srcDir: 'app/',
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  routeRules: {
    '/create': { redirect: '/drafts' }
  },
  modules: ['@nuxtjs/tailwindcss', '@nuxtjs/i18n', '@sentry/nuxt/module'],
  sentry: {
    authToken: process.env.SENTRY_AUTH_TOKEN,
    org: process.env.SENTRY_ORG,
    project: process.env.SENTRY_PROJECT,
    release: process.env.NUXT_PUBLIC_SENTRY_RELEASE
      ? { name: process.env.NUXT_PUBLIC_SENTRY_RELEASE }
      : undefined,
    silent: !sentrySourceMapsEnabled,
    sourcemaps: {
      disable: !sentrySourceMapsEnabled
    }
  },
  css: ['~/assets/css/main.css'],
  i18n: {
    locales: [
      { code: 'en', language: 'en-US', name: 'English', file: 'en.json' },
      { code: 'fr', language: 'fr-FR', name: 'Français', file: 'fr.json' }
    ],
    defaultLocale: 'en',
    strategy: 'no_prefix',
    langDir: 'locales',
    bundle: {
      optimizeTranslationDirective: false
    },
    compilation: {
      strictMessage: false,
      escapeHtml: false
    },
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'personal_lang',
      redirectOn: 'root'
    }
  },
  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000',
      demoBookingUrl: 'https://cal.com/mc-studio/demo-personal-app?overlayCalendar=true',
      googleAnalyticsId: 'G-2X3WXN19GF',
      sentry: {
        dsn: '',
        environment: '',
        release: '',
        tracesSampleRate: 0.1
      }
    }
  },
  app: {
    head: {
      // The app is installable, so it has to draw into the notch and the home
      // indicator rather than being letterboxed away from them. Opening the
      // viewport is what makes env(safe-area-inset-*) report anything at all;
      // every fixed edge in the shell then pads itself back out of the way.
      viewport: 'width=device-width, initial-scale=1, viewport-fit=cover',
      meta: [
        { name: 'theme-color', content: '#e04f36' }
      ],
      // Instrument Serif is the brand display face and Inter carries every other
      // line; both are loaded here so the first paint already has them.
      link: [
        // The SVG mark is the one modern browsers pick; the .ico carries 16/32/48
        // for the tabs and bookmarks that still ask for it.
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico', sizes: '16x16 32x32 48x48' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png', sizes: '180x180' },
        { rel: 'manifest', href: '/site.webmanifest' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600&display=swap'
        }
      ]
    }
  }
})
