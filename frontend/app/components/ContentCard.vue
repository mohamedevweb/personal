<script setup lang="ts">
import type { ContentPost } from '~/types/product'
import { compactNumber, relativeDate } from '~/types/product'

defineProps<{ post: ContentPost }>()
defineEmits<{ save: [post: ContentPost], dismiss: [post: ContentPost], remix: [post: ContentPost] }>()
</script>

<template>
  <article class="group overflow-hidden rounded-[24px] border border-[var(--line)] bg-[#fbfaf7] transition duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_45px_rgba(50,43,32,.08)]">
    <NuxtLink :to="`/content/${post.id}`" class="relative block aspect-[4/3] overflow-hidden bg-[#ded9cf]">
      <img v-if="post.thumbnail_url" :src="post.thumbnail_url" :alt="post.hook" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.025]">
      <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent" />
      <div class="absolute left-5 right-5 top-5 flex items-center justify-between">
        <span class="rounded-full bg-black/35 px-3 py-1.5 text-[11px] font-medium text-white backdrop-blur-md">{{ post.format }}</span>
        <span class="rounded-full bg-[#f3ebe1] px-3 py-1.5 text-[12px] font-semibold text-[#8b402a]">{{ $t('contentCard.average', { ratio: post.performance_ratio.toFixed(1) }) }}</span>
      </div>
      <h2 class="absolute bottom-5 left-5 right-5 max-w-lg text-[22px] font-medium leading-[1.16] tracking-[-0.025em] text-white">{{ post.hook }}</h2>
    </NuxtLink>

    <div class="p-5 md:p-6">
      <div class="flex items-center gap-3">
        <img :src="post.creator.avatar_url || ''" alt="" class="h-9 w-9 rounded-full bg-[#e5e1d8] object-cover">
        <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">@{{ post.creator.username }}</p><p class="text-[11px] text-[#8f8b83]">{{ $t('contentCard.followers', { count: compactNumber(post.creator.followers) }) }} · {{ relativeDate(post.published_at) }}</p></div>
        <div class="text-right text-[11px] text-[#77736c]"><p>{{ $t('contentCard.views', { count: compactNumber(post.views) }) }}</p><p>{{ $t('contentCard.likes', { count: compactNumber(post.likes) }) }}</p></div>
      </div>

      <div class="mt-5 flex flex-wrap gap-1.5"><span v-for="signal in post.signals?.slice(0, 3)" :key="signal" class="rounded-full border border-[#dedad1] px-2.5 py-1 text-[10px] text-[#716e67]">{{ signal }}</span></div>
      <div class="mt-5 border-l-2 border-[#c85234]/55 pl-4"><p class="text-[10px] font-semibold uppercase tracking-[.12em] text-[#918d85]">{{ $t('contentCard.whyRecommends') }}</p><p class="mt-1.5 text-[13px] leading-5 text-[#5f5c56]">{{ post.why_recommended || post.why_it_works }}</p></div>

      <div class="mt-6 flex items-center gap-2">
        <button class="rounded-full border border-[#d9d5cc] px-3.5 py-2 text-xs transition hover:bg-[#f0ede6]" @click="$emit('save', post)">{{ post.is_saved ? $t('contentCard.saved') : $t('contentCard.save') }}</button>
        <button class="rounded-full px-3 py-2 text-xs text-[#8b877f] hover:text-[#33312d]" @click="$emit('dismiss', post)">{{ $t('contentCard.notForMe') }}</button>
        <button class="ml-auto inline-flex items-center gap-2 rounded-full bg-[#1d1d1b] px-4 py-2.5 text-xs font-medium text-white transition hover:bg-black" @click="$emit('remix', post)">{{ $t('contentCard.remixForMe') }} <AppIcon name="arrow" :size="14" /></button>
      </div>
    </div>
  </article>
</template>
