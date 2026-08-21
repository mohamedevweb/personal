<script setup lang="ts">
/** The morning brief: the first thing you see of Personal is it already working. */
const { t } = useI18n()

const TABS = ['understand', 'find', 'remix'] as const

const rows = computed(() => (['one', 'two', 'three'] as const).map((key, index) => ({
  key,
  accent: index === 0,
  title: t(`landing.hero.brief.rows.${key}.title`),
  body: t(`landing.hero.brief.rows.${key}.body`)
})))
</script>

<template>
  <div>
    <div class="flex items-center justify-between border-b border-[var(--b-line-soft)] px-5 py-4 md:px-7">
      <PersonalLogo :size="15" />
      <div class="hidden items-center gap-6 text-[12.5px] text-[var(--b-stone)] sm:flex">
        <span v-for="(tab, index) in TABS" :key="tab" :class="index === 1 ? 'text-[var(--b-black)]' : ''">
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
</template>
