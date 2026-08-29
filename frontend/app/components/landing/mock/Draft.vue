<script setup lang="ts">
/**
 * 04 — one story, two production-ready drafts.
 *
 * This mirrors the remix editor closely enough to make the format switch
 * honest: a carousel is a swipeable deck and a Reel is a timed script.
 */
const live = useScreenLive()

const FORMATS = ['reel', 'carousel'] as const
type Format = typeof FORMATS[number]

const format = ref<Format>('carousel')

const arrived = ref(true)

function selectFormat(next: Format) {
  format.value = next
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  arrived.value = false
  requestAnimationFrame(() => { arrived.value = true })
}

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  arrived.value = false
  watch(live, (on) => { if (on) arrived.value = true }, { immediate: true })
})
</script>

<template>
  <LandingMockStage>
    <div class="flex justify-center">
      <div
        class="inline-flex items-center gap-1 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] p-1"
        role="tablist"
        :aria-label="$t('remix.formatLabel')"
      >
        <button
          v-for="option in FORMATS"
          :key="option"
          type="button"
          role="tab"
          :aria-selected="format === option"
          class="b-focus inline-flex h-8 items-center gap-1.5 rounded-full px-3.5 text-[12.5px] transition-colors duration-300"
          :class="format === option
            ? 'bg-[var(--b-black)] font-medium text-[var(--b-ivory)]'
            : 'text-[var(--b-stone)] hover:text-[var(--b-black)]'"
          @click="selectFormat(option)"
        >
          <AppIcon :name="option" :size="14" />
          {{ $t(`remix.formats.${option}`) }}
        </button>
      </div>
    </div>

    <div
      class="mt-6 flex min-h-[242px] items-center justify-center transition duration-500 ease-out"
      :class="arrived ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'"
    >
      <!-- A carousel is read as a sequence, so the preview exposes the deck
           instead of pretending the cover is the whole deliverable. -->
      <div v-if="format === 'carousel'" class="w-full">
        <div class="mb-2.5 flex items-center justify-between px-0.5">
          <span class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.draft.carousel.label') }}</span>
          <span class="text-[10.5px] text-[var(--b-stone)]">{{ $t('landing.how.draft.carousel.count') }}</span>
        </div>
        <div class="grid grid-cols-3 gap-2">
          <article class="b-night flex aspect-[4/5] min-w-0 flex-col rounded-[13px] p-3 text-white">
            <span class="b-mono text-[var(--b-red-lit)]">{{ $t('landing.how.draft.carousel.cover') }}</span>
            <p class="mt-auto font-display text-[15px] leading-[1.16] tracking-[-.01em]">
              {{ $t('landing.how.draft.beats.hook.value') }}
            </p>
          </article>
          <article class="flex aspect-[4/5] min-w-0 flex-col rounded-[13px] border border-[var(--b-line)] bg-[var(--b-surface)] p-3">
            <span class="b-mono text-[var(--b-stone)]">02 / 06</span>
            <p class="mt-auto text-[11.5px] leading-[1.35] text-[var(--copy)]">
              {{ $t('landing.how.draft.carousel.slide2') }}
            </p>
          </article>
          <article class="flex aspect-[4/5] min-w-0 flex-col rounded-[13px] border border-[var(--b-line)] bg-[var(--b-surface)] p-3">
            <span class="b-mono text-[var(--b-stone)]">03 / 06</span>
            <p class="mt-auto text-[11.5px] leading-[1.35] text-[var(--copy)]">
              {{ $t('landing.how.draft.carousel.slide3') }}
            </p>
          </article>
        </div>
        <div class="mt-3 flex justify-center gap-1.5" aria-hidden="true">
          <span class="h-1.5 w-4 rounded-full bg-[var(--b-black)]" />
          <span v-for="dot in 5" :key="dot" class="h-1.5 w-1.5 rounded-full bg-[var(--b-line)]" />
        </div>
      </div>

      <!-- The real Reel draft is an editable timeline, not a decorative video
           frame. These are the same beats the product generates. -->
      <div v-else class="w-full overflow-hidden rounded-[15px] border border-[var(--b-line)] bg-[var(--b-surface)]">
        <div class="flex items-center justify-between border-b border-[var(--b-line-soft)] px-4 py-3">
          <span class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.draft.reel.label') }}</span>
          <span class="inline-flex items-center gap-1 text-[10.5px] tabular-nums text-[var(--b-stone)]">
            <AppIcon name="clock" :size="12" />
            {{ $t('landing.how.draft.reel.runtime') }}
          </span>
        </div>
        <div class="grid grid-cols-[58px_1fr] border-b border-[var(--b-line-soft)] px-4 py-3">
          <span class="font-mono text-[9.5px] tabular-nums text-[var(--b-stone)]">0:00 · 0:03</span>
          <div>
            <span class="b-mono text-[var(--b-red-600)]">{{ $t('remix.hook') }}</span>
            <p class="mt-1 font-display text-[16px] leading-tight">{{ $t('landing.how.draft.beats.hook.value') }}</p>
          </div>
        </div>
        <div class="grid grid-cols-[58px_1fr] border-b border-[var(--b-line-soft)] px-4 py-3">
          <span class="font-mono text-[9.5px] tabular-nums text-[var(--b-stone)]">0:03 · 0:42</span>
          <div>
            <span class="b-mono text-[var(--b-stone)]">{{ $t('remix.body') }}</span>
            <p class="mt-1 line-clamp-2 text-[11.5px] leading-[1.45] text-[var(--copy)]">{{ $t('landing.how.draft.reel.script') }}</p>
          </div>
        </div>
        <div class="grid grid-cols-[58px_1fr] border-b border-[var(--b-line-soft)] px-4 py-3">
          <span class="text-[var(--b-stone)]"><AppIcon name="eye" :size="13" /></span>
          <div>
            <span class="b-mono text-[var(--b-stone)]">{{ $t('remix.onScreen') }}</span>
            <p class="mt-1 text-[11.5px] leading-[1.4] text-[var(--copy)]">{{ $t('landing.how.draft.reel.visual') }}</p>
          </div>
        </div>
        <div class="grid grid-cols-[58px_1fr] px-4 py-3">
          <span class="font-mono text-[9.5px] tabular-nums text-[var(--b-stone)]">0:42 · 0:47</span>
          <div>
            <span class="b-mono text-[var(--b-stone)]">{{ $t('remix.cta') }}</span>
            <p class="mt-1 text-[11.5px] leading-[1.4] text-[var(--copy)]">{{ $t('landing.how.draft.reel.cta') }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-6 flex justify-center">
      <LandingMockAction class="b-btn-red inline-flex h-10 items-center gap-2 rounded-full px-5 text-[13px] font-medium">
        {{ $t('remix.markReady') }}
        <AppIcon name="arrow" :size="14" />
      </LandingMockAction>
    </div>
  </LandingMockStage>
</template>
