<script setup lang="ts">
/**
 * 03 — the half no trend tool has: the pattern held against your own week.
 *
 * This is the app's Moments screen. The pattern arrives from the feed at the
 * top, the Moment is the card the product actually draws, and the ring between
 * them is the join — stated as a number rather than as prose, because the whole
 * claim is that the fit is measurable.
 *
 * Which is why the list is live. A score only means something if a different
 * Moment would score differently, so picking the other one re-runs the join in
 * front of you: the ring redraws, the number climbs to somewhere else, the
 * reasons change. One click is a more convincing argument than the sentence
 * underneath it.
 */
const props = withDefaults(defineProps<{ active?: boolean }>(), { active: false })

const CIRCUMFERENCE = 2 * Math.PI * 20

// The two Moments the week left behind, each with what the join found in it.
// The second scores lower on purpose: this is a ranking, not a rubber stamp.
const MOMENTS = [
  { key: 'first', category: 'Failure', fit: 94, potential: '9/10', reasons: ['one', 'two', 'three'] },
  { key: 'second', category: 'Win', fit: 71, potential: '7/10', reasons: ['four', 'five'] }
] as const

const picked = ref(0)
const moment = computed(() => MOMENTS[picked.value]!)

/**
 * The ring is driven off its own value rather than off the score, so that
 * switching Moments sweeps between the two scores instead of snapping. The
 * number and the arc move together: they are one readout drawn twice.
 */
const shown = ref(MOMENTS[0].fit)
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

  // The join has not run until the step is read: the ring starts empty.
  shown.value = 0
  let joined = false

  watch(() => props.active, (on) => {
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
  <LandingMockScreen :title="$t('landing.how.screens.moments')">
    <!-- What came out of the feed, carried over as one line. -->
    <div class="rounded-[12px] border border-[var(--b-line)] bg-[var(--b-surface)] px-4 py-3">
      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.connect.patternLabel') }}</p>
      <p class="mt-2 text-[14px] leading-[1.45] tracking-[-.01em]">{{ $t('landing.how.connect.patternQuote') }}</p>
    </div>

    <!-- The seam. A hairline dropping onto the fit score. -->
    <div class="flex items-center gap-4 py-3">
      <span class="h-px flex-1 bg-gradient-to-r from-transparent to-[var(--b-line)]" aria-hidden="true" />

      <span class="relative flex h-[46px] w-[46px] shrink-0 items-center justify-center">
        <svg class="absolute inset-0 -rotate-90" viewBox="0 0 52 52" aria-hidden="true">
          <circle cx="26" cy="26" r="20" fill="none" stroke="var(--b-line)" stroke-width="2.5" />
          <circle
            cx="26"
            cy="26"
            r="20"
            fill="none"
            stroke="var(--b-red-500)"
            stroke-width="2.5"
            stroke-linecap="round"
            class="transition-[stroke-dashoffset] duration-500 ease-out"
            :stroke-dasharray="CIRCUMFERENCE"
            :stroke-dashoffset="offset"
          />
        </svg>
        <span class="b-metric font-display text-[16px] leading-none">{{ shown }}</span>
      </span>

      <span class="h-px flex-1 bg-gradient-to-l from-transparent to-[var(--b-line)]" aria-hidden="true" />
    </div>

    <!-- The list. The Moment on top is the one being scored; the others are
         one click from taking its place. -->
    <div class="grid gap-3">
      <article
        v-for="(entry, index) in MOMENTS"
        :key="entry.key"
        class="relative rounded-[16px] border border-[var(--b-line)] bg-[var(--b-surface)] p-5 transition-all duration-500"
        :class="index === picked
          ? 'shadow-[0_1px_2px_rgba(23,23,21,.04)]'
          : 'opacity-55 hover:-translate-y-0.5 hover:border-[#d6cfc0] hover:opacity-100'"
      >
        <!-- An unscored Moment is one target, laid over the whole card: the
             card is the button, without a button wrapped around a link. -->
        <button
          v-if="index !== picked"
          type="button"
          class="b-focus absolute inset-0 z-10 cursor-pointer rounded-[16px]"
          :aria-label="$t('landing.how.connect.pick')"
          @click="pick(index)"
        />

        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="rounded-full bg-[var(--b-ivory)] px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--b-stone)]">
                {{ $t(`moments.categories.${entry.category}`) }}
              </span>
              <span class="text-[12px] text-[var(--b-stone)]">{{ $t(`landing.how.connect.moments.${entry.key}.date`) }}</span>
            </div>

            <p class="mt-3.5 text-[15.5px] leading-[1.5] tracking-[-.01em]">
              {{ $t(`landing.how.connect.moments.${entry.key}.quote`) }}
            </p>
          </div>

          <div class="shrink-0 rounded-[12px] bg-[var(--b-red-100)] px-3.5 py-2.5 text-center text-[var(--b-red-700)]">
            <strong class="font-display text-[20px] leading-none">{{ entry.potential }}</strong>
            <p class="mt-1.5 text-[8.5px] uppercase tracking-widest">{{ $t('moments.storyPotential') }}</p>
          </div>
        </div>

        <!-- Only the scored Moment shows its reasons and its action: the join
             is what makes them exist, and the join runs on one Moment. -->
        <div v-if="index === picked">
          <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5">
            <span v-for="reason in entry.reasons" :key="reason" class="text-[12px] text-[var(--b-stone)]">
              ✓ {{ $t(`landing.how.connect.reasons.${reason}`) }}
            </span>
          </div>

          <div class="mt-5 flex items-center gap-3 border-t border-[var(--b-line-soft)] pt-4">
            <LandingMockAction
              class="inline-flex h-9 shrink-0 items-center rounded-full bg-[var(--b-black)] px-4 text-[12.5px] font-medium text-[var(--b-ivory)] hover:bg-black"
            >
              {{ $t('moments.turnIntoContent') }}
            </LandingMockAction>
            <p class="min-w-0 flex-1 text-[12px] leading-[1.5] text-[var(--b-stone)]">{{ $t('landing.how.connect.verdict') }}</p>
          </div>
        </div>
      </article>
    </div>
  </LandingMockScreen>
</template>
