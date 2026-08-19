// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  srcDir: 'app/',
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxtjs/tailwindcss', '@nuxtjs/i18n'],
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
      apiBase: 'http://localhost:8000'
    }
  },
  app: {
    head: {
      title: 'Personal — Your content strategist',
      meta: [
        { name: 'description', content: 'Personal understands your work and helps you create what matters next.' }
      ],
      // Instrument Serif is the brand display face and Inter carries every other
      // line; both are loaded here so the first paint already has them.
      link: [
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
