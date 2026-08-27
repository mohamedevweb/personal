<script setup lang="ts">
// The slides of a carousel, with the ways Instagram lets you move between them:
// the arrows on a pointer, a swipe on a touch screen, the dots anywhere. Shared
// so the feed card and the analysis page behave the same on the same post.
const props = withDefaults(defineProps<{
  urls: string[]
  alt?: string
  /** Where tapping the media leads, when it leads anywhere. */
  to?: string
  fit?: 'cover' | 'contain'
}>(), { alt: '', to: undefined, fit: 'cover' })

const NuxtLink = resolveComponent('NuxtLink')

const activeIndex = ref(0)
const activeUrl = computed(() => props.urls[activeIndex.value] || null)
// Instagram signs its image links with an expiry, so a frame can go missing long
// after the post was found. Remembering which ones the browser could not load
// keeps the media showing its own frame instead of a broken picture.
const failedUrls = ref<string[]>([])
const isUnavailable = computed(() => !!activeUrl.value && failedUrls.value.includes(activeUrl.value))
const hasPrevious = computed(() => activeIndex.value > 0)
const hasNext = computed(() => activeIndex.value < props.urls.length - 1)
const frame = computed(() => (props.to ? NuxtLink : 'div'))

let touchStart: { x: number, y: number } | null = null

function showPrevious() {
  if (hasPrevious.value) activeIndex.value--
}

function showNext() {
  if (hasNext.value) activeIndex.value++
}

function rememberFailed(url: string | null) {
  if (url && !failedUrls.value.includes(url)) failedUrls.value.push(url)
}

function rememberTouch(event: TouchEvent) {
  const touch = event.touches[0]
  if (touch) touchStart = { x: touch.clientX, y: touch.clientY }
}

function navigateFromSwipe(event: TouchEvent) {
  const touch = event.changedTouches[0]
  if (!touch || !touchStart) return

  const horizontalDistance = touch.clientX - touchStart.x
  const verticalDistance = touch.clientY - touchStart.y
  touchStart = null

  if (Math.abs(horizontalDistance) < 40 || Math.abs(horizontalDistance) <= Math.abs(verticalDistance)) return
  if (horizontalDistance > 0) showPrevious()
  else showNext()
}

watch(() => props.urls, () => {
  activeIndex.value = 0
  failedUrls.value = []
})
</script>

<template>
  <div
    class="absolute inset-0"
    @touchstart.passive="rememberTouch"
    @touchend.passive="navigateFromSwipe"
  >
    <component :is="frame" v-bind="to ? { to } : {}" class="block h-full w-full">
      <img
        v-if="activeUrl && !isUnavailable"
        :src="activeUrl"
        :alt="alt"
        class="h-full w-full"
        :class="fit === 'contain' ? 'object-contain' : 'object-cover'"
        @error="rememberFailed(activeUrl)"
      >
      <span v-else-if="isUnavailable" class="flex h-full w-full items-center justify-center px-6 text-center text-[12px] text-[var(--faint)]">
        {{ $t('contentCard.mediaUnavailable') }}
      </span>
    </component>

    <!-- Anything the caller draws over the frame — the format glyph, the hook —
         sits between the media and the controls, so the controls stay clickable. -->
    <slot />

    <button
      v-if="hasPrevious"
      type="button"
      class="absolute left-3 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
      :aria-label="$t('contentCard.previousSlide')"
      @click="showPrevious"
    >
      <AppIcon name="chevron" :size="18" class="rotate-180" />
    </button>
    <button
      v-if="hasNext"
      type="button"
      class="absolute right-3 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
      :aria-label="$t('contentCard.nextSlide')"
      @click="showNext"
    >
      <AppIcon name="chevron" :size="18" />
    </button>

    <span v-if="urls.length > 1" class="absolute bottom-2 left-1/2 flex -translate-x-1/2">
      <button
        v-for="(_, index) in urls"
        :key="index"
        type="button"
        class="inline-flex h-4 w-4 items-center justify-center rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
        :aria-label="$t('contentCard.goToSlide', { slide: index + 1 })"
        :aria-current="index === activeIndex ? 'true' : undefined"
        @click="activeIndex = index"
      >
        <i class="h-[5px] w-[5px] rounded-full shadow-[0_1px_2px_rgba(0,0,0,.25)]" :class="index === activeIndex ? 'bg-white' : 'bg-white/45'" />
      </button>
    </span>
  </div>
</template>
