<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()

useHead({
  title: () => t('story.head.title'),
  meta: [{ name: 'description', content: () => t('story.head.description') }]
})

/**
 * Three chapters, in the order they get told: who is building, what is being
 * built, and where the building happens in public. The frames are here so the
 * writing can drop straight into the locale files.
 */
const CHAPTERS = ['me', 'tool', 'public'] as const
</script>

<template>
  <div class="min-h-screen bg-[var(--b-ivory)] text-[var(--b-black)] antialiased">
    <LandingNav />

    <main>
      <header class="px-6 pb-14 pt-16 md:px-10 md:pb-20 md:pt-24">
        <div class="mx-auto max-w-[1200px]">
          <p class="b-eyebrow">{{ $t('story.hero.eyebrow') }}</p>
          <h1 class="mt-6 max-w-3xl font-display text-[40px] leading-[1.04] tracking-[-.03em] md:text-[64px]">
            {{ $t('story.hero.title') }}
          </h1>
          <p class="mt-7 max-w-2xl text-[17px] leading-[1.7] text-[var(--b-stone)] md:text-[19px]">
            {{ $t('story.hero.lede') }}
          </p>
        </div>
      </header>

      <div class="border-t border-[var(--b-line)] px-6 py-14 md:px-10 md:py-20">
        <div class="mx-auto max-w-[1200px]">
          <div class="max-w-[68ch]">
            <section
              v-for="(chapter, index) in CHAPTERS"
              :key="chapter"
              class="border-b border-[var(--b-line)] pb-12 pt-12 first:pt-0 last:border-0"
            >
              <p class="b-eyebrow">{{ String(index + 1).padStart(2, '0') }}</p>
              <h2 class="mt-4 font-display text-[28px] leading-[1.12] tracking-[-.025em] md:text-[34px]">
                {{ $t(`story.chapters.${chapter}.title`) }}
              </h2>
              <p class="mt-5 text-[15.5px] leading-[1.75] text-[var(--b-stone)]">
                {{ $t(`story.chapters.${chapter}.body`) }}
              </p>
            </section>

            <div class="pt-12">
              <LandingButtonLink to="/" variant="ghost">
                {{ $t('story.back') }}
              </LandingButtonLink>
            </div>
          </div>
        </div>
      </div>
    </main>

    <LandingSiteFooter />
  </div>
</template>
