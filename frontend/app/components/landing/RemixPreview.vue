<script setup lang="ts">
/**
 * The draft as it would actually land in the feed. The chrome around the media
 * is the same card for every format; only the media pane changes, so switching
 * format reads as one post being re-cut rather than three different mockups.
 *
 * There are deliberately no like or comment counts here. The panel underneath
 * promises nothing is published without you, and inventing engagement on a
 * draft would argue against that.
 */
const props = defineProps<{
  format: 'reel' | 'carousel' | 'post'
  slide: number
}>()

const { t } = useI18n()

const slides = computed(() => (['one', 'two', 'three', 'four', 'five'] as const)
  .map(key => t(`landing.remix.carousel.slides.${key}`)))

const current = computed(() => slides.value[props.slide] ?? slides.value[0]!)

/** The caption preview is truncated the way the feed truncates it. */
const captionLead = computed(() => t('landing.remix.post.body').split('\n\n')[0]!)
</script>

<template>
  <figure class="mx-auto w-full max-w-[300px]">
    <figcaption class="b-eyebrow mb-3 text-center">{{ $t('landing.remix.preview.label') }}</figcaption>

    <div class="overflow-hidden rounded-[20px] border border-[var(--b-line)] bg-[var(--b-surface)]">
      <div class="flex items-center gap-2.5 px-3.5 py-3">
        <span class="h-7 w-7 shrink-0 rounded-full bg-[#e9e4d9]" aria-hidden="true" />
        <span class="min-w-0 flex-1 truncate text-[13px] font-medium tracking-[-.01em]">
          {{ $t('landing.remix.preview.handle') }}
        </span>
        <span class="rounded-full border border-[var(--b-line)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[.14em] text-[var(--b-stone)]">
          {{ $t('landing.remix.preview.badge') }}
        </span>
      </div>

      <!-- Reel: the hook is the whole frame, the way it is on the phone. -->
      <div v-if="format === 'reel'" class="hero-night relative aspect-[9/16] px-5 py-6">
        <p class="font-display text-[25px] leading-[1.15] tracking-[-.02em] text-white">
          {{ $t('landing.remix.reel.beats.hook.value') }}
        </p>

        <div class="absolute inset-x-5 bottom-5 flex items-end justify-between gap-4">
          <p class="flex items-center gap-1.5 text-[11.5px] text-white/70">
            <AppIcon name="reel" :size="13" />
            {{ $t('landing.remix.preview.audio') }}
          </p>
          <div class="flex flex-col items-center gap-3.5 text-white/80" aria-hidden="true">
            <AppIcon name="heart" :size="19" />
            <AppIcon name="chat" :size="19" />
            <AppIcon name="paper-plane" :size="19" />
          </div>
        </div>
      </div>

      <!-- Carousel: one slide at a time, driven by the strip beside it. -->
      <div v-else-if="format === 'carousel'" class="relative flex aspect-[4/5] flex-col justify-between bg-[#f4f1ea] p-5">
        <span class="b-eyebrow self-end">{{ slide + 1 }}/{{ slides.length }}</span>
        <p class="font-display text-[22px] leading-[1.2] tracking-[-.015em]">{{ current }}</p>
        <div class="flex justify-center gap-1.5" aria-hidden="true">
          <span
            v-for="(item, index) in slides"
            :key="item"
            class="h-1.5 w-1.5 rounded-full transition-colors duration-300"
            :class="index === slide ? 'bg-[var(--b-signature)]' : 'bg-[#d6cfc1]'"
          />
        </div>
      </div>

      <!-- Post: the caption is the payload and it already sits under the card.
           Personal writes the words, not the picture, so the frame says so
           rather than repeating the caption back at twice the size. -->
      <div v-else class="grid aspect-[4/5] place-items-center bg-[#f4f1ea] p-5">
        <span class="grid h-full w-full place-items-center rounded-[10px] border border-dashed border-[#d6cfc1] text-[12.5px] text-[var(--b-stone)]">
          {{ $t('landing.remix.preview.photo') }}
        </span>
      </div>

      <div class="flex items-center gap-4 px-3.5 pt-3.5 text-[var(--b-black)]" aria-hidden="true">
        <AppIcon name="heart" :size="20" />
        <AppIcon name="chat" :size="20" />
        <AppIcon name="paper-plane" :size="20" />
        <AppIcon name="bookmark" :size="20" class="ml-auto" />
      </div>

      <p class="px-3.5 pb-4 pt-3 text-[12.5px] leading-[1.5] text-[var(--b-stone)]">
        <span class="font-medium text-[var(--b-black)]">{{ $t('landing.remix.preview.handle') }}</span>
        <span class="ml-1.5">{{ captionLead }}</span>
        <span class="ml-1">{{ $t('landing.remix.preview.more') }}</span>
      </p>
    </div>
  </figure>
</template>
