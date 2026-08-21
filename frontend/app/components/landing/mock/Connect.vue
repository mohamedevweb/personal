<script setup lang="ts">
/** 03 — the half no trend tool has: the pattern held against your own week. */

// The join is the product. It is drawn as a ring closing on a number rather than
// stated in prose, because the whole claim is that the fit is measurable.
const FIT = 94
const CIRCUMFERENCE = 2 * Math.PI * 20
</script>

<template>
  <div class="p-6 md:p-8">
    <div class="rounded-[14px] border border-[var(--b-line-soft)] bg-[#f6f3ec] p-5">
      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.connect.patternLabel') }}</p>
      <p class="mt-3 font-display text-[19px] leading-[1.3] tracking-[-.015em]">{{ $t('landing.how.connect.patternQuote') }}</p>
    </div>

    <!-- The seam. Two hairlines meeting on the fit score. -->
    <div class="flex items-center gap-4 py-4">
      <span class="h-px flex-1 bg-gradient-to-r from-transparent to-[var(--b-line)]" aria-hidden="true" />

      <span class="relative flex h-[52px] w-[52px] shrink-0 items-center justify-center">
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
        <span class="b-metric font-display text-[17px] leading-none">{{ FIT }}</span>
      </span>

      <span class="h-px flex-1 bg-gradient-to-l from-transparent to-[var(--b-line)]" aria-hidden="true" />
    </div>

    <div class="rounded-[14px] border border-[var(--b-line-soft)] p-5">
      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.connect.momentLabel') }}</p>
      <p class="mt-3 font-display text-[19px] leading-[1.3] tracking-[-.015em]">{{ $t('landing.how.connect.momentQuote') }}</p>
    </div>

    <p class="mt-6 border-l border-[var(--b-line)] pl-4 text-[13.5px] leading-[1.6] text-[var(--b-stone)]">
      {{ $t('landing.how.connect.verdict') }}
    </p>
  </div>
</template>
