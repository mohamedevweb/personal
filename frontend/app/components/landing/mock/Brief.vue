<script setup lang="ts">
/**
 * The morning brief: the first thing you see of Personal is it already working.
 *
 * The three tabs are the product's three moves, and they are real: clicking one
 * swaps the panel under it. It is still a mock — no request is made, the copy is
 * fixed — but a tab that did nothing would be the one thing above the fold that
 * lies about the product.
 */
const { t } = useI18n()

const TABS = ['understand', 'find', 'remix'] as const
type Tab = typeof TABS[number]

// The middle move is the one the page is arguing for, so it is the one open.
const active = ref<Tab>('find')

const panel = computed(() => {
  const key = `landing.hero.brief.panels.${active.value}`
  return {
    eyebrow: t(`${key}.eyebrow`),
    count: t(`${key}.count`),
    rows: (['one', 'two', 'three'] as const).map((row, index) => ({
      key: row,
      // Only the top row is live-red. A brief where everything is urgent is a
      // brief that says nothing.
      accent: index === 0,
      metric: t(`${key}.rows.${row}.metric`),
      title: t(`${key}.rows.${row}.title`),
      body: t(`${key}.rows.${row}.body`)
    }))
  }
})

// Seven days of niche activity behind the greeting: enough to show the week has
// a shape, small enough to stay furniture.
const WEEK = [34, 48, 41, 62, 55, 78, 96]
</script>

<template>
  <div class="relative">
    <div class="flex items-center justify-between border-b border-[var(--b-line-soft)] px-5 py-4 md:px-7">
      <PersonalLogo :size="15" />

      <div class="hidden items-center gap-6 text-[12.5px] sm:flex" role="tablist" :aria-label="$t('landing.hero.brief.tabsLabel')">
        <button
          v-for="tab in TABS"
          :key="tab"
          type="button"
          role="tab"
          :aria-selected="active === tab"
          class="b-focus relative py-1 transition-colors"
          :class="active === tab ? 'font-medium text-[var(--b-black)]' : 'text-[var(--b-stone)] hover:text-[var(--b-black)]'"
          @click="active = tab"
        >
          {{ $t(`landing.hero.brief.tabs.${tab}`) }}
          <span
            v-if="active === tab"
            class="absolute -bottom-[17px] left-0 right-0 h-[2px] rounded-full bg-[var(--b-black)]"
            aria-hidden="true"
          />
        </button>
      </div>

      <span class="h-7 w-7 rounded-full bg-[#e9e4d9]" aria-hidden="true" />
    </div>

    <div class="px-5 pb-6 pt-6 md:px-7 md:pb-8" role="tabpanel">
      <div class="flex items-start justify-between gap-6">
        <div class="min-w-0">
          <p class="b-mono flex items-center gap-2.5 text-[var(--b-stone)]">
            <span class="b-live" aria-hidden="true" />
            {{ panel.eyebrow }}
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
            :class="index === WEEK.length - 1 ? 'bg-[#a9a294]' : 'bg-[#ded7c8]'"
            :style="{ height: `${(day / 96) * 38}px` }"
          />
        </div>
      </div>

      <p class="b-mono mt-5 flex items-center justify-between border-t border-[var(--b-line-soft)] pt-4 text-[var(--b-stone)]">
        <span>{{ panel.count }}</span>
        <span>{{ $t('landing.hero.brief.updated') }}</span>
      </p>

      <ul class="mt-1 divide-y divide-[var(--b-line-soft)]">
        <li
          v-for="row in panel.rows"
          :key="row.key"
          class="-mx-3 flex items-start gap-3.5 rounded-[10px] px-3 py-4"
          :class="row.accent ? 'bg-[#f6f3ec]' : ''"
        >
          <span
            class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full"
            :class="row.accent ? 'bg-[var(--b-red-500)]' : 'bg-[#c9c2b4]'"
            aria-hidden="true"
          />

          <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-baseline gap-x-2.5 text-[14.5px] font-medium tracking-[-.01em]">
              <span :class="row.accent ? 'font-display text-[19px] tabular-nums' : 'text-[var(--b-stone)]'">{{ row.metric }}</span>
              <span>{{ row.title }}</span>
            </p>
            <p class="mt-1 text-[13.5px] leading-[1.55] text-[var(--b-stone)]">{{ row.body }}</p>
          </div>

          <AppIcon name="chevron" :size="15" class="mt-1 shrink-0 text-[#c9c2b4]" />
        </li>
      </ul>
    </div>
  </div>
</template>
