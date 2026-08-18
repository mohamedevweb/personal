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
  <main class="mx-auto max-w-[1120px] px-5 py-10 md:px-10 md:py-14">
    <p class="text-[11px] font-semibold uppercase tracking-[.17em] text-[#918d85]">Your library</p><h1 class="mt-4 font-serif text-4xl tracking-[-.04em] md:text-[54px]">Saved for later.</h1><p class="mt-4 text-[#716e67]">The patterns and stories you want to return to.</p>
    <div v-if="loading" class="mt-10 h-96 animate-pulse rounded-3xl bg-[#e8e4db]" />
    <div v-else-if="items.length" class="mt-10 grid gap-6 lg:grid-cols-2"><ContentCard v-for="post in items" :key="post.id" :post="post" @save="unsave" @dismiss="unsave" @remix="remix" /></div>
    <div v-else class="mt-16 rounded-[28px] border border-dashed border-[#cdc8be] p-12 text-center"><p class="font-serif text-2xl">Nothing saved yet.</p><p class="mt-2 text-sm text-[#77736c]">Save a strong pattern when it feels useful—not just interesting.</p><NuxtLink to="/feed" class="mt-6 inline-flex rounded-full bg-[#1d1d1b] px-5 py-2.5 text-sm text-white">Explore your feed</NuxtLink></div>
  </main>
</template>
