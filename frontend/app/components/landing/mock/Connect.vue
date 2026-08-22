<script setup lang="ts">
/**
 * 03 — the half no trend tool has: the pattern held against your own week.
 *
 * This is the app's Moments screen. The pattern arrives from the feed at the
 * top, the Moment is the card the product actually draws, and the ring between
 * them is the join — stated as a number rather than as prose, because the whole
 * claim is that the fit is measurable.
 */
const FIT = 94
const CIRCUMFERENCE = 2 * Math.PI * 20
const REASONS = ['one', 'two', 'three'] as const
</script>

<template>
  <LandingMockScreen :title="$t('landing.how.screens.moments')">
    <!-- What came out of the feed, carried over as one line. -->
    <div class="rounded-[12px] border border-[var(--b-line)] bg-[var(--b-surface)] px-4 py-3">
      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.connect.patternLabel') }}</p>
      <p class="mt-2 text-[14px] leading-[1.45] tracking-[-.01em]">{{ $t('landing.how.connect.patternQuote') }}</p>
    </div>

    <!-- The seam. A hairline dropping onto the fit score. -->
    <div class="flex items-center gap-4 py-3">
      <span class="h-px flex-1 bg-gradient-to-r from-transparent to-[var(--b-line)]" aria-hidden="true" />

      <span class="relative flex h-[46px] w-[46px] shrink-0 items-center justify-center">
        <svg class="absolute inset-0 -rotate-90" viewBox="0 0 52 52" aria-hidden="true">
          <circle cx="26" cy="26" r="20" fill="none" stroke="var(--b-line)" stroke-width="2.5" />
          <circle
            cx="26"
            cy="26"
            r="20"
            fill="none"
            stroke="var(--b-red-500)"
            stroke-width="2.5"
            stroke-linecap="round"
            :stroke-dasharray="CIRCUMFERENCE"
            :stroke-dashoffset="CIRCUMFERENCE * (1 - FIT / 100)"
          />
        </svg>
        <span class="b-metric font-display text-[16px] leading-none">{{ FIT }}</span>
      </span>

      <span class="h-px flex-1 bg-gradient-to-l from-transparent to-[var(--b-line)]" aria-hidden="true" />
    </div>

    <!-- The Moment, as the app draws it. -->
    <article class="rounded-[16px] border border-[var(--b-line)] bg-[var(--b-surface)] p-5 shadow-[0_1px_2px_rgba(23,23,21,.04)]">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="rounded-full bg-[var(--b-ivory)] px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--b-stone)]">
              {{ $t('moments.categories.Failure') }}
            </span>
            <span class="text-[12px] text-[var(--b-stone)]">{{ $t('landing.how.connect.momentDate') }}</span>
          </div>

          <p class="mt-3.5 text-[15.5px] leading-[1.5] tracking-[-.01em]">{{ $t('landing.how.connect.momentQuote') }}</p>
        </div>

        <div class="shrink-0 rounded-[12px] bg-[var(--b-red-100)] px-3.5 py-2.5 text-center text-[var(--b-red-700)]">
          <strong class="font-display text-[20px] leading-none">9/10</strong>
          <p class="mt-1.5 text-[8.5px] uppercase tracking-widest">{{ $t('moments.storyPotential') }}</p>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5">
        <span v-for="reason in REASONS" :key="reason" class="text-[12px] text-[var(--b-stone)]">
          ✓ {{ $t(`landing.how.connect.reasons.${reason}`) }}
        </span>
      </div>

      <div class="mt-5 flex items-center gap-3 border-t border-[var(--b-line-soft)] pt-4">
        <span class="inline-flex h-9 items-center rounded-full bg-[var(--b-black)] px-4 text-[12.5px] font-medium text-[var(--b-ivory)]">
          {{ $t('moments.turnIntoContent') }}
        </span>
        <p class="min-w-0 flex-1 text-[12px] leading-[1.5] text-[var(--b-stone)]">{{ $t('landing.how.connect.verdict') }}</p>
      </div>
    </article>

    <!-- The list goes on. The next Moment is there, waiting for the week that
         makes it worth writing. -->
    <article class="mt-3 flex items-start justify-between gap-4 rounded-[16px] border border-[var(--b-line)] bg-[var(--b-surface)] p-5 opacity-55">
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <span class="rounded-full bg-[var(--b-ivory)] px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--b-stone)]">
            {{ $t('moments.categories.Win') }}
          </span>
          <span class="text-[12px] text-[var(--b-stone)]">{{ $t('landing.how.connect.second.date') }}</span>
        </div>
        <p class="mt-3.5 text-[15.5px] leading-[1.5] tracking-[-.01em]">{{ $t('landing.how.connect.second.quote') }}</p>
      </div>

      <div class="shrink-0 rounded-[12px] bg-[var(--b-red-100)] px-3.5 py-2.5 text-center text-[var(--b-red-700)]">
        <strong class="font-display text-[20px] leading-none">7/10</strong>
        <p class="mt-1.5 text-[8.5px] uppercase tracking-widest">{{ $t('moments.storyPotential') }}</p>
      </div>
    </article>
  </LandingMockScreen>
</template>
