<script setup lang="ts">
/**
 * The strip between the hero and the argument. It is not a logo wall — an early
 * access product does not have one — so it carries the numbers the product runs
 * on instead, drifting past on a hairline rail.
 *
 * The list is rendered twice: the track translates by exactly one copy, so the
 * loop closes on itself with no visible seam.
 */
const KEYS = ['scanned', 'outliers', 'niches', 'voice', 'minutes'] as const
</script>

<template>
  <section class="relative border-y border-[var(--b-line)] bg-[var(--b-surface)] py-5">
    <div class="b-dots pointer-events-none absolute inset-0 opacity-40" aria-hidden="true" />

    <!-- The rail fades at both ends rather than being clipped, so items arrive
         and leave instead of appearing. -->
    <div
      class="relative flex overflow-hidden"
      style="mask-image: linear-gradient(to right, transparent, #000 8%, #000 92%, transparent)"
    >
      <div class="flex shrink-0 animate-[b-drift_38s_linear_infinite] items-center whitespace-nowrap">
        <template v-for="pass in 2" :key="pass">
          <span
            v-for="key in KEYS"
            :key="`${pass}-${key}`"
            class="flex items-center gap-2.5 px-7"
          >
            <PersonalMark :size="10" class="shrink-0 text-[#cdc6b8]" />
            <span class="b-mono text-[var(--b-stone)]">{{ $t(`landing.proof.${key}`) }}</span>
          </span>
        </template>
      </div>
    </div>
  </section>
</template>
