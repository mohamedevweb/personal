<script setup lang="ts">
/**
 * 04 — the winning structure, rewritten as something only you could post.
 *
 * This is the app's remix screen: the format is a live choice, and a carousel
 * is edited as a deck at the size it will be swiped. The cover carries the
 * hook, on the same night ground the product uses everywhere it wants a slide
 * to feel like the first thing seen.
 */
const { t } = useI18n()

const FORMATS = ['caption', 'carousel', 'reel'] as const

// Four slides in a row overflow the frame on purpose: the app's deck scrolls,
// and a cut-off fourth card says so better than a scrollbar would.
const slides = computed(() => (['hook', 'two', 'three', 'close'] as const)
  .map(key => t(`landing.how.draft.beats.${key}.value`)))
</script>

<template>
  <LandingMockScreen :title="$t('landing.how.screens.remix')">
    <!-- The format switch, as it sits in the app. -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="inline-flex items-center gap-1 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] p-1">
        <span
          v-for="format in FORMATS"
          :key="format"
          class="inline-flex h-8 items-center gap-1.5 rounded-full px-3.5 text-[12.5px]"
          :class="format === 'carousel' ? 'bg-[var(--b-black)] font-medium text-[var(--b-ivory)]' : 'text-[var(--b-stone)]'"
        >
          <AppIcon :name="format === 'caption' ? 'text' : format" :size="14" />
          {{ $t(`remix.formats.${format}`) }}
        </span>
      </div>

      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.draft.voice') }}</p>
    </div>

    <!-- The deck. The cover is the only slide that decides whether the rest
         gets swiped, so it is the one on the night ground. -->
    <div class="mt-5 flex gap-3">
      <article
        v-for="(text, index) in slides"
        :key="index"
        class="flex aspect-[4/5] w-[178px] shrink-0 flex-col rounded-[14px] border p-3.5"
        :class="index === 0 ? 'b-night border-transparent text-white' : 'border-[var(--b-line)] bg-[var(--b-surface)]'"
      >
        <header class="flex items-center justify-between">
          <span class="b-mono" :class="index === 0 ? 'text-[var(--b-red-lit)]' : 'text-[var(--b-stone)]'">
            {{ index === 0 ? $t('remix.cover') : $t('remix.slideOf', { index: index + 1, total: slides.length }) }}
          </span>
          <span class="text-[10px] tabular-nums" :class="index === 0 ? 'text-white/40' : 'text-[var(--b-stone)]'">
            {{ text.length }}
          </span>
        </header>

        <p class="mt-3 font-display text-[19px] leading-[1.25] tracking-[-.01em]">{{ text }}</p>
      </article>
    </div>

    <!-- The action bar: what the draft was built from, and the one button that
         is a decision rather than an edit. -->
    <div class="mt-5 flex items-center gap-3 border-t border-[var(--b-line-soft)] pt-4">
      <PersonalMark :size="12" class="shrink-0 text-[var(--b-stone)]" />
      <p class="min-w-0 flex-1 text-[12.5px] leading-[1.5] text-[var(--b-stone)]">{{ $t('landing.how.draft.source') }}</p>
      <span class="b-btn-red inline-flex h-9 shrink-0 items-center rounded-full px-4 text-[12.5px] font-medium">
        {{ $t('remix.markReady') }}
      </span>
    </div>
  </LandingMockScreen>
</template>
