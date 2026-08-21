<script setup lang="ts">
/** The morning brief: the first thing you see of Personal is it already working. */
const { t } = useI18n()

const TABS = ['understand', 'find', 'remix'] as const

const rows = computed(() => (['one', 'two', 'three'] as const).map((key, index) => ({
  key,
  // Only the top row is live-red. A brief where everything is urgent is a brief
  // that says nothing.
  accent: index === 0,
  metric: t(`landing.hero.brief.rows.${key}.metric`),
  title: t(`landing.hero.brief.rows.${key}.title`),
  body: t(`landing.hero.brief.rows.${key}.body`)
})))

// Seven days of niche activity behind the greeting: enough to show the week has
// a shape, small enough to stay furniture.
const WEEK = [34, 48, 41, 62, 55, 78, 96]
</script>

<template>
  <div class="relative">
    <!-- The product's own top rail. Red, full bleed, one pixel: the same line
         the app draws over an active workspace. -->
    <div class="h-[3px] bg-gradient-to-r from-[var(--b-red-500)] via-[var(--b-red-400)] to-transparent" aria-hidden="true" />

    <div class="flex items-center justify-between border-b border-[var(--b-line-soft)] px-5 py-4 md:px-7">
      <PersonalLogo :size="15" />

      <div class="hidden items-center gap-6 text-[12.5px] text-[var(--b-stone)] sm:flex">
        <span
          v-for="(tab, index) in TABS"
          :key="tab"
          class="relative py-1"
          :class="index === 1 ? 'font-medium text-[var(--b-black)]' : ''"
        >
          {{ $t(`landing.hero.brief.tabs.${tab}`) }}
          <span
            v-if="index === 1"
            class="absolute -bottom-[17px] left-0 right-0 h-[2px] rounded-full bg-[var(--b-red-500)]"
            aria-hidden="true"
          />
        </span>
      </div>

      <span class="h-7 w-7 rounded-full bg-[#e9e4d9] ring-2 ring-[var(--b-red-200)] ring-offset-2 ring-offset-[var(--b-surface)]" aria-hidden="true" />
    </div>

    <div class="px-5 pb-6 pt-6 md:px-7 md:pb-8">
      <div class="flex items-start justify-between gap-6">
        <div class="min-w-0">
          <p class="b-mono flex items-center gap-2.5 text-[var(--b-red-600)]">
            <span class="b-live" aria-hidden="true" />
            {{ $t('landing.hero.brief.eyebrow') }}
          </p>

          <p class="font-display mt-3 text-[27px] tracking-[-.02em] md:text-[32px]">{{ $t('landing.hero.brief.greeting') }}</p>
        </div>

        <!-- The week's activity in your niche, as furniture rather than as a
             chart: it is context for the greeting, not a thing to read. -->
        <div class="hidden shrink-0 items-end gap-[3px] sm:flex" aria-hidden="true">
          <span
            v-for="(day, index) in WEEK"
            :key="index"
            class="w-[5px] rounded-[1px]"
            :class="index === WEEK.length - 1 ? 'bg-[var(--b-red-500)]' : 'bg-[#ded7c8]'"
            :style="{ height: `${(day / 96) * 38}px` }"
          />
        </div>
      </div>

      <p class="b-mono mt-5 flex items-center justify-between border-t border-[var(--b-line-soft)] pt-4 text-[var(--b-stone)]">
        <span>{{ $t('landing.hero.brief.count') }}</span>
        <span class="text-[var(--b-red-600)]">{{ $t('landing.hero.brief.updated') }}</span>
      </p>

      <ul class="mt-1 divide-y divide-[var(--b-line-soft)]">
        <li
          v-for="row in rows"
          :key="row.key"
          class="-mx-3 flex items-start gap-3.5 rounded-[10px] px-3 py-4"
          :class="row.accent ? 'bg-[var(--b-red-50)]' : ''"
        >
          <span
            class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full"
            :class="row.accent ? 'bg-[var(--b-red-500)]' : 'bg-[#c9c2b4]'"
            aria-hidden="true"
          />

          <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-baseline gap-x-2.5 text-[14.5px] font-medium tracking-[-.01em]">
              <span :class="row.accent ? 'b-metric font-display text-[19px]' : ''">{{ row.metric }}</span>
              <span>{{ row.title }}</span>
            </p>
            <p class="mt-1 text-[13.5px] leading-[1.55] text-[var(--b-stone)]">{{ row.body }}</p>
          </div>

          <AppIcon
            name="chevron"
            :size="15"
            class="mt-1 shrink-0"
            :class="row.accent ? 'text-[var(--b-red-400)]' : 'text-[#c9c2b4]'"
          />
        </li>
      </ul>
    </div>
  </div>
</template>
