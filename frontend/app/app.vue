<script setup lang="ts">
const { locale, t } = useI18n()
const { initialize: initializeGoogleAnalytics } = useGoogleAnalytics()

onMounted(initializeGoogleAnalytics)

useHead({
  htmlAttrs: { lang: locale },
  title: computed(() => t('meta.title')),
  meta: [
    { name: 'description', content: computed(() => t('meta.description')) }
  ]
})
</script>

<template>
  <NuxtRouteAnnouncer />
  <ToastViewport />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
