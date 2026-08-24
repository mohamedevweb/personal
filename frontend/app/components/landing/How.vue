<script setup lang="ts">
import { LandingMockProfile, LandingMockOutliers, LandingMockConnect, LandingMockDraft } from '#components'

/**
 * The whole mechanism, drawn as one circuit running down the page.
 *
 * Each step is a node — a pill naming the move, the sentence that explains it —
 * wired by a hairline into the screen where that move actually happens. The
 * node sits left, then right, then left again, so the line has somewhere to go
 * and the eye has a reason to follow it. The signature unrolls along the wire
 * as you arrive at each step: the diagram is complete from the first glance,
 * but only lit as far as you have read.
 */
const STEPS = [
  { key: 'understand', mock: LandingMockProfile },
  { key: 'discover', mock: LandingMockOutliers },
  { key: 'connect', mock: LandingMockConnect },
  { key: 'write', mock: LandingMockDraft }
] as const

// The two columns the nodes alternate between, and the middle the screens sit
// in. They are viewBox units of the 1200-wide column, and they are also where
// a 440-wide block pinned to either edge puts its own centre.
const LEFT = 220
const RIGHT = 980
const CENTER = 600

const nodeX = (index: number) => (index % 2 === 0 ? LEFT : RIGHT)

const active = ref(0)

// Indexed by hand rather than through `ref="stages"`: Vue makes no promise that
// a v-for ref array keeps source order, and here the order is the meaning.
const stages: HTMLElement[] = []
function registerStage(el: Element | null, index: number) {
  if (el) stages[index] = el as HTMLElement
}

let observer: IntersectionObserver | null = null

onMounted(() => {
  if (!stages.length) return

  // The stage crossing the middle of the viewport is the one being read.
  observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return
      const index = stages.indexOf(entry.target as HTMLElement)
      if (index >= 0) active.value = index
    })
  }, { rootMargin: '-45% 0px -45% 0px', threshold: 0 })

  stages.forEach(node => observer!.observe(node))
})

onUnmounted(() => observer?.disconnect())
</script>

<template>
  <section id="how" class="relative scroll-mt-24 overflow-hidden border-y border-[var(--b-line)] bg-[#f2efe8] px-5 py-24 md:px-10 md:py-32">
    <div class="b-graph b-fade-b pointer-events-none absolute inset-0 opacity-70" aria-hidden="true" />

    <div class="relative mx-auto max-w-[1200px]">
      <div data-reveal class="mx-auto max-w-2xl text-center">
        <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.eyebrow') }}</p>
        <h2
          class="mt-6 font-display text-[36px] leading-[1.04] tracking-[-.025em] sm:text-[46px] md:text-[56px]"
          v-html="$t('landing.how.title')"
        />
      </div>

      <div class="mt-14 md:mt-16">
        <template v-for="(step, index) in STEPS" :key="step.key">
          <!-- The wire arriving from the screen above, swinging across to the
               column this node stands in. -->
          <LandingFlowLink
            v-if="index > 0"
            :from="CENTER"
            :to="nodeX(index)"
            :height="112"
            :active="index <= active"
          />

          <!-- The node. A 440 block pinned to its edge puts its own centre on
               the column the wire leaves from. -->
          <div :ref="(el) => registerStage(el as Element | null, index)" class="flex" :class="index % 2 === 0 ? 'justify-start' : 'justify-end'">
            <div class="w-full text-center md:w-[440px]" :class="index % 2 === 0 ? 'md:text-left' : 'md:text-right'">
              <span
                class="inline-flex items-center gap-2.5 rounded-full px-4 py-2 text-[12.5px] font-medium transition-colors duration-500"
                :class="index === active
                  ? 'b-btn-red'
                  : 'border border-[var(--b-line)] bg-[var(--b-surface)] text-[var(--b-stone)]'"
              >
                <PersonalMark :size="12" :tone="index === active ? 'inherit' : 'signature'" />
                {{ String(index + 1).padStart(2, '0') }} · {{ $t(`landing.how.steps.${step.key}.title`) }}
              </span>

              <p class="mx-auto mt-5 max-w-[25rem] text-[15px] leading-[1.65] text-[var(--b-stone)] md:mx-0 md:text-[16px]">
                {{ $t(`landing.how.steps.${step.key}.body`) }}
              </p>
            </div>
          </div>

          <!-- The wire from the node into the screen, carrying what the step is
               doing while it runs. -->
          <LandingFlowLink
            :from="nodeX(index)"
            :to="CENTER"
            :height="112"
            :label="$t(`landing.how.steps.${step.key}.caption`)"
            :active="index <= active"
          />

          <!-- The frame. One object doing one thing rather than a screen full
               of them: the step is the point, and everything the app also
               happens to draw would only bury it. -->
          <div class="relative mx-auto max-w-[680px]">
            <div
              class="b-glow-blob pointer-events-none absolute -inset-8 -z-10 transition-opacity duration-700"
              :class="index === active ? 'opacity-70' : 'opacity-0'"
              aria-hidden="true"
            />

            <LandingMedia :src="LANDING_CLIPS[step.key]" :label="$t('landing.how.mediaLabel')">
              <!-- The frame tells the visual when it has been reached, so the
                   step runs its own move at the moment it is looked at. -->
              <component :is="step.mock" />
            </LandingMedia>
          </div>
        </template>
      </div>
    </div>
  </section>
</template>
