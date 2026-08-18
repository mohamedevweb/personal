<script setup lang="ts">
import type { ContentPost, Opportunity } from '~/types/product'

const { apiFetch } = usePersonalApi()
const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<{ greeting_name: string, opportunity_count: number, featured_opportunity: Opportunity | null, items: ContentPost[] } | null>(null)
const dayLabel = new Intl.DateTimeFormat('en', { weekday: 'long' }).format(new Date())

async function loadFeed() {
  try {
    data.value = await apiFetch('/api/feed')
  } catch (exception: any) {
    error.value = exception?.data?.message || 'Personal could not load today’s opportunities.'
  } finally {
    loading.value = false
  }
}

async function save(post: ContentPost) {
  const response = await apiFetch<{ saved: boolean }>(`/api/content/${post.id}/save`, { method: 'POST' })
  post.is_saved = response.saved
}

async function dismiss(post: ContentPost) {
  await apiFetch(`/api/content/${post.id}/dismiss`, { method: 'POST' })
  if (data.value) data.value.items = data.value.items.filter(item => item.id !== post.id)
}

async function remix(post: ContentPost) {
  try {
    const response = await apiFetch<{ remix: { id: number } }>(`/api/content/${post.id}/remix`, {
      method: 'POST', body: { format: 'carousel' }
    })
    await navigateTo(`/remix/${response.remix.id}`)
  } catch (exception: any) {
    error.value = exception?.data?.message || 'Personal could not draft this right now. Please try again.'
  }
}

onMounted(loadFeed)
</script>

<template>
  <main class="mx-auto max-w-[1180px] px-5 py-10 md:px-10 md:py-14">
    <header class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-[11px] font-medium uppercase tracking-[.17em] text-[#908c83]">{{ dayLabel }} · Your daily brief</p>
        <h1 class="mt-4 font-serif text-[42px] leading-none tracking-[-.04em] md:text-[58px]">Good morning<template v-if="data?.greeting_name">, {{ data.greeting_name }}</template>.</h1>
        <p v-if="data" class="mt-4 text-[16px] text-[#716e67]">I found <span class="font-medium text-[#292824]">{{ data.opportunity_count }} content opportunities</span> for you today.</p>
      </div>
      <NuxtLink to="/create" class="inline-flex w-fit items-center gap-2 rounded-full bg-[#1d1d1b] px-5 py-3 text-sm font-medium text-white"><AppIcon name="plus" :size="17" />Create from scratch</NuxtLink>
    </header>

    <section v-if="data?.featured_opportunity" class="relative mt-10 overflow-hidden rounded-[26px] bg-[#22221f] p-6 text-white md:p-8">
      <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full bg-[#c85234]/25 blur-3xl" />
      <div class="relative grid gap-7 md:grid-cols-[1fr_auto] md:items-end">
        <div>
          <p class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.16em] text-[#d7a999]"><AppIcon name="sparkles" :size="15" />A strong match today · {{ data.featured_opportunity.relevance_score }}%</p>
          <h2 class="mt-4 max-w-3xl font-serif text-2xl leading-tight tracking-[-.02em] md:text-[32px]">“{{ data.featured_opportunity.title }}”</h2>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-white/65">{{ data.featured_opportunity.explanation }}</p>
          <div v-if="data.featured_opportunity.life_moment" class="mt-5 inline-flex rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-white/75">Your moment: {{ data.featured_opportunity.life_moment.content }}</div>
        </div>
        <NuxtLink v-if="data.featured_opportunity.content_post_id" :to="`/content/${data.featured_opportunity.content_post_id}`" class="inline-flex h-11 items-center gap-2 rounded-full bg-white px-5 text-sm font-medium text-[#22221f]">See the pattern <AppIcon name="arrow" :size="15" /></NuxtLink>
      </div>
    </section>

    <div class="mt-12 flex items-center justify-between border-b border-[var(--line)] pb-4"><h2 class="text-sm font-medium">Worth creating today</h2><span class="text-xs text-[#918d85]">Ranked for you</span></div>

    <p v-if="error" class="mt-8 rounded-2xl border border-[#ddb9ae] bg-[#f9ece8] p-4 text-sm text-[#8b402a]">{{ error }}</p>
    <div v-if="loading" class="mt-7 grid gap-6 lg:grid-cols-2"><div v-for="i in 4" :key="i" class="h-[560px] animate-pulse rounded-[24px] bg-[#e9e5dc]" /></div>
    <div v-else class="mt-7 grid items-start gap-6 lg:grid-cols-2">
      <ContentCard v-for="post in data?.items" :key="post.id" :post="post" @save="save" @dismiss="dismiss" @remix="remix" />
    </div>
  </main>
</template>
