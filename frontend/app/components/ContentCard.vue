<script setup lang="ts">
import type { ContentPost } from '~/types/product'
import { compactNumber, creatorProfileUrl, relativeDate } from '~/types/product'

const props = withDefaults(defineProps<{ post: ContentPost, remixing?: boolean }>(), {
  remixing: false
})
defineEmits<{ save: [post: ContentPost], remix: [post: ContentPost] }>()
const { locale } = useI18n()

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
// Instagram cuts the caption after a couple of lines and reveals the rest behind
// an inline "more", so we truncate on length to keep that link on the line. Here
// the link opens the post's analysis rather than unfolding the caption in place.
const caption = computed(() => [props.post.hook, props.post.caption].filter(Boolean).join(' '))
const isLongCaption = computed(() => caption.value.length > 80)
const visibleCaption = computed(() => (isLongCaption.value ? `${caption.value.slice(0, 80).trimEnd()}… ` : caption.value))

</script>

<template>
  <article class="group flex h-full flex-col overflow-hidden rounded-[20px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)] transition duration-300 hover:shadow-[0_12px_34px_rgba(23,23,26,.08)]">
    <!-- The post is shown the way it looks on Instagram: same header, square
         media, action bar, like count and caption ordering. -->
    <header class="flex items-center gap-2.5 px-3 py-2">
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

    <div class="relative aspect-square overflow-hidden bg-[var(--sand)]">
      <ReelPlayer
        v-if="mediaKind === 'reel' && post.video_url"
        :src="post.video_url"
        :poster="post.thumbnail_url"
        :label="$t('contentCard.playReel', { username: post.creator.username })"
      />
      <CarouselMedia v-else :urls="mediaUrls" :alt="post.hook" :to="`/content/${post.id}`">
        <AppIcon v-if="mediaKind === 'carousel'" :name="mediaKind" :size="22" :stroke-width="1.9" class="pointer-events-none absolute right-3 top-3 text-white drop-shadow-[0_1px_3px_rgba(0,0,0,.55)]" />
      </CarouselMedia>
    </div>

    <div class="flex items-center gap-3.5 px-3 pb-0.5 pt-2 text-[var(--ink)]">
      <AppIcon name="heart" :size="20" :stroke-width="1.6" />
      <AppIcon name="chat" :size="20" :stroke-width="1.6" class="-scale-x-100" />
      <AppIcon name="paper-plane" :size="20" :stroke-width="1.6" />
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
        <AppIcon name="bookmark" :size="20" :stroke-width="1.6" :filled="post.is_saved" />
      </button>
    </div>

    <!-- Captions are the only part of a post whose length we do not control, so
         this block is held at the height of its fullest form — likes, two lines
         of caption, the comment count. Every card is then exactly as tall as
         every other, in its row and across rows. -->
    <div class="h-[80px] overflow-hidden px-3 pb-2 text-[12px] leading-[16px]">
      <p class="truncate font-semibold">{{ $t('contentCard.likes', { count: compactNumber(post.likes) }) }}</p>
      <p class="mt-1 line-clamp-2">
        <span class="font-semibold">{{ post.creator.username }}</span>
        {{ ' ' }}{{ visibleCaption }}<NuxtLink v-if="isLongCaption" :to="`/content/${post.id}`" class="text-[var(--faint)] transition hover:text-[var(--ink)]">{{ $t('contentCard.more') }}</NuxtLink>
      </p>
      <p v-if="post.comments" class="mt-1 truncate text-[var(--faint)]">{{ $t('contentCard.viewComments', { count: compactNumber(post.comments) }) }}</p>
    </div>

    <!-- Everything Personal adds on top of the post lives below the fold line. -->
    <div class="flex flex-1 flex-col justify-end border-t border-[var(--line)] bg-[var(--paper)] px-2.5 py-2.5">
      <!-- Only the outlier ratio: the raw counts were already in the post above,
           and crowding them next to the badge pushed it into a clipped scroller.
           Saving is not repeated here either — the post's own bookmark in the
           action bar above already does it, the way it does on Instagram. -->
      <div class="flex items-center">
        <PerformanceBadge :post="post" />
      </div>

      <div class="grid gap-2 pt-2">
        <button
          type="button"
          class="inline-flex h-9 min-w-0 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-4 text-[12.5px] font-medium text-[var(--paper)] transition hover:bg-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]"
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
