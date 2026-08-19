<script setup lang="ts">
/**
 * Mark plus wordmark. The wordmark is set in the brand display serif rather than
 * shipped as an outline, so it stays crisp at every size and inherits colour.
 */
withDefaults(defineProps<{ size?: number, label?: string }>(), { size: 21 })
</script>

<template>
  <span class="inline-flex items-baseline gap-[.32em]" :style="{ fontSize: `${size}px` }">
    <PersonalMark :size="size * 0.92" class="translate-y-[.08em]" :label="label" />
    <span class="b-wordmark leading-none">Personal</span>
  </span>
</template>
