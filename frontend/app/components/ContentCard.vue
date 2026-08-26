<script setup lang="ts">
import type { ContentPost } from '~/types/product'
import { compactNumber, creatorProfileUrl, relativeDate } from '~/types/product'

const props = withDefaults(defineProps<{ post: ContentPost, remixing?: boolean }>(), {
  remixing: false
})
defineEmits<{ save: [post: ContentPost], remix: [post: ContentPost] }>()
const { locale } = useI18n()

const activeMediaIndex = ref(0)
const mediaKind = computed(() => {
  const format = (props.post.format || '').toLowerCase()
  if (format.includes('reel') || format.includes('video')) return 'reel'
  if (format.includes('carousel')) return 'carousel'
  return 'image'
})
const mediaUrls = computed(() => {
  const urls = props.post.media_urls?.filter(Boolean) || []
  return urls.length > 0 ? urls : props.post.thumbnail_url ? [props.post.thumbnail_url] : []
})
const activeMediaUrl = computed(() => mediaUrls.value[activeMediaIndex.value] || null)
const hasPreviousMedia = computed(() => activeMediaIndex.value > 0)
const hasNextMedia = computed(() => activeMediaIndex.value < mediaUrls.value.length - 1)
// Instagram cuts the caption after a couple of lines and reveals the rest behind
// an inline "more", so we truncate on length to keep that link on the line. Here
// the link opens the post's analysis rather than unfolding the caption in place.
const caption = computed(() => [props.post.hook, props.post.caption].filter(Boolean).join(' '))
const isLongCaption = computed(() => caption.value.length > 80)
const visibleCaption = computed(() => (isLongCaption.value ? `${caption.value.slice(0, 80).trimEnd()}… ` : caption.value))
const engagement = computed(() => (props.post.likes || 0) + (props.post.comments || 0) + (props.post.shares || 0))

let touchStart: { x: number, y: number } | null = null

function showPreviousMedia() {
  if (hasPreviousMedia.value) activeMediaIndex.value--
}

function showNextMedia() {
  if (hasNextMedia.value) activeMediaIndex.value++
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
  if (horizontalDistance > 0) showPreviousMedia()
  else showNextMedia()
}

watch(() => props.post.id, () => {
  activeMediaIndex.value = 0
})
</script>

