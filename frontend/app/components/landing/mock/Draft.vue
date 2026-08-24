<script setup lang="ts">
/**
 * 04 — one story, three shapes.
 *
 * The claim is that the format is only how the story is cut, so the switch
 * works and the line never changes. Pressing Reel stands the frame up;
 * pressing Légende lays it flat. Same words each time — which is the argument,
 * made without making it.
 */
const live = useScreenLive()

const FORMATS = ['caption', 'carousel', 'reel'] as const
type Format = typeof FORMATS[number]

const format = ref<Format>('carousel')

// The frame each format is read in. Nothing else about the card changes.
const SHAPE: Record<Format, string> = {
  caption: 'aspect-[16/9] w-full',
  carousel: 'aspect-[4/5] w-[188px]',
  reel: 'aspect-[9/16] w-[152px]'
}

// The cover is the only slide that decides whether the rest gets swiped, so
// the deck and the reel are read on the night ground; a caption is read on
// paper, because that is where a caption is read.
const onNight = computed(() => format.value !== 'caption')

const arrived = ref(true)
onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  arrived.value = false
  watch(live, (on) => { if (on) arrived.value = true }, { immediate: true })
})
</script>

<template>
  <LandingMockStage>
    <!-- The switch, as it sits in the app — and working the way it does there. -->
    <div class="flex justify-center">
      <div class="inline-flex items-center gap-1 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] p-1">
        <button
          v-for="option in FORMATS"
          :key="option"
          type="button"
          :aria-pressed="format === option"
          class="b-focus inline-flex h-8 items-center gap-1.5 rounded-full px-3.5 text-[12.5px] transition-colors duration-300"
          :class="format === option
            ? 'bg-[var(--b-black)] font-medium text-[var(--b-ivory)]'
            : 'text-[var(--b-stone)] hover:text-[var(--b-black)]'"
          @click="format = option"
        >
          <AppIcon :name="option === 'caption' ? 'text' : option" :size="14" />
          {{ $t(`remix.formats.${option}`) }}
        </button>
      </div>
    </div>

    <!-- The cut. One card, changing shape and ground, holding the same line. -->
    <div class="mt-7 flex min-h-[236px] items-center justify-center">
      <article
        class="flex flex-col justify-end rounded-[16px] border p-4 transition-all duration-500 ease-out"
        :class="[
          SHAPE[format],
          onNight ? 'b-night border-transparent text-white' : 'border-[var(--b-line)] bg-[var(--b-surface)]',
          arrived ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0'
        ]"
      >
        <span class="b-mono mb-auto" :class="onNight ? 'text-[var(--b-red-lit)]' : 'text-[var(--b-stone)]'">
          {{ $t(`landing.how.draft.marks.${format}`) }}
        </span>

        <p class="font-display text-[18px] leading-[1.25] tracking-[-.01em]">
          {{ $t('landing.how.draft.beats.hook.value') }}
        </p>
      </article>
    </div>

    <!-- The one control that is a decision rather than an edit. -->
    <div class="mt-7 flex justify-center">
      <LandingMockAction class="b-btn-red inline-flex h-10 items-center gap-2 rounded-full px-5 text-[13px] font-medium">
        {{ $t('remix.markReady') }}
        <AppIcon name="arrow" :size="14" />
      </LandingMockAction>
    </div>
  </LandingMockStage>
</template>
