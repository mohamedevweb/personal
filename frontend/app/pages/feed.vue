<script setup lang="ts">
import type { ContentPost, FeedResponse } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const refreshing = ref(false)
const data = ref<FeedResponse | null>(null)
const seenIds = new Set<number>()
let requestId = 0

const posts = computed(() => data.value?.items || [])

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

    <section v-else-if="posts.length" class="mt-2">
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
        <ContentCard v-for="post in posts" :key="post.id" :post="post" @save="save" @remix="openRemix" />
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
  </main>
</template>
