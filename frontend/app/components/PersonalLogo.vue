<script setup lang="ts">
/**
 * Mark plus wordmark. The wordmark is set in the brand display serif rather than
 * shipped as an outline, so it stays crisp at every size and inherits colour.
 *
 * The mark can carry the signature on its own while the wordmark keeps the
 * colour of the text around it. Which red depends on the ground: the palette
 * brightens on the near-black surfaces, so the caller says which one it is
 * standing on rather than hard-coding a value at the call site.
 */
withDefaults(defineProps<{
  size?: number
  label?: string
  tone?: 'inherit' | 'signature' | 'signature-lit'
}>(), { size: 21, tone: 'inherit' })

const TONES = {
  inherit: '',
  signature: 'text-[var(--b-signature)]',
  'signature-lit': 'text-[var(--b-red-lit)]'
}
</script>

<template>
  <span class="inline-flex items-baseline gap-[.32em]" :style="{ fontSize: `${size}px` }">
    <PersonalMark :size="size * 0.92" class="translate-y-[.08em]" :class="TONES[tone]" :label="label" />
    <span class="b-wordmark leading-none">Personal</span>
  </span>
</template>
