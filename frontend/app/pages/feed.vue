<script setup lang="ts">
import type { ContentPost, FeedResponse } from '~/types/product'
import { compactNumber } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const refreshing = ref(false)
const data = ref<FeedResponse | null>(null)
const seenIds = new Set<number>()
let requestId = 0

const featuredPost = computed(() => data.value?.items[0] || null)
const remainingPosts = computed(() => data.value?.items.slice(1) || [])

function feedPath(excludeIds: number[] = []) {
  if (excludeIds.length === 0) return '/api/feed'
  const query = excludeIds.map(id => `exclude[]=${encodeURIComponent(id)}`).join('&')
  return `/api/feed?${query}`
}

async function loadFeed(showError = true, excludeIds: number[] = []): Promise<boolean> {
  const currentRequest = ++requestId
  try {
    const response = await apiFetch<FeedResponse>(feedPath(excludeIds))
    if (currentRequest === requestId) data.value = response
    return true
  } catch (exception: unknown) {
    if (showError) toast.error(apiErrorMessage(exception, t('feed.loadError')))
    return false
  } finally {
    if (currentRequest === requestId) loading.value = false
  }
}

async function refresh() {
  if (refreshing.value) return
  refreshing.value = true
  data.value?.items.forEach(post => seenIds.add(post.id))
  try {
    const response = await apiFetch<FeedResponse>(feedPath([...seenIds]))
    if (response.items.length > 0) {
      data.value = response
    } else {
      seenIds.clear()
      toast.success(t('feed.rotationComplete'))
    }
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('feed.loadError')))
  } finally {
    refreshing.value = false
  }
}

async function save(post: ContentPost) {
  try {
    const response = await apiFetch<{ saved: boolean }>(`/api/content/${post.id}/save`, { method: 'POST' })
    post.is_saved = response.saved
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('feed.saveError')))
  }
}

function openRemix(post: ContentPost) {
  return navigateTo(`/content/${post.id}`)
}

