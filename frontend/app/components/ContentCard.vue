<script setup lang="ts">
import type { ContentPost } from '~/types/product'
import { compactNumber, relativeDate } from '~/types/product'

const props = defineProps<{ post: ContentPost }>()
defineEmits<{ save: [post: ContentPost], dismiss: [post: ContentPost], remix: [post: ContentPost] }>()

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
const isLongCaption = computed(() => caption.value.length > 125)
const visibleCaption = computed(() => (expanded.value || !isLongCaption.value ? caption.value : `${caption.value.slice(0, 125).trimEnd()}… `))
</script>

<template>
  <article class="group overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)] transition duration-300 hover:shadow-[0_12px_34px_rgba(23,23,26,.08)]">
    <!-- The post is shown the way it looks on Instagram: same header, square
         media, action bar, like count and caption ordering. -->
    <header class="flex items-center gap-3 px-3.5 py-3">
      <span class="rounded-full bg-gradient-to-tr from-[#f9ce34] via-[#ee2a7b] to-[#6228d7] p-[2px]">
        <img :src="post.creator.avatar_url || ''" alt="" class="block h-8 w-8 rounded-full border-2 border-[var(--surface)] bg-[var(--sand)] object-cover">
      </span>
      <div class="min-w-0 flex-1 leading-tight">
        <p class="truncate text-[13px] font-semibold">
          {{ post.creator.username }}<span class="font-normal text-[var(--faint)]"> · {{ relativeDate(post.published_at) }}</span>
        </p>
        <p class="truncate text-[12px] text-[var(--faint)]">{{ $t('contentCard.followers', { count: compactNumber(post.creator.followers) }) }}</p>
      </div>
      <AppIcon name="dots" :size="18" class="shrink-0 text-[var(--muted)]" />
    </header>

    <NuxtLink :to="`/content/${post.id}`" class="relative block aspect-square overflow-hidden bg-[var(--sand)]">
      <img v-if="post.thumbnail_url" :src="post.thumbnail_url" :alt="post.hook" class="h-full w-full object-cover">
      <AppIcon v-if="mediaKind !== 'image'" :name="mediaKind" :size="22" :stroke-width="1.9" class="absolute right-3 top-3 text-white drop-shadow-[0_1px_3px_rgba(0,0,0,.55)]" />
      <span v-if="mediaKind === 'carousel'" class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
        <i v-for="dot in 5" :key="dot" class="h-[5px] w-[5px] rounded-full" :class="dot === 1 ? 'bg-white' : 'bg-white/45'" />
      </span>
    </NuxtLink>

    <div class="flex items-center gap-4 px-3.5 pb-1 pt-3 text-[var(--ink)]">
      <AppIcon name="heart" :size="24" :stroke-width="1.6" />
      <AppIcon name="chat" :size="24" :stroke-width="1.6" class="-scale-x-100" />
      <AppIcon name="paper-plane" :size="24" :stroke-width="1.6" />
      <button
        class="ml-auto transition hover:opacity-60"
        :aria-label="post.is_saved ? $t('contentCard.saved') : $t('contentCard.save')"
        @click="$emit('save', post)"
      >
        <AppIcon name="bookmark" :size="24" :stroke-width="1.6" :filled="post.is_saved" />
      </button>
    </div>

    <div class="px-3.5 pb-4 text-[13px] leading-[18px]">
      <p class="font-semibold">{{ $t('contentCard.likes', { count: compactNumber(post.likes) }) }}</p>
      <p class="mt-1">
        <span class="font-semibold">{{ post.creator.username }}</span>
        {{ ' ' }}{{ visibleCaption }}<button v-if="isLongCaption && !expanded" class="text-[var(--faint)] transition hover:text-[var(--ink)]" @click="expanded = true">{{ $t('contentCard.more') }}</button>
      </p>
      <p v-if="post.comments" class="mt-1 text-[var(--faint)]">{{ $t('contentCard.viewComments', { count: compactNumber(post.comments) }) }}</p>
    </div>

    <!-- Everything Personal adds on top of the post lives below the fold line. -->
    <div class="border-t border-[var(--line)] bg-[var(--paper)] p-4 md:p-5">
      <div class="flex flex-wrap items-center gap-1.5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-soft)] px-2.5 py-1 text-[11px] font-semibold text-[var(--accent-ink)]">
          <AppIcon name="trend" :size="13" />{{ $t('contentCard.average', { ratio: post.performance_ratio.toFixed(1) }) }}
        </span>
        <span class="rounded-full border border-[var(--line)] px-2.5 py-1 text-[11px] text-[var(--muted)]">{{ $t('contentCard.views', { count: compactNumber(post.views) }) }}</span>
        <span v-for="signal in post.signals?.slice(0, 2)" :key="signal" class="rounded-full border border-[var(--line)] px-2.5 py-1 text-[10px] text-[var(--muted)]">{{ signal }}</span>
      </div>

      <div class="mt-4 rounded-[14px] bg-[var(--surface)] p-4">
        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('contentCard.whyRecommends') }}</p>
        <p class="mt-1.5 text-[13px] leading-5 text-[var(--copy)]">{{ post.why_recommended || post.why_it_works }}</p>
      </div>

      <div class="mt-5 flex items-center gap-2">
        <button class="inline-flex h-9 items-center rounded-full border border-[var(--line)] bg-[var(--surface)] px-4 text-[12.5px] transition hover:bg-[var(--line-soft)]" @click="$emit('save', post)">{{ post.is_saved ? $t('contentCard.saved') : $t('contentCard.save') }}</button>
        <button class="rounded-full px-3 py-2 text-xs text-[var(--faint)] transition hover:text-[var(--ink)]" @click="$emit('dismiss', post)">{{ $t('contentCard.notForMe') }}</button>
        <button class="ml-auto inline-flex h-9 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-4 text-[12.5px] font-medium text-[var(--paper)] transition hover:bg-black" @click="$emit('remix', post)">{{ $t('contentCard.remixForMe') }} <AppIcon name="arrow" :size="14" /></button>
      </div>
    </div>
  </article>
</template>
