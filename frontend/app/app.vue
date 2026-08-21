<script setup lang="ts">
const { locale } = useI18n()
useHead({
  htmlAttrs: { lang: locale }
})
</script>

<template>
  <NuxtRouteAnnouncer />
  <ToastViewport />
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
