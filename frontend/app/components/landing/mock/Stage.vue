<script setup lang="ts">
/**
 * The ground a step's visual stands on.
 *
 * Each step in "how it works" shows one thing happening, not a screen full of
 * them. The stage is deliberately empty: warm paper, a pool of signature light
 * behind the middle, and one object floating in it. Whatever the object is, it
 * gets the same room and the same light, so four different moves read as four
 * frames of one film.
 *
 * The line underneath is the only prose allowed in here, and it is optional.
 */
defineProps<{ note?: string }>()
</script>

<template>
  <div class="relative flex min-h-[320px] flex-col items-center justify-center overflow-hidden bg-[#f4f1ea] px-6 py-14 sm:min-h-[380px] md:px-12">
    <div class="b-stage-light pointer-events-none absolute inset-0" aria-hidden="true" />

    <div class="relative w-full max-w-[400px]">
      <slot />
    </div>

    <p v-if="note" class="b-mono relative mt-9 flex items-center gap-2.5 text-[var(--b-stone)]">
      <span class="b-live" aria-hidden="true" />
      {{ note }}
    </p>
  </div>
</template>
