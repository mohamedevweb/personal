<script setup lang="ts">
const { t } = useI18n()

type Format = 'reel' | 'carousel' | 'post'
const FORMATS: Format[] = ['reel', 'carousel', 'post']

const active = ref<Format>('reel')
const tabs = ref<HTMLButtonElement[]>([])

/** Which carousel slide the preview beside the strip is showing. */
const slide = ref(0)

const beats = computed(() => (['hook', 'two', 'three', 'close'] as const).map(key => ({
  key,
  label: t(`landing.remix.reel.beats.${key}.label`),
  value: t(`landing.remix.reel.beats.${key}.value`)
})))

const slides = computed(() => (['one', 'two', 'three', 'four', 'five'] as const)
  .map(key => t(`landing.remix.carousel.slides.${key}`)))

/** Arrow, Home and End move between formats the way a real tablist does. */
function onKeydown(event: KeyboardEvent, index: number) {
  const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0
  let next: number | null = null

  if (step) next = (index + step + FORMATS.length) % FORMATS.length
  else if (event.key === 'Home') next = 0
  else if (event.key === 'End') next = FORMATS.length - 1
  if (next === null) return

  event.preventDefault()
  active.value = FORMATS[next]!
  tabs.value[next]?.focus()
}
</script>

<template>
  <section class="px-6 pb-24 md:px-10 md:pb-36">
    <div class="mx-auto max-w-[1200px]">
      <LandingStepHeading
        data-reveal
        :step="$t('landing.remix.step')"
        :eyebrow="$t('landing.remix.eyebrow')"
        :title="$t('landing.remix.title')"
        :lede="$t('landing.remix.lede')"
      />

      <div data-reveal class="mt-14 md:mt-16">
        <div class="flex items-center gap-1.5 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] p-1.5 w-fit" role="tablist" :aria-label="$t('landing.remix.formatLabel')">
          <button
            v-for="(format, index) in FORMATS"
            :key="format"
            ref="tabs"
            type="button"
            role="tab"
            :id="`remix-tab-${format}`"
            :aria-selected="active === format"
            :aria-controls="`remix-panel-${format}`"
            :tabindex="active === format ? 0 : -1"
            class="b-focus rounded-full px-5 py-2 text-[14px] font-medium transition-colors duration-300"
            :class="active === format ? 'bg-[var(--b-black)] text-[var(--b-ivory)]' : 'text-[var(--b-stone)] hover:text-[var(--b-black)]'"
            @click="active = format"
            @keydown="onKeydown($event, index)"
          >
            {{ $t(`landing.remix.formats.${format}`) }}
          </button>
        </div>

        <div class="b-panel mt-5 overflow-hidden">
          <div
            v-for="format in FORMATS"
            v-show="active === format"
            :key="format"
            :id="`remix-panel-${format}`"
            role="tabpanel"
            :aria-labelledby="`remix-tab-${format}`"
            tabindex="0"
            class="b-focus"
          >
            <div class="grid gap-10 p-6 md:grid-cols-[1fr_300px] md:gap-12 md:p-9">
              <div>
                <p class="b-eyebrow">{{ $t(`landing.remix.${format}.meta`) }}</p>

                <dl v-if="format === 'reel'" class="mt-7 divide-y divide-[var(--b-line-soft)]">
                  <div v-for="beat in beats" :key="beat.key" class="grid gap-2 py-5 first:pt-0 last:pb-0 sm:grid-cols-[92px_1fr] sm:gap-6">
                    <dt class="b-eyebrow sm:pt-1">{{ beat.label }}</dt>
                    <dd class="font-display text-[20px] leading-[1.35] tracking-[-.01em] md:text-[23px]">{{ beat.value }}</dd>
                  </div>
                </dl>

                <ol v-else-if="format === 'carousel'" class="mt-7 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2">
                  <li v-for="(item, index) in slides" :key="item" class="shrink-0 snap-start">
                    <button
                      type="button"
                      class="b-focus flex aspect-[4/5] w-[176px] flex-col justify-between rounded-[14px] border bg-[#f4f1ea] p-5 text-left transition-colors duration-300"
                      :class="index === slide ? 'border-[var(--b-signature)]' : 'border-[var(--b-line)] hover:border-[#d6cfc1]'"
                      :aria-pressed="index === slide"
                      @click="slide = index"
                    >
                      <span class="b-eyebrow">{{ index + 1 }}/{{ slides.length }}</span>
                      <p class="font-display text-[19px] leading-[1.25] tracking-[-.01em]">{{ item }}</p>
                    </button>
                  </li>
                </ol>

                <p v-else class="mt-7 max-w-[46rem] whitespace-pre-line text-[16px] leading-[1.75]">{{ $t('landing.remix.post.body') }}</p>
              </div>

              <LandingRemixPreview :format="format" :slide="slide" />
            </div>

            <div class="flex flex-col gap-5 border-t border-[var(--b-line-soft)] bg-[#faf8f4] px-6 py-5 md:flex-row md:items-center md:justify-between md:px-9">
              <p class="text-[13px] leading-[1.5] text-[var(--b-stone)]">
                <span class="b-eyebrow">{{ $t('landing.remix.sourceLabel') }}</span>
                <span class="ml-2">{{ $t('landing.remix.sourcePattern') }} + {{ $t('landing.remix.sourceMoment') }}</span>
              </p>
              <div class="flex shrink-0 items-center gap-2">
                <span class="inline-flex h-10 items-center rounded-full bg-[var(--b-black)] px-5 text-[13.5px] font-medium text-[var(--b-ivory)]">{{ $t('landing.remix.use') }}</span>
                <span class="inline-flex h-10 items-center rounded-full border border-[var(--b-line)] px-5 text-[13.5px] text-[var(--b-stone)]">{{ $t('landing.remix.another') }}</span>
              </div>
            </div>
          </div>
        </div>

        <p class="mt-5 text-[13.5px] text-[var(--b-stone)]">{{ $t('landing.remix.never') }}</p>
      </div>
    </div>
  </section>
</template>
