<script setup lang="ts">
/**
 * The Personal mark: four petals cut from a disc, leaving a four-pointed star in
 * the negative space. The petal outline is the vector from the Branding board;
 * it is drawn once and rotated, so the mark is exactly symmetrical rather than
 * carrying the small inconsistencies of the traced original.
 */
withDefaults(defineProps<{ size?: number, label?: string }>(), { size: 22 })

const PETAL = 'M148.59 0 L153.99 .08 C153.58 33.87 138.27 69.79 116.62 95.42 C89.09 128.17 49.62 148.57 6.98 152.09 L0 152.59 C5.22 67.22 61.74 5.05 148.59 0 Z'
const ROTATIONS = [0, 90, 180, 270]
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="-163.5 -163.5 327 327"
    :role="label ? 'img' : undefined"
    :aria-hidden="label ? undefined : true"
    focusable="false"
  >
    <title v-if="label">{{ label }}</title>
    <g fill="currentColor">
      <path v-for="angle in ROTATIONS" :key="angle" :d="PETAL" :transform="`rotate(${angle}) translate(9.2 9.2)`" />
    </g>
  </svg>
</template>
