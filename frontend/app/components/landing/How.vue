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
</script>

<template>
  <section id="how" class="scroll-mt-24 border-y border-[var(--b-line)] bg-[#f2efe8] px-6 py-24 md:px-10 md:py-32">
    <div class="mx-auto max-w-[1200px]">
      <div data-reveal class="max-w-2xl">
        <p class="b-eyebrow">{{ $t('landing.how.eyebrow') }}</p>
        <h2
          class="mt-6 font-display text-[34px] leading-[1.06] tracking-[-.025em] sm:text-[44px] md:text-[54px]"
          v-html="$t('landing.how.title')"
        />
      </div>

      <div class="mt-14 md:mt-20 md:grid md:grid-cols-[minmax(0,26rem)_1fr] md:items-start md:gap-20">
        <ol class="border-t border-[var(--b-line)] md:border-t-0">
          <li
            v-for="(step, index) in STEPS"
            :key="step.key"
            :ref="(el) => registerBeat(el as Element | null, index)"
            class="border-b border-[var(--b-line)] py-10 transition-opacity duration-500 md:min-h-[58vh] md:border-b-0 md:flex md:flex-col md:justify-center md:py-0"
            :class="index === active ? 'md:opacity-100' : 'md:opacity-40'"
          >
            <p class="b-eyebrow tabular-nums text-[var(--b-signature)]">{{ String(index + 1).padStart(2, '0') }}</p>

            <h3 class="mt-5 font-display text-[28px] leading-none tracking-[-.02em] md:text-[34px]">
              {{ $t(`landing.how.steps.${step.key}.title`) }}
            </h3>

            <p class="mt-4 max-w-[24rem] text-[15px] leading-[1.65] text-[var(--b-stone)] md:text-[16px]">
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

        <div class="hidden md:sticky md:top-28 md:block md:self-start">
          <LandingMedia :src="LANDING_CLIPS[current.key]" :label="$t('landing.how.mediaLabel')">
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
      </div>
    </div>
  </section>
</template>
