<script setup lang="ts">
import type { ContentPost } from '~/types/product'

const { apiFetch } = usePersonalApi()
const items = ref<ContentPost[]>([])
const loading = ref(true)

async function unsave(post: ContentPost) { await apiFetch(`/api/content/${post.id}/save`, { method: 'POST' }); items.value = items.value.filter(item => item.id !== post.id) }
async function remix(post: ContentPost) { const response = await apiFetch<{ remix: { id: number } }>(`/api/content/${post.id}/remix`, { method: 'POST', body: { format: 'carousel' } }); await navigateTo(`/remix/${response.remix.id}`) }
onMounted(async () => { const response = await apiFetch<{ items: ContentPost[] }>('/api/saved'); items.value = response.items; loading.value = false })
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
    <div v-else-if="items.length" class="mt-5 grid gap-6 lg:grid-cols-2">
      <ContentCard v-for="post in items" :key="post.id" :post="post" @save="unsave" @dismiss="unsave" @remix="remix" />
    </div>
    <div v-else class="mt-5 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <p class="font-serif text-[26px] tracking-[-.02em]">{{ $t('saved.emptyTitle') }}</p>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('saved.emptyCopy') }}</p>
      <NuxtLink to="/feed" class="mt-6 inline-flex h-11 items-center justify-center rounded-full bg-[var(--ink)] px-5 text-[14px] font-medium text-[var(--paper)] transition hover:bg-black">{{ $t('saved.exploreFeed') }}</NuxtLink>
    </div>
  </main>
</template>
