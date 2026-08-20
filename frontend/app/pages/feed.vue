<script setup lang="ts">
import type { ContentPost, Opportunity } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t, locale } = useI18n()
const loading = ref(true)
const refreshing = ref(false)
const error = ref<string | null>(null)
const data = ref<{ greeting_name: string, opportunity_count: number, featured_opportunity: Opportunity | null, items: ContentPost[] } | null>(null)
const dayLabel = computed(() => new Intl.DateTimeFormat(locale.value, { weekday: 'long' }).format(new Date()))

// The hero chart reads the lift of the posts already on screen, so it always
// describes today's feed rather than a separate metric.
const lifts = computed(() => (data.value?.items || []).slice(0, 7).map(post => post.performance_ratio))
const bestLift = computed(() => (lifts.value.length ? Math.max(...lifts.value) : 0))

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
  <main class="page-shell pb-16 pt-2">
    <section class="hero-night relative overflow-hidden rounded-[24px] px-6 py-14 text-white md:px-12 md:py-16">
      <div class="mx-auto max-w-2xl text-center">
        <p class="inline-flex items-center gap-2.5 text-[10px] font-semibold uppercase tracking-[.22em] text-[var(--gold)]">
          <span class="grid h-6 w-6 place-items-center rounded-full border border-[var(--gold)]/35"><AppIcon name="sparkles" :size="12" /></span>
          {{ $t('feed.dailyBrief', { day: dayLabel }) }}
        </p>
        <h2 class="mt-7 font-serif text-[40px] leading-[1.03] tracking-[-.035em] md:text-[60px]">
          {{ data?.greeting_name ? $t('feed.greetingNamed', { name: data.greeting_name }) : $t('feed.greetingPlain') }}
        </h2>
        <p v-if="data" class="mx-auto mt-5 max-w-lg text-[15px] leading-7 text-white/65">
          <i18n-t keypath="feed.foundOpportunities" tag="span" scope="global">
            <template #highlight><span class="text-white">{{ $t('feed.opportunitiesHighlight', { count: data.opportunity_count }) }}</span></template>
          </i18n-t>
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
          <NuxtLink
            v-if="data?.featured_opportunity?.content_post_id"
            :to="`/content/${data.featured_opportunity.content_post_id}`"
            class="inline-flex h-12 items-center gap-2 rounded-full bg-[var(--paper)] px-6 text-[14px] font-medium text-[var(--night)] transition hover:bg-white"
          >
            {{ $t('feed.seePattern') }} <AppIcon name="arrow" :size="15" />
          </NuxtLink>
          <NuxtLink to="/create" class="inline-flex h-12 items-center gap-2 rounded-full border border-white/20 px-6 text-[14px] font-medium text-white transition hover:bg-white/10">
            <AppIcon name="plus" :size="16" />{{ $t('feed.createFromScratch') }}
          </NuxtLink>
        </div>
        <p class="mt-6 text-[12px] text-white/40">{{ $t('feed.rankedForYou') }}</p>
      </div>

      <div v-if="data" class="relative mx-auto mt-12 grid max-w-4xl gap-4" :class="data.featured_opportunity ? 'md:grid-cols-2' : ''">
        <div class="panel-night rounded-[18px] p-5">
          <div class="flex items-start justify-between gap-4 border-b border-white/10 pb-4">
            <div>
              <p class="text-[11px] text-white/45">{{ $t('feed.opportunitiesToday') }}</p>
              <p class="mt-1 text-[28px] leading-none tracking-[-.02em]">{{ data.opportunity_count }}</p>
            </div>
            <div class="text-right">
              <p class="text-[11px] text-white/45">{{ $t('feed.bestLift') }}</p>
              <p class="mt-1 text-[28px] leading-none tracking-[-.02em] text-[var(--gold)]">{{ bestLift ? bestLift.toFixed(1) + '×' : '—' }}</p>
            </div>
          </div>
          <div class="mt-5 flex h-24 items-end gap-2">
            <span
              v-for="(lift, index) in lifts"
              :key="index"
              class="flex-1 rounded-t-[4px] bg-gradient-to-t from-[#8a6a1e]/40 to-[var(--gold)]"
              :style="{ height: `${Math.max(12, (lift / (bestLift || 1)) * 100)}%` }"
            />
            <span v-if="!lifts.length" class="w-full rounded-[4px] border border-dashed border-white/10 py-8 text-center text-[11px] text-white/35">{{ $t('feed.emptyTitle') }}</span>
          </div>
        </div>

        <div v-if="data.featured_opportunity" class="panel-night divide-y divide-white/10 rounded-[18px]">
          <div class="flex gap-3.5 p-5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[12px] bg-white/10 text-[var(--gold)]"><AppIcon name="trend" :size="17" /></span>
            <div class="min-w-0">
              <p class="text-[11px] text-white/45">{{ $t('feed.strongMatch', { score: data.featured_opportunity.relevance_score }) }}</p>
              <p class="mt-1 line-clamp-2 text-[14px] leading-6">{{ data.featured_opportunity.title }}</p>
            </div>
          </div>
          <div class="flex gap-3.5 p-5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[12px] bg-white/10 text-white/70"><AppIcon name="sparkles" :size="17" /></span>
            <div class="min-w-0">
              <p class="text-[11px] text-white/45">{{ $t('feed.whyFirst') }}</p>
              <p class="mt-1 line-clamp-2 text-[14px] leading-6">{{ data.featured_opportunity.explanation }}</p>
            </div>
          </div>
          <div v-if="data.featured_opportunity.life_moment" class="flex gap-3.5 p-5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[12px] bg-white/10 text-white/70"><AppIcon name="moments" :size="17" /></span>
            <div class="min-w-0">
              <p class="text-[11px] text-white/45">{{ $t('nav.moments') }}</p>
              <p class="mt-1 line-clamp-2 text-[14px] leading-6">{{ data.featured_opportunity.life_moment.content }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="mt-12 flex items-center justify-between border-b border-[var(--line)] pb-4">
      <h2 class="text-[11px] font-semibold uppercase tracking-[.18em] text-[var(--muted)]">{{ $t('feed.worthCreating') }}</h2>
      <button class="inline-flex h-9 items-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-4 text-[12.5px] text-[var(--muted)] transition hover:text-[var(--ink)] disabled:opacity-60" :disabled="refreshing || loading" @click="refresh">
        <AppIcon name="sparkles" :size="14" />{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}
      </button>
    </div>

    <p v-if="error" role="alert" class="mt-8 rounded-[18px] border border-[var(--danger-line)] bg-[var(--danger-soft)] p-4 text-sm text-[var(--danger)]">{{ error }}</p>
    <div v-if="loading" class="mt-7 grid gap-6 lg:grid-cols-2"><div v-for="i in 4" :key="i" class="h-[560px] animate-pulse rounded-[18px] bg-[var(--sand-soft)]" /></div>
    <div v-else-if="data && data.items.length === 0" class="mt-7 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <h3 class="font-serif text-2xl tracking-[-.02em]">{{ $t('feed.emptyTitle') }}</h3>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('feed.emptyBody') }}</p>
      <button class="mt-6 inline-flex h-11 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-5 text-[14px] font-medium text-[var(--paper)] transition hover:bg-black disabled:cursor-wait disabled:opacity-60" :disabled="refreshing" @click="refresh">
        <AppIcon name="sparkles" :size="16" />{{ refreshing ? $t('feed.refreshing') : $t('feed.refresh') }}
      </button>
    </div>
    <div v-else class="mt-7 grid items-start gap-6 lg:grid-cols-2">
      <ContentCard v-for="post in data?.items" :key="post.id" :post="post" @save="save" @dismiss="dismiss" @remix="remix" />
    </div>
  </main>
</template>
