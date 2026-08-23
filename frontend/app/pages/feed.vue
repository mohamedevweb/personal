<script setup lang="ts">
import type { ContentPost } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()
const { begin: beginRemix, attach: attachRemix, clear: clearRemix } = useRemixLaunch()
const loading = ref(true)
const refreshing = ref(false)
const data = ref<{ items: ContentPost[] } | null>(null)
let requestId = 0

function feedPath(excludeIds: number[] = []) {
  if (excludeIds.length === 0) return '/api/feed'

  const query = excludeIds.map(id => `exclude[]=${encodeURIComponent(id)}`).join('&')
  return `/api/feed?${query}`
}

async function loadFeed(showError = true, excludeIds: number[] = []): Promise<boolean> {
  const currentRequest = ++requestId

  try {
    const response = await apiFetch<{ items: ContentPost[] }>(feedPath(excludeIds))
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
  const visibleIds = data.value?.items.map(post => post.id) ?? []

  try {
    const response = await apiFetch<{ items: ContentPost[] }>(feedPath(visibleIds))

    if (response.items.length > 0) {
      data.value = response
      toast.success(t('feed.refreshed'))
    } else {
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
    toast.success(t(response.saved ? 'feed.saved' : 'feed.unsaved'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('feed.saveError')))
  }
}

async function remix(post: ContentPost) {
  beginRemix({ format: 'carousel', sourceHook: post.hook, moment: null })
  try {
    const response = await apiFetch<{ remix: { id: number } }>(`/api/content/${post.id}/remix`, {
      method: 'POST', body: { format: 'carousel' }
    })
    attachRemix(response.remix.id)
    await navigateTo(`/remix/${response.remix.id}`)
  } catch (exception: unknown) {
    clearRemix()
    toast.error(apiErrorMessage(exception, t('feed.remixError')))
  }
}

onMounted(loadFeed)
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <div class="flex items-center justify-end border-b border-[var(--line)] pb-4">
      <button
        class="inline-flex h-9 items-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-3 text-[12.5px] text-[var(--muted)] transition hover:text-[var(--ink)] disabled:opacity-60 sm:px-4"
        :aria-label="refreshing ? $t('feed.refreshing') : $t('feed.refresh')"
        :disabled="refreshing || loading"
        @click="refresh"
      >
        <AppIcon name="sparkles" :size="14" />
        <span class="hidden sm:inline">{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}</span>
      </button>
    </div>

    <div v-if="loading" class="mt-7 grid gap-5 sm:grid-cols-2 xl:grid-cols-3"><div v-for="i in 6" :key="i" class="h-[380px] animate-pulse rounded-[18px] bg-[var(--sand-soft)]" /></div>
    <div v-else-if="data && data.items.length === 0" class="mt-7 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <h3 class="font-serif text-2xl tracking-[-.02em]">{{ $t('feed.emptyTitle') }}</h3>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('feed.emptyBody') }}</p>
      <button class="mt-6 inline-flex h-11 items-center justify-center gap-2 rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:cursor-wait disabled:opacity-60" :disabled="refreshing" @click="refresh">
        <AppIcon name="sparkles" :size="16" />{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}
      </button>
    </div>
    <div v-else class="mt-7 grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <ContentCard v-for="post in data?.items" :key="post.id" :post="post" @save="save" @remix="remix" />
    </div>
  </main>
</template>
