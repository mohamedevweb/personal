<script setup lang="ts">
/** 01 — what Personal understood about you, in its own words. */
const FIELDS = ['niche', 'audience', 'tone', 'positioning'] as const
</script>

<template>
  <div class="p-6 md:p-8">
    <p class="b-eyebrow">{{ $t('landing.how.profile.label') }}</p>

    <dl class="mt-6 divide-y divide-[var(--b-line-soft)] border-t border-[var(--b-line-soft)]">
      <div v-for="field in FIELDS" :key="field" class="flex items-baseline justify-between gap-6 py-4">
        <dt class="shrink-0 text-[13px] text-[var(--b-stone)]">{{ $t(`landing.how.profile.${field}`) }}</dt>
        <dd class="text-right text-[14.5px] leading-[1.5] tracking-[-.01em]">{{ $t(`landing.how.profile.${field}Value`) }}</dd>
      </div>
    </dl>
  </div>
</template>
