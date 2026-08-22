<script setup lang="ts">
/**
 * One segment of the flow that runs down "how it works": a hairline dropping
 * from one node to the next, elbowing sideways when the two do not share a
 * column, with the step's own caption sitting on the horizontal run.
 *
 * The line is drawn twice. The pale copy is the whole path, always there, so
 * the diagram reads as a complete circuit before you have scrolled it. The
 * signature copy is dashed to its own length and unrolled to zero the moment
 * its step becomes the one being read, so the red travels the page with you.
 *
 * Geometry is expressed against a fixed 1200-wide viewBox — the width of the
 * section's column — so on a desktop the drawing is 1:1 and the corner radius
 * is exactly the radius asked for. Narrower than that, the whole figure scales
 * uniformly and the corners stay corners. On a phone there is no sideways room
 * at all, so the segment becomes a plain drop drawn in CSS.
 */
const props = withDefaults(defineProps<{
  from: number
  to: number
  height: number
  label?: string
  active?: boolean
}>(), { active: false })

const COLUMN = 1200
const RADIUS = 26
// The cubic that fits a quarter circle to within half a pixel at this size.
const KAPPA = 0.5523

const path = computed(() => {
  const { from, to, height } = props
  if (from === to) return `M${from} 0V${height}`

  const direction = to > from ? 1 : -1
  const middle = height / 2
  const pull = RADIUS * KAPPA

  return [
    `M${from} 0`,
    `V${middle - RADIUS}`,
    `C${from} ${middle - RADIUS + pull} ${from + direction * (RADIUS - pull)} ${middle} ${from + direction * RADIUS} ${middle}`,
    `H${to - direction * RADIUS}`,
    `C${to - direction * (RADIUS - pull)} ${middle} ${to} ${middle + RADIUS - pull} ${to} ${middle + RADIUS}`,
    `V${height}`
  ].join(' ')
})

// The caption rides the horizontal run, which is halfway between the two ends.
const labelLeft = computed(() => `${((props.from + props.to) / 2 / COLUMN) * 100}%`)
</script>

<template>
  <div class="relative" :style="{ '--label-x': labelLeft }">
    <!-- Phone: a plain drop, with the signature growing down it. -->
    <div class="relative mx-auto h-14 w-px bg-[var(--b-line)] md:hidden">
      <span
        class="flow-drop absolute inset-x-0 top-0 origin-top bg-[var(--b-red-500)]"
        :class="active ? 'h-full' : 'h-0'"
        aria-hidden="true"
      />
    </div>

    <svg
      :viewBox="`0 0 ${COLUMN} ${height}`"
      class="hidden w-full md:block"
      fill="none"
      aria-hidden="true"
    >
      <g stroke-linecap="round" stroke-width="1.5">
        <path :d="path" stroke="var(--b-line)" />
        <path
          :d="path"
          stroke="var(--b-red-500)"
          pathLength="1"
          stroke-dasharray="1"
          :stroke-dashoffset="active ? 0 : 1"
          class="flow-trace"
        />
      </g>
    </svg>

    <!-- The line is interrupted by its own caption rather than running under
         it: the label belongs to the circuit, not beside it. -->
    <p
      v-if="label"
      class="b-mono absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 whitespace-nowrap bg-[#f2efe8] px-3 text-[var(--b-stone)] md:left-[var(--label-x)]"
    >
      {{ label }}
    </p>
  </div>
</template>

<style scoped>
.flow-trace { transition: stroke-dashoffset 1.1s cubic-bezier(.22, 1, .36, 1); }
.flow-drop { transition: height .8s cubic-bezier(.22, 1, .36, 1); }

@media (prefers-reduced-motion: reduce) {
  .flow-trace,
  .flow-drop { transition: none; }
}
</style>
