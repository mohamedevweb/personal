<script setup lang="ts">
/**
 * 03 — the format held against your own week.
 *
 * Two lines and a number between them. The line on top came out of the feed,
 * the line underneath came out of your week, and the ring says how well they
 * meet. Everything else a Moments screen carries — categories, scores,
 * actions — would only bury the join.
 *
 * The two Moments are switchable, because a score means nothing unless a
 * different one would score differently. Picking the other re-runs the join in
 * front of you, which is a shorter argument than any sentence about it.
 */
const live = useScreenLive()

const CIRCUMFERENCE = 2 * Math.PI * 22

const MOMENTS = [
  { key: 'first', category: 'Failure', fit: 94 },
  { key: 'second', category: 'Win', fit: 71 }
] as const

const picked = ref(0)
const moment = computed(() => MOMENTS[picked.value]!)

/**
 * The ring is driven off its own value rather than off the score, so switching
 * Moments sweeps between the two instead of snapping. The number and the arc
 * move together: they are one readout drawn twice.
 */
const shown = ref<number>(MOMENTS[0].fit)
const offset = computed(() => CIRCUMFERENCE * (1 - shown.value / 100))

let motion = false
let frame = 0
let settle: ReturnType<typeof setTimeout> | null = null

function tweenTo(target: number, duration = 900) {
  cancelAnimationFrame(frame)
  if (settle) clearTimeout(settle)

  if (!motion) {
    shown.value = target
    return
  }

  const from = shown.value
  const start = performance.now()

  const step = (now: number) => {
    const t = Math.min(1, (now - start) / duration)
    shown.value = Math.round(from + (target - from) * (1 - Math.pow(1 - t, 3)))
    if (t < 1) frame = requestAnimationFrame(step)
  }

  frame = requestAnimationFrame(step)

  // A tab that never paints never gets a frame. The score still has to arrive:
  // a ring stuck at zero would be a claim the app failed to make.
  settle = setTimeout(() => { shown.value = target }, duration + 80)
}

function pick(index: number) {
  if (picked.value === index) return
  picked.value = index
  tweenTo(MOMENTS[index]!.fit, 620)
}

onMounted(() => {
  motion = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (!motion) return

  // The join has not run until the step is reached: the ring starts empty.
  shown.value = 0
  let joined = false

  watch(live, (on) => {
    if (!on || joined) return
    joined = true
    tweenTo(moment.value.fit, 1200)
  }, { immediate: true })
})

onUnmounted(() => {
  cancelAnimationFrame(frame)
  if (settle) clearTimeout(settle)
})
</script>

<template>
  <LandingMockStage>
    <!-- What the feed found. -->
    <p class="text-center text-[14px] leading-[1.5] text-[var(--b-stone)]">
      {{ $t('landing.how.connect.patternQuote') }}
    </p>

    <!-- The join, stated as a number because the whole claim is that it is
         measurable. -->
    <div class="my-5 flex items-center gap-4">
      <span class="h-px flex-1 bg-gradient-to-r from-transparent to-[var(--b-line)]" aria-hidden="true" />

      <span class="relative flex h-[52px] w-[52px] shrink-0 items-center justify-center">
        <svg class="absolute inset-0 -rotate-90" viewBox="0 0 56 56" aria-hidden="true">
          <circle cx="28" cy="28" r="22" fill="none" stroke="var(--b-line)" stroke-width="2.5" />
          <circle
            cx="28"
            cy="28"
            r="22"
            fill="none"
            stroke="var(--b-red-500)"
            stroke-width="2.5"
            stroke-linecap="round"
            class="transition-[stroke-dashoffset] duration-500 ease-out"
            :stroke-dasharray="CIRCUMFERENCE"
            :stroke-dashoffset="offset"
          />
        </svg>
        <span class="b-metric font-display text-[18px] leading-none">{{ shown }}</span>
      </span>

      <span class="h-px flex-1 bg-gradient-to-l from-transparent to-[var(--b-line)]" aria-hidden="true" />
    </div>

    <!-- Your week. One line, and the other one is a click away. -->
    <p class="text-center font-display text-[17px] leading-[1.35] tracking-[-.015em]">
      {{ $t(`landing.how.connect.moments.${moment.key}.quote`) }}
    </p>

    <div class="mt-5 flex justify-center gap-2">
      <button
        v-for="(entry, index) in MOMENTS"
        :key="entry.key"
        type="button"
        :aria-pressed="index === picked"
        class="b-focus inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-[11.5px] transition-colors duration-300"
        :class="index === picked
          ? 'border-transparent bg-[var(--b-black)] font-medium text-[var(--b-ivory)]'
          : 'border-[var(--b-line)] bg-[var(--b-surface)] text-[var(--b-stone)] hover:border-[#d6cfc0] hover:text-[var(--b-black)]'"
        @click="pick(index)"
      >
        {{ $t(`moments.categories.${entry.category}`) }}
        <span :class="index === picked ? 'text-white/50' : ''">
          {{ $t(`landing.how.connect.moments.${entry.key}.date`) }}
        </span>
      </button>
    </div>
  </LandingMockStage>
</template>
