<script setup lang="ts">
import type { ContentPost, DismissReason, FeedResponse } from '~/types/product'

/**
 * The feed pages itself by exclusion: every request carries the ids already on
 * screen, and the API answers with the next best-ranked batch. A batch that adds
 * nothing new means the eligible catalogue is spent, and the scroll stops there
 * rather than looping the same posts forever.
 */
const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()
const loading = ref(true)
const loadingMore = ref(false)
const refreshing = ref(false)
const exhausted = ref(false)
const extendFailed = ref(false)
const meta = ref<FeedResponse | null>(null)
const posts = ref<ContentPost[]>([])
const explorePosts = ref<ContentPost[]>([])
const sentinel = ref<HTMLElement | null>(null)
const rotation = createFeedRotation()
let observer: IntersectionObserver | null = null
let requestId = 0

function feedPath(excludeIds: number[]) {
  if (excludeIds.length === 0) return '/api/feed'
  const query = excludeIds.map(id => `exclude[]=${encodeURIComponent(id)}`).join('&')

  return `/api/feed?${query}`
}

async function loadFeed(): Promise<void> {
  const currentRequest = ++requestId
  try {
    const response = await apiFetch<FeedResponse>('/api/feed')
    if (currentRequest !== requestId) return

    rotation.forget()
    meta.value = response
    posts.value = rotation.accept(response.items)
    explorePosts.value = response.explore_items ?? []
    exhausted.value = posts.value.length === 0
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('feed.loadError')))
  } finally {
    if (currentRequest === requestId) loading.value = false
  }
}

/**
 * A batch can leave the sentinel still on screen — a tall window, or a short
 * batch — and the observer only fires on a crossing, so the fill is re-checked
 * once the new cards have laid out.
 */
function sentinelInView(): boolean {
  const box = sentinel.value?.getBoundingClientRect()

  return box ? box.top < window.innerHeight + 400 : false
}

async function loadMore(): Promise<void> {
  if (loadingMore.value || refreshing.value || exhausted.value || extendFailed.value) return
  if (posts.value.length === 0) return

  loadingMore.value = true
  try {
    const response = await apiFetch<FeedResponse>(feedPath(rotation.exclude()))
    const fresh = rotation.accept(response.items)
    // Nothing new means there is no page left to ask for, so the scroll ends
    // here instead of requesting one the API cannot fill.
    if (fresh.length === 0) exhausted.value = true
    else posts.value = [...posts.value, ...fresh]
  } catch (exception: unknown) {
    // Stopping on failure keeps a broken request from being retried on every
    // pixel of scroll; the reader gets an explicit button instead.
    extendFailed.value = true
    toast.error(apiErrorMessage(exception, t('feed.loadError')))
  } finally {
    loadingMore.value = false
  }

  await nextTick()
  if (sentinelInView()) await loadMore()
}

function retryLoadMore() {
  extendFailed.value = false

  return loadMore()
}

