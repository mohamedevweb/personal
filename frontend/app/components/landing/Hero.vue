<script setup lang="ts">
const { t } = useI18n()

const rows = computed(() => [
  { key: 'one', accent: true },
  { key: 'two', accent: false },
  { key: 'three', accent: false }
].map(row => ({
  ...row,
  title: t(`landing.hero.brief.rows.${row.key}.title`),
  body: t(`landing.hero.brief.rows.${row.key}.body`)
})))

const tabs = ['understand', 'find', 'remix'] as const
</script>

<template>
  <section class="relative px-6 md:px-10">
    <div class="mx-auto max-w-[1200px] pt-14 md:pt-24">
      <div class="mx-auto max-w-[52rem] text-center">
        <h1
          data-reveal
          class="font-display text-[44px] leading-[1.02] tracking-[-.03em] sm:text-[62px] md:text-[76px] lg:text-[86px]"
          style="--reveal-delay:60ms"
        >
          <span class="block">{{ $t('landing.hero.titleLineOne') }}</span>
          <span class="block">{{ $t('landing.hero.titleLineTwo') }}</span>
        </h1>

        <p
          data-reveal
          class="mx-auto mt-8 max-w-[40rem] text-balance text-[17px] leading-[1.65] text-[var(--b-stone)] md:text-[19px]"
          style="--reveal-delay:120ms"
        >
          {{ $t('landing.hero.subtitle') }}
        </p>

        <div data-reveal class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row" style="--reveal-delay:180ms">
          <LandingButtonLink to="/login" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.getAccess') }}
            <AppIcon name="arrow" :size="17" />
          </LandingButtonLink>
          <LandingButtonLink to="#product" variant="ghost" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.seeHow') }}
          </LandingButtonLink>
        </div>

        <p data-reveal class="mt-6 text-[13px] text-[var(--b-stone)]" style="--reveal-delay:220ms">
          {{ $t('landing.hero.reassurance') }}
        </p>
      </div>

      <!-- The morning brief. It sits deliberately low so the fold cuts through
           it: the first thing you see of the product is it already working. -->
      <figure data-reveal class="mx-auto mt-16 max-w-[940px] md:mt-24" style="--reveal-delay:280ms">
        <figcaption class="sr-only">{{ $t('landing.hero.brief.label') }}</figcaption>

        <div class="b-panel b-lift overflow-hidden">
          <div class="flex items-center justify-between border-b border-[var(--b-line-soft)] px-5 py-4 md:px-7">
            <PersonalLogo :size="15" />
            <div class="hidden items-center gap-6 text-[12.5px] text-[var(--b-stone)] sm:flex">
              <span v-for="(tab, index) in tabs" :key="tab" :class="index === 1 ? 'text-[var(--b-black)]' : ''">
                {{ $t(`landing.hero.brief.tabs.${tab}`) }}
              </span>
            </div>
            <span class="h-7 w-7 rounded-full bg-[#e9e4d9]" aria-hidden="true" />
          </div>

          <div class="px-5 pb-6 pt-6 md:px-7 md:pb-8">
            <p class="b-eyebrow">{{ $t('landing.hero.brief.eyebrow') }}</p>

            <div class="mt-3 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
              <p class="font-display text-[26px] tracking-[-.02em] md:text-[30px]">{{ $t('landing.hero.brief.greeting') }}</p>
              <p class="text-[13px] text-[var(--b-stone)]">{{ $t('landing.hero.brief.count') }}</p>
            </div>

            <ul class="mt-6 divide-y divide-[var(--b-line-soft)] border-t border-[var(--b-line-soft)]">
              <li v-for="row in rows" :key="row.key" class="flex items-start gap-3.5 py-4">
                <span
                  class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="row.accent ? 'bg-[var(--b-signature)]' : 'bg-[#c9c2b4]'"
                  aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                  <p class="text-[14.5px] font-medium tracking-[-.01em]">{{ row.title }}</p>
                  <p class="mt-1 text-[13.5px] leading-[1.55] text-[var(--b-stone)]">{{ row.body }}</p>
                </div>
                <AppIcon name="chevron" :size="15" class="mt-1 shrink-0 text-[#c9c2b4]" />
              </li>
            </ul>
          </div>
        </div>
      </figure>
    </div>
  </section>
</template>
