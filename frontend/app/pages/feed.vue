<script setup lang="ts">
import type { ContentPost, Opportunity } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t, locale } = useI18n()
const loading = ref(true)
const refreshing = ref(false)
const error = ref<string | null>(null)
const data = ref<{ greeting_name: string, opportunity_count: number, featured_opportunity: Opportunity | null, items: ContentPost[] } | null>(null)
const dayLabel = computed(() => new Intl.DateTimeFormat(locale.value, { weekday: 'long' }).format(new Date()))

async function loadFeed() {
  try {
    data.value = await apiFetch('/api/feed')
  } catch (exception: any) {
    error.value = exception?.data?.message || t('feed.loadError')
  } finally {
    loading.value = false
  }
}

async function refresh() {
  if (refreshing.value) return
  refreshing.value = true
  try {
    await apiFetch('/api/feed/refresh', { method: 'POST' })
    // Discovery scrapes in the background and a real Apify run can take up to a
    // minute, so poll rather than reload once — stop as soon as posts appear.
    for (let attempt = 0; attempt < 12; attempt++) {
      await new Promise(resolve => setTimeout(resolve, 5000))
      await loadFeed()
      if (data.value && data.value.items.length > 0) break
    }
  } catch (exception: any) {
    error.value = exception?.data?.message || t('feed.loadError')
  } finally {
    refreshing.value = false
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
    error.value = exception?.data?.message || t('feed.remixError')
  }
}

onMounted(loadFeed)
</script>

<template>
  <main class="mx-auto max-w-[1180px] px-5 py-10 md:px-10 md:py-14">
    <header class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-[11px] font-medium uppercase tracking-[.17em] text-[#908c83]">{{ $t('feed.dailyBrief', { day: dayLabel }) }}</p>
        <h1 class="mt-4 font-serif text-[42px] leading-none tracking-[-.04em] md:text-[58px]">{{ data?.greeting_name ? $t('feed.greetingNamed', { name: data.greeting_name }) : $t('feed.greetingPlain') }}</h1>
        <p v-if="data" class="mt-4 text-[16px] text-[#716e67]"><i18n-t keypath="feed.foundOpportunities" tag="span" scope="global"><template #highlight><span class="font-medium text-[#292824]">{{ $t('feed.opportunitiesHighlight', { count: data.opportunity_count }) }}</span></template></i18n-t></p>
      </div>
      <NuxtLink to="/create" class="inline-flex w-fit items-center gap-2 rounded-full bg-[#1d1d1b] px-5 py-3 text-sm font-medium text-white"><AppIcon name="plus" :size="17" />{{ $t('feed.createFromScratch') }}</NuxtLink>
    </header>

    <section v-if="data?.featured_opportunity" class="relative mt-10 overflow-hidden rounded-[26px] bg-[#22221f] p-6 text-white md:p-8">
      <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full bg-[#c85234]/25 blur-3xl" />
      <div class="relative grid gap-7 md:grid-cols-[1fr_auto] md:items-end">
        <div>
          <p class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.16em] text-[#d7a999]"><AppIcon name="sparkles" :size="15" />{{ $t('feed.strongMatch', { score: data.featured_opportunity.relevance_score }) }}</p>
          <h2 class="mt-4 max-w-3xl font-serif text-2xl leading-tight tracking-[-.02em] md:text-[32px]">“{{ data.featured_opportunity.title }}”</h2>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-white/65">{{ data.featured_opportunity.explanation }}</p>
          <div v-if="data.featured_opportunity.life_moment" class="mt-5 inline-flex rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-white/75">{{ $t('feed.yourMoment', { content: data.featured_opportunity.life_moment.content }) }}</div>
        </div>
        <NuxtLink v-if="data.featured_opportunity.content_post_id" :to="`/content/${data.featured_opportunity.content_post_id}`" class="inline-flex h-11 items-center gap-2 rounded-full bg-white px-5 text-sm font-medium text-[#22221f]">{{ $t('feed.seePattern') }} <AppIcon name="arrow" :size="15" /></NuxtLink>
      </div>
    </section>

    <div class="mt-12 flex items-center justify-between border-b border-[var(--line)] pb-4">
      <h2 class="text-sm font-medium">{{ $t('feed.worthCreating') }}</h2>
      <button class="text-xs text-[#918d85] underline underline-offset-2 transition hover:text-[#1c1c1a] disabled:no-underline disabled:opacity-60" :disabled="refreshing || loading" @click="refresh">
        {{ refreshing ? $t('feed.refreshing') : $t('feed.rankedForYou') }}
      </button>
    </div>

    <p v-if="error" class="mt-8 rounded-2xl border border-[#ddb9ae] bg-[#f9ece8] p-4 text-sm text-[#8b402a]">{{ error }}</p>
    <div v-if="loading" class="mt-7 grid gap-6 lg:grid-cols-2"><div v-for="i in 4" :key="i" class="h-[560px] animate-pulse rounded-[24px] bg-[#e9e5dc]" /></div>
    <div v-else-if="data && data.items.length === 0" class="mt-7 rounded-[24px] border border-dashed border-[var(--line)] bg-[#fbfaf7] px-6 py-16 text-center">
      <h3 class="font-serif text-2xl tracking-[-.02em]">{{ $t('feed.emptyTitle') }}</h3>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[#77736c]">{{ $t('feed.emptyBody') }}</p>
      <button class="mt-6 inline-flex items-center gap-2 rounded-full bg-[#1d1d1b] px-5 py-3 text-sm font-medium text-white transition hover:bg-black disabled:cursor-wait disabled:opacity-60" :disabled="refreshing" @click="refresh">
        <AppIcon name="sparkles" :size="16" />{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}
      </button>
    </div>
    <div v-else class="mt-7 grid items-start gap-6 lg:grid-cols-2">
      <ContentCard v-for="post in data?.items" :key="post.id" :post="post" @save="save" @dismiss="dismiss" @remix="remix" />
    </div>
  </main>
</template>