async function refresh() {
  if (refreshing.value) return
  refreshing.value = true
  exhausted.value = false
  extendFailed.value = false
  try {
    const response = await apiFetch<FeedResponse>(feedPath(rotation.exclude()))
    // The rotation is deliberately kept across a refresh: it carries on from
    // where the scroll left off instead of replaying what was just passed.
    const fresh = rotation.accept(response.items)
    if (fresh.length === 0) {
      rotation.forget()
      toast.success(t('feed.rotationComplete'))
      await loadFeed()

      return
    }

    meta.value = response
    posts.value = fresh
    explorePosts.value = response.explore_items ?? []
    window.scrollTo({ top: 0, behavior: 'smooth' })
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

async function dismiss(post: ContentPost, reason: DismissReason) {
  try {
    await apiFetch(`/api/content/${post.id}/dismiss`, { method: 'POST', body: { reason } })
    posts.value = posts.value.filter(item => item.id !== post.id)
    explorePosts.value = explorePosts.value.filter(item => item.id !== post.id)
    toast.success(t('feed.dismissed'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('feed.dismissError')))
  }
}

function openRemix(post: ContentPost) {
  return navigateTo(`/content/${post.id}`)
}

watch(sentinel, (element) => {
  observer?.disconnect()
  observer = null
  if (!element || typeof IntersectionObserver === 'undefined') return

  // The margin fetches the next batch roughly a screen early, so the grid grows
  // without the reader ever reaching its bottom edge.
  observer = new IntersectionObserver((entries) => {
    if (entries.some(entry => entry.isIntersecting)) void loadMore()
  }, { rootMargin: '800px 0px' })
  observer.observe(element)
})

onUnmounted(() => observer?.disconnect())
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
        <!-- Icon-only on a phone, so it is a circle a thumb can land on rather
             than a pill squeezed to the width of its glyph. -->
        <button class="inline-flex h-11 w-11 items-center justify-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] text-[12.5px] text-[var(--muted)] transition hover:text-[var(--ink)] disabled:opacity-60 sm:h-9 sm:w-auto sm:px-4" :aria-label="refreshing ? $t('feed.refreshing') : $t('feed.refresh')" :disabled="refreshing" @click="refresh">
          <AppIcon name="sparkles" :size="14" />
          <span class="hidden sm:inline">{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}</span>
        </button>
      </div>
      <div class="mt-5 grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <ContentCard v-for="post in posts" :key="post.id" :post="post" dismissible @save="save" @dismiss="dismiss" @remix="openRemix" />
      </div>

      <div v-if="loadingMore" class="mt-5 grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
        <div v-for="i in 3" :key="i" class="h-[380px] animate-pulse rounded-[20px] bg-[var(--sand-soft)]" />
      </div>

      <!-- The skeletons above already say "loading" to anyone who can see them,
           so that half of the status is left to screen readers only. -->
      <p class="text-center text-[13px] text-[var(--muted)]" :class="{ 'mt-6': exhausted }" role="status" aria-live="polite">
        <span v-if="loadingMore" class="sr-only">{{ $t('feed.loadingMore') }}</span>
        <span v-else-if="exhausted">{{ $t('feed.rotationComplete') }}</span>
      </p>

      <div v-if="extendFailed" class="mt-2 text-center">
        <button class="inline-flex h-10 items-center justify-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-5 text-[13.5px] transition hover:bg-[var(--paper)]" @click="retryLoadMore">
          <AppIcon name="sparkles" :size="14" />{{ $t('feed.loadMore') }}
        </button>
      </div>

      <div v-if="!exhausted && !extendFailed" ref="sentinel" class="h-px w-full" aria-hidden="true" />
    </section>

    <section v-else-if="meta" class="mt-7 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <h3 class="font-serif text-2xl tracking-[-.02em]">{{ $t('feed.emptyTitle') }}</h3>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('feed.emptyBody') }}</p>
      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <NuxtLink to="/personal" class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-5 text-[14px] font-medium transition hover:bg-[var(--paper)]">{{ $t('feed.adjustContext') }}</NuxtLink>
        <button class="inline-flex h-11 items-center justify-center gap-2 rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:cursor-wait disabled:opacity-60" :disabled="refreshing" @click="refresh"><AppIcon name="sparkles" :size="16" />{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}</button>
      </div>
    </section>

    <section v-if="!loading && explorePosts.length" class="mt-12 border-t border-[var(--line)] pt-7">
      <div class="max-w-2xl">
        <h2 class="font-serif text-[28px] tracking-[-.025em]">{{ $t('feed.exploreTitle') }}</h2>
        <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $t('feed.exploreBody') }}</p>
      </div>
      <div class="mt-5 grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <ContentCard v-for="post in explorePosts" :key="post.id" :post="post" dismissible @save="save" @dismiss="dismiss" @remix="openRemix" />
      </div>
    </section>
  </main>
</template>
