<script setup lang="ts">
/** 03 — the half no trend tool has: the pattern held against your own week. */
</script>

<template>
  <div class="p-6 md:p-8">
    <div class="rounded-[14px] border border-[var(--b-line-soft)] p-5">
      <p class="b-eyebrow">{{ $t('landing.how.connect.patternLabel') }}</p>
      <p class="mt-3 font-display text-[19px] leading-[1.3] tracking-[-.015em]">{{ $t('landing.how.connect.patternQuote') }}</p>
    </div>

    <div class="flex items-center gap-3 py-3" aria-hidden="true">
      <span class="h-px flex-1 bg-[var(--b-line)]" />
      <PersonalMark :size="16" class="text-[var(--b-signature)]" />
      <span class="h-px flex-1 bg-[var(--b-line)]" />
    </div>

    <div class="rounded-[14px] border border-[var(--b-line-soft)] p-5">
      <p class="b-eyebrow">{{ $t('landing.how.connect.momentLabel') }}</p>
      <p class="mt-3 font-display text-[19px] leading-[1.3] tracking-[-.015em]">{{ $t('landing.how.connect.momentQuote') }}</p>
    </div>

    <p class="mt-6 text-[13.5px] leading-[1.6] text-[var(--b-stone)]">{{ $t('landing.how.connect.verdict') }}</p>
  </div>
</template>
