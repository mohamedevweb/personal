<script setup lang="ts">
/**
 * A single measurement, lifted off the frame it belongs to. Three lines: what
 * was measured, the number, and what makes the number mean something. The
 * value is the only red on the card, because it is the only fact on it.
 */
defineProps<{ label: string, value: string, note: string }>()
</script>

<template>
  <div
    data-reveal
    class="b-panel b-lift flex-col gap-1 rounded-[14px] border-[var(--b-line-soft)] px-4 py-3.5 backdrop-blur-sm"
  >
    <p class="b-mono text-[var(--b-stone)]">{{ label }}</p>
    <p class="b-metric font-display text-[30px] leading-none tracking-[-.02em]">{{ value }}</p>
    <p class="mt-0.5 text-[12px] leading-[1.35] text-[var(--b-stone)]">{{ note }}</p>
  </div>
</template>
