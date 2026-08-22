<script setup lang="ts">
/**
 * Mark plus wordmark. The wordmark is set in the brand display serif rather than
 * shipped as an outline, so it stays crisp at every size and inherits colour.
 *
 * The mark carries the signature on its own while the wordmark keeps the colour
 * of the text around it; the tone is handed straight to the mark.
 */
withDefaults(defineProps<{
  size?: number
  label?: string
  tone?: 'inherit' | 'signature' | 'signature-lit'
}>(), { size: 21, tone: 'signature' })
</script>

<template>
  <span class="inline-flex items-baseline gap-[.32em]" :style="{ fontSize: `${size}px` }">
    <PersonalMark :size="size * 0.92" class="translate-y-[.08em]" :tone="tone" :label="label" />
    <span class="b-wordmark leading-none">Personal</span>
  </span>
</template>
