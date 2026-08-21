<script setup lang="ts">
import { LandingMockProfile, LandingMockOutliers, LandingMockConnect, LandingMockDraft } from '#components'

/**
 * The whole mechanism in one section. It used to be four, each with its own
 * headline and lede; the scroll now carries what the prose was carrying, and a
 * single frame on the right shows the step you are reading.
 */
const STEPS = [
  { key: 'understand', mock: LandingMockProfile },
  { key: 'discover', mock: LandingMockOutliers },
  { key: 'connect', mock: LandingMockConnect },
  { key: 'write', mock: LandingMockDraft }
] as const

const active = ref(0)

// Indexed by hand rather than through `ref="beats"`: Vue makes no promise that a
// v-for ref array keeps source order, and here the order is the meaning.
const beats: HTMLElement[] = []
function registerBeat(el: Element | null, index: number) {
  if (el) beats[index] = el as HTMLElement
}

let observer: IntersectionObserver | null = null

onMounted(() => {
  if (!beats.length) return

  // The beat crossing the middle of the viewport is the one being read.
  observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return
      const index = beats.indexOf(entry.target as HTMLElement)
      if (index >= 0) active.value = index
    })
  }, { rootMargin: '-50% 0px -50% 0px', threshold: 0 })

  beats.forEach(node => observer!.observe(node))
})

onUnmounted(() => observer?.disconnect())

const current = computed(() => STEPS[active.value] ?? STEPS[0]!)
// The rail fills to the middle of the active step, so it reads as a position
// rather than as a completed count.
const progress = computed(() => `${((active.value + 0.5) / STEPS.length) * 100}%`)
</script>

<template>
  <section id="how" class="relative scroll-mt-24 overflow-hidden border-y border-[var(--b-line)] bg-[#f2efe8] px-5 py-24 md:px-10 md:py-32">
    <div class="b-graph b-fade-b pointer-events-none absolute inset-0 opacity-70" aria-hidden="true" />

    <div class="relative mx-auto max-w-[1200px]">
      <div data-reveal class="max-w-2xl">
        <p class="b-mono text-[var(--b-red-600)]">{{ $t('landing.how.eyebrow') }}</p>
        <h2
          class="mt-6 font-display text-[36px] leading-[1.04] tracking-[-.025em] sm:text-[46px] md:text-[56px]"
          v-html="$t('landing.how.title')"
        />
      </div>

      <div class="mt-14 md:mt-20 md:grid md:grid-cols-[minmax(0,27rem)_1fr] md:items-start md:gap-20">
        <ol class="relative border-t border-[var(--b-line)] md:border-t-0 md:pl-9">
          <!-- The rail only exists on a desktop, where the steps are tall enough
               for a position along it to mean anything. -->
          <div class="absolute bottom-0 left-0 top-0 hidden w-px bg-[var(--b-line)] md:block" aria-hidden="true">
            <div
              class="w-px bg-gradient-to-b from-[var(--b-red-400)] to-[var(--b-red-600)] transition-[height] duration-700 ease-out"
              :style="{ height: progress }"
            />
          </div>

          <li
            v-for="(step, index) in STEPS"
            :key="step.key"
            :ref="(el) => registerBeat(el as Element | null, index)"
            class="relative border-b border-[var(--b-line)] py-10 transition-opacity duration-500 md:min-h-[58vh] md:border-b-0 md:flex md:flex-col md:justify-center md:py-0"
            :class="index === active ? 'md:opacity-100' : 'md:opacity-45'"
          >
            <!-- The dot on the rail marking this beat's own place. -->
            <span
              class="absolute -left-9 top-1/2 hidden h-2 w-2 -translate-x-[3.5px] -translate-y-1/2 rounded-full transition-colors duration-500 md:block"
              :class="index <= active ? 'bg-[var(--b-red-500)]' : 'bg-[var(--b-line)]'"
              aria-hidden="true"
            />

            <p class="b-mono flex items-center gap-3 text-[var(--b-red-600)]">
              {{ String(index + 1).padStart(2, '0') }}
              <span class="h-px w-8 bg-[var(--b-red-200)]" aria-hidden="true" />
            </p>

            <h3 class="mt-5 font-display text-[30px] leading-none tracking-[-.02em] md:text-[36px]">
              {{ $t(`landing.how.steps.${step.key}.title`) }}
            </h3>

            <p class="mt-4 max-w-[25rem] text-[15px] leading-[1.65] text-[var(--b-stone)] md:text-[16.5px]">
              {{ $t(`landing.how.steps.${step.key}.body`) }}
            </p>

            <!-- On a phone there is nothing to pin against, so each beat carries
                 its own frame instead. -->
            <LandingMedia
              :src="LANDING_CLIPS[step.key]"
              :label="$t('landing.how.mediaLabel')"
              class="mt-8 md:hidden"
            >
              <component :is="step.mock" />
            </LandingMedia>
          </li>
        </ol>

        <!-- One frame, held still. The minimum height is the tallest mock, so
             swapping steps never nudges the layout — and it is the shape the
             clips are cut to once they land. -->
        <div class="hidden md:sticky md:top-28 md:block md:self-start">
          <div class="relative">
            <div class="b-glow-blob pointer-events-none absolute -inset-10 -z-10 opacity-50" aria-hidden="true" />

            <LandingMedia
              :src="LANDING_CLIPS[current.key]"
              :label="$t('landing.how.mediaLabel')"
              class="min-h-[27rem]"
            >
              <Transition
                mode="out-in"
                enter-active-class="transition-opacity duration-300 ease-out"
                leave-active-class="transition-opacity duration-200 ease-in"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
              >
                <component :is="current.mock" :key="current.key" />
              </Transition>
            </LandingMedia>
          </div>

          <p class="b-mono mt-5 flex items-center gap-2.5 text-[var(--b-stone)]">
            <span class="b-live" aria-hidden="true" />
            {{ $t(`landing.how.steps.${current.key}.caption`) }}
          </p>
        </div>
      </div>
    </div>
  </section>
</template>
