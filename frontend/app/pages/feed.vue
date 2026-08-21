<script setup lang="ts">
import type { ContentPost } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const refreshing = ref(false)
const data = ref<{ items: ContentPost[] } | null>(null)
const activeFeed = ref<'personal' | 'global'>('personal')
let requestId = 0

async function loadFeed(showError = true): Promise<boolean> {
  const currentRequest = ++requestId
  const endpoint = activeFeed.value === 'global' ? '/api/feed/global' : '/api/feed'

  try {
    const response = await apiFetch<{ items: ContentPost[] }>(endpoint)
    if (currentRequest === requestId) data.value = response
    return true
  } catch (exception: unknown) {
    if (showError) toast.error(apiErrorMessage(exception, t('feed.loadError')))
    return false
  } finally {
    if (currentRequest === requestId) loading.value = false
  }
}

async function selectFeed(feed: 'personal' | 'global') {
  if (feed === activeFeed.value) return
  activeFeed.value = feed
  data.value = null
  loading.value = true
  await loadFeed()
}

async function refresh() {
  if (refreshing.value) return
  refreshing.value = true
  try {
    if (activeFeed.value === 'global') {
      if (await loadFeed()) toast.success(t('feed.refreshed'))
      return
    }

    await apiFetch('/api/feed/refresh', { method: 'POST' })
    // Discovery scrapes in the background and a real Apify run can take up to a
    // minute, so poll rather than reload once — stop as soon as posts appear.
    for (let attempt = 0; attempt < 12; attempt++) {
      await new Promise(resolve => setTimeout(resolve, 5000))
      const loaded = await loadFeed(false)
      if (!loaded) {
        toast.error(t('feed.loadError'))
        break
      }
      if (data.value && data.value.items.length > 0) {
        toast.success(t('feed.refreshed'))
        break
      }
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
  try {
    const response = await apiFetch<{ remix: { id: number } }>(`/api/content/${post.id}/remix`, {
      method: 'POST', body: { format: 'carousel' }
    })
    await navigateTo(`/remix/${response.remix.id}`)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('feed.remixError')))
  }
}

onMounted(loadFeed)
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <div class="flex items-center justify-between gap-4 border-b border-[var(--line)] pb-4">
      <div class="flex items-center gap-1 rounded-full border border-[var(--line)] bg-[var(--surface)] p-1">
        <button
          type="button"
          class="rounded-full px-4 py-2 text-[12.5px] font-medium transition"
          :class="activeFeed === 'personal' ? 'bg-[var(--ink)] text-white' : 'text-[var(--muted)] hover:text-[var(--ink)]'"
          :aria-pressed="activeFeed === 'personal'"
          @click="selectFeed('personal')"
        >
          {{ $t('feed.forYou') }}
        </button>
        <button
          type="button"
          class="rounded-full px-4 py-2 text-[12.5px] font-medium transition"
          :class="activeFeed === 'global' ? 'bg-[var(--ink)] text-white' : 'text-[var(--muted)] hover:text-[var(--ink)]'"
          :aria-pressed="activeFeed === 'global'"
          @click="selectFeed('global')"
        >
          {{ $t('feed.global') }}
        </button>
      </div>
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

    <p class="mt-4 text-[11px] font-semibold uppercase tracking-[.18em] text-[var(--muted)]">
      {{ activeFeed === 'personal' ? $t('feed.worthCreating') : $t('feed.globalWorthCreating') }}
    </p>

    <div v-if="loading" class="mt-7 grid gap-5 sm:grid-cols-2 xl:grid-cols-3"><div v-for="i in 6" :key="i" class="h-[380px] animate-pulse rounded-[18px] bg-[var(--sand-soft)]" /></div>
    <div v-else-if="data && data.items.length === 0" class="mt-7 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <h3 class="font-serif text-2xl tracking-[-.02em]">{{ $t('feed.emptyTitle') }}</h3>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('feed.emptyBody') }}</p>
      <button class="mt-6 inline-flex h-11 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-5 text-[14px] font-medium text-[var(--paper)] transition hover:bg-black disabled:cursor-wait disabled:opacity-60" :disabled="refreshing" @click="refresh">
        <AppIcon name="sparkles" :size="16" />{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}
      </button>
    </div>
    <div v-else class="mt-7 grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <ContentCard v-for="post in data?.items" :key="post.id" :post="post" @save="save" @remix="remix" />
    </div>
  </main>
</template>
