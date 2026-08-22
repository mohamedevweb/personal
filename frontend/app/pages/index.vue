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
      href="#how"
      class="b-focus sr-only focus:not-sr-only focus:absolute focus:left-6 focus:top-6 focus:z-[60] focus:rounded-full focus:bg-[var(--b-black)] focus:px-5 focus:py-3 focus:text-[14px] focus:text-[var(--b-ivory)]"
    >{{ $t('landing.nav.skip') }}</a>

    <LandingNav />

    <main>
      <!-- The claim, the proof, the number, the mechanism, the objections, the
           door. Six blocks, and every one of them earns its scroll. -->
      <LandingHero />
      <LandingProof />
      <LandingStat />
      <LandingHow />
      <LandingFaq />
      <LandingCta />
    </main>

    <LandingSiteFooter />
  </div>
</template>
