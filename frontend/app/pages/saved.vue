<script setup lang="ts">
import type { ContentPost, Remix } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { openWhenReady } = useRemixOpening()
const { t } = useI18n()
const toast = useToast()
const items = ref<ContentPost[]>([])
const loading = ref(true)
const remixingPostId = ref<number | null>(null)

async function unsave(post: ContentPost) {
  try {
    await apiFetch(`/api/content/${post.id}/save`, { method: 'POST' })
    items.value = items.value.filter(item => item.id !== post.id)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('saved.removeError')))
  }
}

async function remix(post: ContentPost) {
  if (remixingPostId.value !== null) return
  remixingPostId.value = post.id

  try {
    const response = await apiFetch<{ remix: Pick<Remix, 'id' | 'status'> }>(`/api/content/${post.id}/remix`, { method: 'POST', body: { format: 'carousel' } })
    if (await openWhenReady(response.remix) === 'failed') toast.error(t('feed.remixError'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('feed.remixError')))
  } finally {
    remixingPostId.value = null
  }
}

onMounted(async () => {
  try {
    const response = await apiFetch<{ items: ContentPost[] }>('/api/saved')
    items.value = response.items
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('saved.loadError')))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <div v-if="loading" class="h-96 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
    <div v-else-if="items.length" class="grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <ContentCard v-for="post in items" :key="post.id" :post="post" :remixing="remixingPostId === post.id" @save="unsave" @remix="remix" />
    </div>
    <div v-else class="rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <p class="font-serif text-[26px] tracking-[-.02em]">{{ $t('saved.emptyTitle') }}</p>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('saved.emptyCopy') }}</p>
      <NuxtLink to="/feed" class="mt-6 inline-flex h-11 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition">{{ $t('saved.exploreFeed') }}</NuxtLink>
    </div>
  </main>
</template>
