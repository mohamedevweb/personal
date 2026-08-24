<script setup lang="ts">
import type { ContentPost } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()
const { begin: beginRemix, attach: attachRemix, clear: clearRemix } = useRemixLaunch()
const items = ref<ContentPost[]>([])
const loading = ref(true)

async function unsave(post: ContentPost) {
  try {
    await apiFetch(`/api/content/${post.id}/save`, { method: 'POST' })
    items.value = items.value.filter(item => item.id !== post.id)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('saved.removeError')))
  }
}

async function remix(post: ContentPost) {
  try {
    const response = await apiFetch<{ remix: { id: number, status: string } }>(`/api/content/${post.id}/remix`, { method: 'POST', body: { format: 'carousel' } })
    /* An existing draft comes back untouched; only a generation that actually
       starts now earns the stage. */
    if (response.remix.status === 'generating') {
      beginRemix({ format: 'carousel', sourceHook: post.hook, moment: null })
      attachRemix(response.remix.id)
    }
    await navigateTo(`/remix/${response.remix.id}`)
  } catch (exception: unknown) {
    clearRemix()
    toast.error(apiErrorMessage(exception, t('feed.remixError')))
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
    <header class="flex items-start gap-4 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[12px] bg-[var(--accent-soft)] text-[var(--accent-ink)]"><AppIcon name="bookmark" :size="19" /></span>
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t('saved.eyebrow') }}</p>
        <p class="mt-2 max-w-xl text-[15px] leading-6 text-[var(--muted)]">{{ $t('saved.subtitle') }}</p>
      </div>
    </header>

    <div v-if="loading" class="mt-5 h-96 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
    <div v-else-if="items.length" class="mt-5 grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <ContentCard v-for="post in items" :key="post.id" :post="post" @save="unsave" @remix="remix" />
    </div>
    <div v-else class="mt-5 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <p class="font-serif text-[26px] tracking-[-.02em]">{{ $t('saved.emptyTitle') }}</p>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('saved.emptyCopy') }}</p>
      <NuxtLink to="/feed" class="mt-6 inline-flex h-11 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition">{{ $t('saved.exploreFeed') }}</NuxtLink>
    </div>
  </main>
</template>
