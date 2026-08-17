// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  srcDir: 'app/',
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxtjs/tailwindcss'],
  css: ['~/assets/css/main.css'],
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
      ]
    }
  }
})
