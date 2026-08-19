<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()
const { root } = useReveal()

useHead({
  title: () => t('landing.head.title'),
  meta: [{ name: 'description', content: () => t('landing.head.description') }]
})
</script>

<template>
  <div ref="root" class="min-h-screen bg-[var(--b-ivory)] text-[var(--b-black)] antialiased">
    <a
      href="#product"
      class="b-focus sr-only focus:not-sr-only focus:absolute focus:left-6 focus:top-6 focus:z-[60] focus:rounded-full focus:bg-[var(--b-black)] focus:px-5 focus:py-3 focus:text-[14px] focus:text-[var(--b-ivory)]"
    >{{ $t('landing.nav.skip') }}</a>

    <LandingNav />

    <main>
      <LandingHero />

      <!-- 01–04. One sequence, told once: who you are, what is working, where
           they meet, and what comes out of it. -->
      <LandingUnderstand />
      <LandingOutliers />
      <LandingStat />
      <LandingConnection />
      <LandingRemix />

      <LandingMoments />
      <LandingLoop />
      <LandingFaq />
      <LandingCta />
    </main>

    <LandingSiteFooter />
  </div>
</template>
