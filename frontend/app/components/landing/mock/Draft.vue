<script setup lang="ts">
/**
 * 04 — the winning structure, rewritten as something only you could post.
 *
 * This is the app's remix screen, and the format switch is the whole point of
 * it: one Moment, one structure, three shapes. Claiming that in a caption is
 * cheap, so the switch works. Press Reel and the beats become a shot list with
 * timecodes; press Légende and they become the paragraph you would actually
 * paste. The words never change — which is the argument. The story was written
 * once, and the format is only how it is cut.
 */
const { t } = useI18n()

const FORMATS = ['caption', 'carousel', 'reel'] as const
type Format = typeof FORMATS[number]

const BEATS = ['hook', 'two', 'three', 'close'] as const

// Where each beat lands in a reel. Not decoration: the hook owns the first
// three seconds, and the rest of the cut is paced off that.
const MARKS = ['0:00', '0:04', '0:11', '0:19'] as const

const format = ref<Format>('carousel')

const beats = computed(() => BEATS.map((key, index) => ({
  key,
  label: t(`landing.how.draft.beats.${key}.label`),
  value: t(`landing.how.draft.beats.${key}.value`),
  mark: MARKS[index]!
})))

// The caption is the same beats, run together as prose: the hook on its own
// line, the middle as the body, the close landing alone.
const caption = computed(() => beats.value.map(beat => beat.value))
const captionLength = computed(() => caption.value.join('\n\n').length)
</script>

<template>
  <LandingMockScreen :title="$t('landing.how.screens.remix')">
    <h3 class="font-display text-[24px] leading-none tracking-[-.025em]">{{ $t('remix.madeFromStory') }}</h3>
    <p class="mt-2.5 max-w-[46ch] text-[13px] leading-[1.5] text-[var(--b-stone)]">{{ $t('remix.madeFromStoryCopy') }}</p>

    <!-- The format switch, as it sits in the app — and working the way it does
         there. -->
    <div class="mt-4 flex flex-wrap items-center gap-3">
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

      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.draft.voice') }}</p>
    </div>

    <!-- The cut. Same story every time; only the shape it is poured into
         changes, which is why the three views sit under one heading row. -->
    <div class="mt-5 flex items-baseline justify-between">
      <p class="b-mono text-[var(--b-stone)]">{{ $t(`landing.how.draft.views.${format}`) }}</p>
      <p class="text-[12px] tabular-nums text-[var(--b-stone)]">
        <template v-if="format === 'carousel'">{{ $t('remix.slideCount', { count: beats.length }) }}</template>
        <template v-else-if="format === 'reel'">{{ $t('landing.how.draft.runtime') }}</template>
        <template v-else>{{ $t('landing.how.draft.characters', { count: captionLength }) }}</template>
      </p>
    </div>

    <Transition name="b-swap" mode="out-in">
      <!-- The deck. The cover is the only slide that decides whether the rest
           gets swiped, so it is the one on the night ground. -->
      <div v-if="format === 'carousel'" key="carousel" class="mt-3 flex gap-3">
        <article
          v-for="(beat, index) in beats"
          :key="beat.key"
          class="flex aspect-[4/5] w-[178px] shrink-0 flex-col rounded-[14px] border p-3.5 transition-transform duration-300 hover:-translate-y-1"
          :class="index === 0 ? 'b-night border-transparent text-white' : 'border-[var(--b-line)] bg-[var(--b-surface)]'"
        >
          <header class="flex items-center justify-between">
            <span class="b-mono" :class="index === 0 ? 'text-[var(--b-red-lit)]' : 'text-[var(--b-stone)]'">
              {{ index === 0 ? $t('remix.cover') : $t('remix.slideOf', { index: index + 1, total: beats.length }) }}
            </span>
            <span class="text-[10px] tabular-nums" :class="index === 0 ? 'text-white/40' : 'text-[var(--b-stone)]'">
              {{ beat.value.length }}
            </span>
          </header>

          <p class="mt-3 font-display text-[19px] leading-[1.25] tracking-[-.01em]">{{ beat.value }}</p>
        </article>
      </div>

      <!-- The reel: the vertical frame the hook is read in, and the cut beside
           it, timed. -->
      <div v-else-if="format === 'reel'" key="reel" class="mt-3 flex gap-4">
        <div class="b-night relative flex aspect-[9/16] w-[150px] shrink-0 flex-col justify-end rounded-[14px] p-3.5 text-white">
          <span class="b-mono absolute left-3.5 top-3.5 text-[var(--b-red-lit)]">{{ MARKS[0] }}</span>
          <span class="absolute right-3.5 top-3.5 grid h-6 w-6 place-items-center rounded-full bg-white/10">
            <AppIcon name="reel" :size="12" />
          </span>
          <p class="font-display text-[17px] leading-[1.2] tracking-[-.01em]">{{ beats[0]!.value }}</p>
          <span class="mt-3 h-[3px] w-full overflow-hidden rounded-full bg-white/15">
            <span class="b-play block h-full w-1/4 rounded-full bg-[var(--b-red-lit)]" />
          </span>
        </div>

        <ol class="min-w-0 flex-1 space-y-2">
          <li
            v-for="(beat, index) in beats"
            :key="beat.key"
            class="flex items-start gap-3 rounded-[12px] border border-[var(--b-line)] bg-[var(--b-surface)] px-3.5 py-2.5"
          >
            <span class="b-mono mt-[3px] shrink-0 tabular-nums" :class="index === 0 ? 'text-[var(--b-red-600)]' : 'text-[var(--b-stone)]'">
              {{ beat.mark }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="b-mono block text-[var(--b-stone)]">{{ beat.label }}</span>
              <span class="mt-1 block text-[13px] leading-[1.45]">{{ beat.value }}</span>
            </span>
          </li>
        </ol>
      </div>

      <!-- The caption: the same beats run together, in the box it gets pasted
           into. -->
      <div v-else key="caption" class="mt-3 rounded-[14px] border border-[var(--b-line)] bg-[var(--b-surface)] p-4">
        <p
          v-for="(line, index) in caption"
          :key="index"
          class="text-[14px] leading-[1.6]"
          :class="[index > 0 ? 'mt-3' : '', index === 0 ? 'font-display text-[17px] leading-[1.3] tracking-[-.01em]' : 'text-[var(--b-stone)]']"
        >
          {{ line }}
        </p>
      </div>
    </Transition>

    <!-- The action bar: what the draft was built from, and the one button that
         is a decision rather than an edit. -->
    <div class="mt-5 flex items-center gap-3 border-t border-[var(--b-line-soft)] pt-4">
      <PersonalMark :size="12" class="shrink-0 text-[var(--b-stone)]" />
      <p class="min-w-0 flex-1 text-[12.5px] leading-[1.5] text-[var(--b-stone)]">{{ $t('landing.how.draft.source') }}</p>
      <LandingMockAction class="b-btn-red inline-flex h-9 shrink-0 items-center rounded-full px-4 text-[12.5px] font-medium">
        {{ $t('remix.markReady') }}
      </LandingMockAction>
    </div>
  </LandingMockScreen>
</template>
