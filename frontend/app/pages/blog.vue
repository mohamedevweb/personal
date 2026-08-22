<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()

useHead({
  title: () => t('blog.head.title'),
  meta: [{ name: 'description', content: () => t('blog.head.description') }]
})

/**
 * The shelf is empty on purpose: the page exists so the nav has somewhere real
 * to land, and so the first post only needs writing, not scaffolding. Posts
 * will slot in as a list here.
 */
</script>

<template>
  <div class="min-h-screen bg-[var(--b-ivory)] text-[var(--b-black)] antialiased">
    <LandingNav />

    <main>
      <header class="px-6 pb-14 pt-16 md:px-10 md:pb-20 md:pt-24">
        <div class="mx-auto max-w-[1200px]">
          <p class="b-eyebrow">{{ $t('blog.hero.eyebrow') }}</p>
          <h1 class="mt-6 max-w-3xl font-display text-[40px] leading-[1.04] tracking-[-.03em] md:text-[64px]">
            {{ $t('blog.hero.title') }}
          </h1>
          <p class="mt-7 max-w-2xl text-[17px] leading-[1.7] text-[var(--b-stone)] md:text-[19px]">
            {{ $t('blog.hero.lede') }}
          </p>
        </div>
      </header>

      <div class="border-t border-[var(--b-line)] px-6 py-14 md:px-10 md:py-24">
        <div class="mx-auto max-w-[1200px]">
          <div class="max-w-[68ch] rounded-[20px] border border-dashed border-[var(--b-line)] bg-[var(--b-surface)] px-7 py-12 md:px-10 md:py-14">
            <p class="b-eyebrow">{{ $t('blog.empty.eyebrow') }}</p>
            <h2 class="mt-4 font-display text-[28px] leading-[1.12] tracking-[-.025em] md:text-[34px]">
              {{ $t('blog.empty.title') }}
            </h2>
            <p class="mt-5 text-[15.5px] leading-[1.75] text-[var(--b-stone)]">
              {{ $t('blog.empty.body') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
              <LandingButtonLink to="/story" variant="ghost">
                {{ $t('blog.empty.story') }}
              </LandingButtonLink>
              <LandingButtonLink to="/login">
                {{ $t('landing.nav.getAccess') }}
              </LandingButtonLink>
            </div>
          </div>
        </div>
      </div>
    </main>

    <LandingSiteFooter />
  </div>
</template>
