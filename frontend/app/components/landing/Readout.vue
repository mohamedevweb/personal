<script setup lang="ts">
/**
 * One measurement from the frame above. Three lines: what was measured, the
 * number, and what makes the number mean something. The value is the only red
 * on the cell, because it is the only fact on it.
 *
 * They sit in a strip under the hero rather than floating over it: a card
 * hanging off the frame covers the very thing it is annotating. The numbers are
 * set in ink — the only red above the fold belongs to the button.
 */
defineProps<{ label: string, value: string, note: string }>()
</script>

<template>
  <div class="flex flex-col gap-1.5 border-t border-[var(--b-line)] pt-4 sm:border-l sm:border-t-0 sm:pl-5 sm:pt-0 sm:first:border-l-0 sm:first:pl-0">
    <p class="b-mono text-[var(--b-stone)]">{{ label }}</p>
    <p class="font-display text-[30px] leading-none tracking-[-.02em] md:text-[34px]">{{ value }}</p>
    <p class="text-[12.5px] leading-[1.4] text-[var(--b-stone)]">{{ note }}</p>
  </div>
</template>
