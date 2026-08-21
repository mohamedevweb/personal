<script setup lang="ts">
/**
 * One measurement, read off the field behind it. The dark twin of
 * `LandingReadout`: same three lines, but set to sit on the night ground with
 * no card under it, so it reads as an instrument pinned to the scene rather
 * than as another panel competing with the brief.
 */
defineProps<{ icon: string, label: string, value: string, note: string }>()
</script>

<template>
  <dl class="flex items-start gap-3">
    <span class="mt-[3px] shrink-0 text-white/30 [filter:drop-shadow(0_2px_10px_rgba(9,8,7,.6))]">
      <AppIcon :name="icon" :size="21" :stroke-width="1.3" />
    </span>

    <div class="[text-shadow:0_2px_16px_rgba(9,8,7,.6)]">
      <dt class="b-mono text-white/50">{{ label }}</dt>
      <dd class="font-display mt-1.5 text-[27px] leading-none tracking-[-.02em] text-[var(--b-ivory)]">{{ value }}</dd>
      <dd class="mt-1.5 max-w-[15rem] text-[12.5px] leading-[1.45] text-white/45">{{ note }}</dd>
    </div>
  </dl>
</template>