onMounted(loadFeed)
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <div v-if="loading" class="space-y-5">
      <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"><div v-for="i in 6" :key="i" class="h-[380px] animate-pulse rounded-[20px] bg-[var(--sand-soft)]" /></div>
    </div>

    <section v-else-if="featuredPost" class="overflow-hidden rounded-[20px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)]">
      <div class="grid lg:grid-cols-[.82fr_1.18fr]">
        <NuxtLink :to="`/content/${featuredPost.id}`" class="order-2 relative min-h-[240px] overflow-hidden bg-[var(--sand)] lg:order-none lg:min-h-[360px]">
          <img v-if="featuredPost.thumbnail_url" :src="featuredPost.thumbnail_url" :alt="featuredPost.hook" class="absolute inset-0 h-full w-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/5 to-transparent" />
          <span class="absolute left-4 top-4 rounded-full bg-black/45 px-3 py-1.5 text-[11px] font-medium text-white backdrop-blur">{{ featuredPost.format }}</span>
          <p class="absolute inset-x-5 bottom-5 text-[22px] font-medium leading-[1.15] tracking-[-.025em] text-white md:text-[27px]">{{ featuredPost.hook }}</p>
        </NuxtLink>

        <div class="order-1 flex flex-col p-5 md:p-7 lg:order-none">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--accent)]">{{ $t('feed.featured.eyebrow') }}</p>
            <span class="rounded-full bg-[var(--accent-soft)] px-3 py-1.5 text-[11.5px] font-medium text-[var(--accent-ink)]">{{ $t('feed.featured.outlier', { ratio: featuredPost.performance_ratio.toFixed(1) }) }}</span>
          </div>
          <h3 class="mt-5 font-serif text-[30px] leading-[1.05] tracking-[-.03em] md:text-[36px]">{{ $t('feed.featured.title') }}</h3>
          <p class="mt-3 text-[14px] leading-6 text-[var(--muted)]">{{ $t('feed.featured.reason', { creator: featuredPost.creator.username, ratio: featuredPost.performance_ratio.toFixed(1) }) }}</p>

          <div class="mt-auto grid grid-cols-2 gap-2 pt-5">
            <NuxtLink :to="{ path: `/content/${featuredPost.id}`, query: { format: 'carousel' } }" class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-4 text-[13px] font-medium text-white transition hover:bg-black">
              <AppIcon name="carousel" :size="16" />
              <span class="sm:hidden">{{ $t('feed.featured.carouselShort') }}</span><span class="hidden sm:inline">{{ $t('feed.featured.carousel') }}</span>
            </NuxtLink>
            <NuxtLink :to="{ path: `/content/${featuredPost.id}`, query: { format: 'reel' } }" class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-4 text-[13px] font-medium transition hover:bg-[var(--paper)]">
              <AppIcon name="reel" :size="16" />
              <span class="sm:hidden">{{ $t('feed.featured.reelShort') }}</span><span class="hidden sm:inline">{{ $t('feed.featured.reel') }}</span>
            </NuxtLink>
          </div>
          <p class="mt-3 text-center text-[11.5px] text-[var(--faint)]">{{ $t('feed.featured.voiceNote') }}</p>

          <div class="mt-4 flex items-center gap-3 rounded-[15px] border border-[var(--line)] bg-[var(--paper)] p-3.5">
            <img v-if="featuredPost.creator.avatar_url" :src="featuredPost.creator.avatar_url" alt="" class="h-10 w-10 rounded-full object-cover">
            <span v-else class="grid h-10 w-10 place-items-center rounded-full bg-[var(--line-soft)] text-sm font-semibold">{{ featuredPost.creator.username.slice(0, 2).toUpperCase() }}</span>
            <span class="min-w-0 flex-1"><span class="block truncate text-[13px] font-medium">@{{ featuredPost.creator.username }}</span><span class="mt-0.5 block text-[11.5px] text-[var(--faint)]">{{ $t('feed.featured.proof', { views: compactNumber(featuredPost.views) }) }}</span></span>
            <NuxtLink :to="`/content/${featuredPost.id}`" class="text-[12px] text-[var(--muted)] transition hover:text-[var(--ink)]">{{ $t('feed.featured.analyze') }}</NuxtLink>
          </div>
        </div>
      </div>
    </section>

    <section v-else-if="data" class="mt-7 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <h3 class="font-serif text-2xl tracking-[-.02em]">{{ $t('feed.emptyTitle') }}</h3>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('feed.emptyBody') }}</p>
      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <NuxtLink to="/personal" class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-5 text-[14px] font-medium transition hover:bg-[var(--paper)]">{{ $t('feed.adjustContext') }}</NuxtLink>
        <button class="inline-flex h-11 items-center justify-center gap-2 rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:cursor-wait disabled:opacity-60" :disabled="refreshing" @click="refresh"><AppIcon name="sparkles" :size="16" />{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}</button>
      </div>
    </section>

    <section v-if="remainingPosts.length" class="mt-9">
      <div class="flex items-end justify-between gap-4 border-b border-[var(--line)] pb-4">
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t('feed.moreEyebrow') }}</p>
          <h2 class="mt-2 font-serif text-[28px] tracking-[-.025em]">{{ $t('feed.moreTitle') }}</h2>
        </div>
        <button class="inline-flex h-9 items-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-3 text-[12.5px] text-[var(--muted)] transition hover:text-[var(--ink)] disabled:opacity-60 sm:px-4" :aria-label="refreshing ? $t('feed.refreshing') : $t('feed.refresh')" :disabled="refreshing" @click="refresh">
          <AppIcon name="sparkles" :size="14" />
          <span class="hidden sm:inline">{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}</span>
        </button>
      </div>
      <div class="mt-5 grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <ContentCard v-for="post in remainingPosts" :key="post.id" :post="post" @save="save" @remix="openRemix" />
      </div>
    </section>
  </main>
</template>