<template>
  <article class="group flex h-full flex-col overflow-hidden rounded-[20px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)] transition duration-300 hover:shadow-[0_12px_34px_rgba(23,23,26,.08)]">
    <!-- The post is shown the way it looks on Instagram: same header, square
         media, action bar, like count and caption ordering. -->
    <header class="flex items-center gap-2.5 px-3 py-2.5">
      <!-- Avatar and handle both open the creator's account, the way they do on
           Instagram itself. -->
      <a
        :href="creatorProfileUrl(post.creator.username)"
        target="_blank"
        rel="noopener noreferrer"
        class="group/creator flex min-w-0 flex-1 items-center gap-2.5"
        :title="$t('contentCard.openProfile', { username: post.creator.username })"
      >
        <span class="rounded-full bg-gradient-to-tr from-[#f9ce34] via-[#ee2a7b] to-[#6228d7] p-[2px]">
          <img :src="post.creator.avatar_url || ''" alt="" class="block h-7 w-7 rounded-full border-2 border-[var(--surface)] bg-[var(--sand)] object-cover">
        </span>
        <span class="min-w-0 flex-1 leading-tight">
          <span class="block truncate text-[12px] font-semibold">
            <span class="group-hover/creator:underline">{{ post.creator.username }}</span><span class="font-normal text-[var(--faint)]"> · {{ relativeDate(post.published_at, locale) }}</span>
          </span>
          <span class="block truncate text-[11px] text-[var(--faint)]">{{ $t('contentCard.followers', { count: compactNumber(post.creator.followers) }) }}</span>
        </span>
      </a>
      <AppIcon name="dots" :size="16" class="shrink-0 text-[var(--muted)]" />
    </header>

    <div
      class="relative aspect-[4/3] overflow-hidden bg-[var(--sand)]"
      @touchstart.passive="rememberTouch"
      @touchend.passive="navigateFromSwipe"
    >
      <NuxtLink :to="`/content/${post.id}`" class="block h-full w-full">
        <img v-if="activeMediaUrl" :src="activeMediaUrl" :alt="post.hook" class="h-full w-full object-cover">
      </NuxtLink>
      <AppIcon v-if="mediaKind !== 'image'" :name="mediaKind" :size="22" :stroke-width="1.9" class="pointer-events-none absolute right-3 top-3 text-white drop-shadow-[0_1px_3px_rgba(0,0,0,.55)]" />

      <button
        v-if="hasPreviousMedia"
        type="button"
        class="absolute left-3 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        :aria-label="$t('contentCard.previousSlide')"
        @click="showPreviousMedia"
      >
        <AppIcon name="chevron" :size="18" class="rotate-180" />
      </button>
      <button
        v-if="hasNextMedia"
        type="button"
        class="absolute right-3 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        :aria-label="$t('contentCard.nextSlide')"
        @click="showNextMedia"
      >
        <AppIcon name="chevron" :size="18" />
      </button>

      <span v-if="mediaUrls.length > 1" class="absolute bottom-2 left-1/2 flex -translate-x-1/2">
        <button
          v-for="(_, index) in mediaUrls"
          :key="index"
          type="button"
          class="inline-flex h-4 w-4 items-center justify-center rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
          :aria-label="$t('contentCard.goToSlide', { slide: index + 1 })"
          :aria-current="index === activeMediaIndex ? 'true' : undefined"
          @click="activeMediaIndex = index"
        >
          <i class="h-[5px] w-[5px] rounded-full shadow-[0_1px_2px_rgba(0,0,0,.25)]" :class="index === activeMediaIndex ? 'bg-white' : 'bg-white/45'" />
        </button>
      </span>
    </div>

    <div class="flex items-center gap-3.5 px-3 pb-1 pt-2.5 text-[var(--ink)]">
      <AppIcon name="heart" :size="21" :stroke-width="1.6" />
      <AppIcon name="chat" :size="21" :stroke-width="1.6" class="-scale-x-100" />
      <AppIcon name="paper-plane" :size="21" :stroke-width="1.6" />
      <a
        v-if="post.source_url"
        :href="post.source_url"
        target="_blank"
        rel="noopener noreferrer"
        class="transition hover:opacity-60"
        :aria-label="$t('contentCard.openSource')"
      >
        <AppIcon name="arrow" :size="20" class="-rotate-45" />
      </a>
      <button
        class="ml-auto transition hover:opacity-60"
        :aria-label="post.is_saved ? $t('contentCard.saved') : $t('contentCard.save')"
        @click="$emit('save', post)"
      >
        <AppIcon name="bookmark" :size="21" :stroke-width="1.6" :filled="post.is_saved" />
      </button>
    </div>

    <div class="min-h-[82px] px-3 pb-2.5 text-[12px] leading-[17px]">
      <p class="font-semibold">{{ $t('contentCard.likes', { count: compactNumber(post.likes) }) }}</p>
      <p class="mt-1">
        <span class="font-semibold">{{ post.creator.username }}</span>
        {{ ' ' }}{{ visibleCaption }}<NuxtLink v-if="isLongCaption" :to="`/content/${post.id}`" class="text-[var(--faint)] transition hover:text-[var(--ink)]">{{ $t('contentCard.more') }}</NuxtLink>
      </p>
      <p v-if="post.comments" class="mt-1 text-[var(--faint)]">{{ $t('contentCard.viewComments', { count: compactNumber(post.comments) }) }}</p>
    </div>

    <!-- Everything Personal adds on top of the post lives below the fold line. -->
    <div class="flex flex-1 flex-col border-t border-[var(--line)] bg-[var(--paper)] px-2.5 py-3.5">
      <!-- The badge sits outside the scrolling row: its explanation popover would
           be clipped by the horizontal overflow otherwise. -->
      <div class="flex flex-nowrap items-center gap-1">
        <PerformanceBadge :post="post" />
        <div class="flex flex-nowrap items-center gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          <span v-if="(post.creator_fit_score ?? 0) >= 55" class="shrink-0 whitespace-nowrap rounded-full border border-[var(--accent-line)] bg-[var(--accent-soft)] px-2.5 py-1.5 text-[12px] text-[var(--accent-ink)]">
            {{ $t('contentCard.creatorFit') }}
          </span>
          <span class="shrink-0 whitespace-nowrap rounded-full border border-[var(--line)] px-2.5 py-1.5 text-[12px] text-[var(--muted)]">
            {{ mediaKind === 'reel' && post.views > 0 ? $t('contentCard.views', { count: compactNumber(post.views) }) : $t('contentCard.engagements', { count: compactNumber(engagement) }) }}
          </span>
          <span v-for="signal in post.signals?.slice(-1)" :key="signal" class="hidden shrink-0 whitespace-nowrap rounded-full border border-[var(--line)] px-2.5 py-1.5 text-[12px] text-[var(--muted)] min-[360px]:inline">{{ signal }}</span>
        </div>
      </div>

      <div class="mt-auto grid gap-2 pt-3">
        <button
          type="button"
          class="inline-flex h-9 min-w-0 items-center justify-center gap-1.5 rounded-full border border-[var(--line)] bg-[var(--surface)] px-3 text-[12px] transition hover:bg-[var(--line-soft)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]"
          @click="$emit('save', post)"
        >
          <AppIcon name="bookmark" :size="14" :filled="post.is_saved" />
          <span class="truncate">{{ post.is_saved ? $t('contentCard.saved') : $t('contentCard.save') }}</span>
        </button>
        <button
          type="button"
          class="inline-flex h-10 min-w-0 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-4 text-[12.5px] font-medium text-[var(--paper)] transition hover:bg-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]"
          :disabled="remixing"
          :aria-busy="remixing"
          @click="$emit('remix', post)"
        >
          {{ $t('contentCard.remixForMe') }}
          <AppIcon name="arrow" :size="14" />
        </button>
      </div>
    </div>
  </article>
</template>
