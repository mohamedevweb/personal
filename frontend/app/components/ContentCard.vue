<script setup lang="ts">
import type { ContentPost } from '~/types/product'
import { compactNumber, creatorProfileUrl, relativeDate } from '~/types/product'

const props = defineProps<{ post: ContentPost }>()
defineEmits<{ save: [post: ContentPost], remix: [post: ContentPost] }>()

const expanded = ref(false)
const mediaKind = computed(() => {
  const format = (props.post.format || '').toLowerCase()
  if (format.includes('reel') || format.includes('video')) return 'reel'
  if (format.includes('carousel')) return 'carousel'
  return 'image'
})
// Instagram cuts the caption after a couple of lines and reveals the rest behind
// an inline "more", so we truncate on length to keep that button on the line.
const caption = computed(() => [props.post.hook, props.post.caption].filter(Boolean).join(' '))
const isLongCaption = computed(() => caption.value.length > 80)
const visibleCaption = computed(() => (expanded.value || !isLongCaption.value ? caption.value : `${caption.value.slice(0, 80).trimEnd()}… `))
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
            <span class="group-hover/creator:underline">{{ post.creator.username }}</span><span class="font-normal text-[var(--faint)]"> · {{ relativeDate(post.published_at) }}</span>
          </span>
          <span class="block truncate text-[11px] text-[var(--faint)]">{{ $t('contentCard.followers', { count: compactNumber(post.creator.followers) }) }}</span>
        </span>
      </a>
      <AppIcon name="dots" :size="16" class="shrink-0 text-[var(--muted)]" />
    </header>

    <NuxtLink :to="`/content/${post.id}`" class="relative block aspect-[4/3] overflow-hidden bg-[var(--sand)]">
      <img v-if="post.thumbnail_url" :src="post.thumbnail_url" :alt="post.hook" class="h-full w-full object-cover">
      <AppIcon v-if="mediaKind !== 'image'" :name="mediaKind" :size="22" :stroke-width="1.9" class="absolute right-3 top-3 text-white drop-shadow-[0_1px_3px_rgba(0,0,0,.55)]" />
      <span v-if="mediaKind === 'carousel'" class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
        <i v-for="dot in 5" :key="dot" class="h-[5px] w-[5px] rounded-full" :class="dot === 1 ? 'bg-white' : 'bg-white/45'" />
      </span>
    </NuxtLink>

    <div class="flex items-center gap-3.5 px-3 pb-1 pt-2.5 text-[var(--ink)]">
      <AppIcon name="heart" :size="21" :stroke-width="1.6" />
      <AppIcon name="chat" :size="21" :stroke-width="1.6" class="-scale-x-100" />
      <AppIcon name="paper-plane" :size="21" :stroke-width="1.6" />
      <button
        class="ml-auto transition hover:opacity-60"
        :aria-label="post.is_saved ? $t('contentCard.saved') : $t('contentCard.save')"
        @click="$emit('save', post)"
      >
        <AppIcon name="bookmark" :size="21" :stroke-width="1.6" :filled="post.is_saved" />
      </button>
    </div>

    <div class="px-3 pb-2.5 text-[12px] leading-[17px]">
      <p class="font-semibold">{{ $t('contentCard.likes', { count: compactNumber(post.likes) }) }}</p>
      <p class="mt-1">
        <span class="font-semibold">{{ post.creator.username }}</span>
        {{ ' ' }}{{ visibleCaption }}<button v-if="isLongCaption && !expanded" class="text-[var(--faint)] transition hover:text-[var(--ink)]" @click="expanded = true">{{ $t('contentCard.more') }}</button>
      </p>
      <p v-if="post.comments" class="mt-1 text-[var(--faint)]">{{ $t('contentCard.viewComments', { count: compactNumber(post.comments) }) }}</p>
    </div>

    <!-- Everything Personal adds on top of the post lives below the fold line. -->
    <div class="flex flex-1 flex-col border-t border-[var(--line)] bg-[var(--paper)] p-3.5">
      <div class="flex flex-wrap items-center gap-1.5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-soft)] px-2.5 py-1 text-[11px] font-semibold text-[var(--accent-ink)]">
          <AppIcon name="trend" :size="13" />{{ $t('contentCard.average', { ratio: post.performance_ratio.toFixed(1) }) }}
        </span>
        <span class="rounded-full border border-[var(--line)] px-2.5 py-1 text-[11px] text-[var(--muted)]">{{ $t('contentCard.views', { count: compactNumber(post.views) }) }}</span>
        <span v-for="signal in post.signals?.slice(0, 2)" :key="signal" class="rounded-full border border-[var(--line)] px-2.5 py-1 text-[10px] text-[var(--muted)]">{{ signal }}</span>
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
          @click="$emit('remix', post)"
        >
          {{ $t('contentCard.remixForMe') }}
          <AppIcon name="arrow" :size="14" />
        </button>
      </div>
    </div>
  </article>
</template>
