<script setup lang="ts">
/** 04 — the winning structure, rewritten as something only you could post. */
const BEATS = ['hook', 'two', 'three', 'close'] as const
</script>

<template>
  <div class="p-6 md:p-8">
    <div class="flex items-center gap-2 text-[var(--b-stone)]">
      <AppIcon name="reel" :size="15" />
      <p class="b-eyebrow">{{ $t('landing.how.draft.label') }}</p>
    </div>

    <ol class="mt-6 divide-y divide-[var(--b-line-soft)] border-t border-[var(--b-line-soft)]">
      <li v-for="beat in BEATS" :key="beat" class="py-4">
        <p class="b-eyebrow !text-[11px]">{{ $t(`landing.how.draft.beats.${beat}.label`) }}</p>
        <p class="mt-2 text-[14.5px] leading-[1.5] tracking-[-.01em]">{{ $t(`landing.how.draft.beats.${beat}.value`) }}</p>
      </li>
    </ol>
  </div>
</template>
