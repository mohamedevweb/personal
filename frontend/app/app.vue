<script setup lang="ts">
const { locale, t } = useI18n()
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
