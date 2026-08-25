<script setup lang="ts">
import type { ContentPost, LifeMoment, Remix } from '~/types/product'
import { compactNumber, creatorProfileUrl, relativeDate } from '~/types/product'

const route = useRoute()
const { apiFetch } = usePersonalApi()
const { openWhenReady } = useRemixOpening()
const { locale, t } = useI18n()
const toast = useToast()
const post = ref<ContentPost | null>(null)
const moments = ref<LifeMoment[]>([])
const format = ref<'reel' | 'carousel' | 'caption'>('carousel')
const selectedMoment = ref<number | null>(null)
const generating = ref(false)
const loading = ref(true)
let analysisTimer: ReturnType<typeof setTimeout> | undefined
let analysisAttempts = 0

async function pollAnalysis() {
  try {
    const response = await apiFetch<{ content: ContentPost }>(`/api/content/${route.params.id}`)
    post.value = response.content
    analysisAttempts++
    if (response.content.analysis_status === 'pending' && analysisAttempts < 90) {
      analysisTimer = setTimeout(pollAnalysis, 2000)
    }
  } catch {
    analysisAttempts++
    if (analysisAttempts < 45) analysisTimer = setTimeout(pollAnalysis, 4000)
  }
}

async function requestAnalysis() {
  try {
    await apiFetch(`/api/content/${route.params.id}/analysis`, { method: 'POST' })
    analysisAttempts = 0
    analysisTimer = setTimeout(pollAnalysis, 1200)
  } catch {
    // The immediate heuristic remains useful when background analysis is unavailable.
  }
}

async function createRemix() {
  if (!post.value) return
  generating.value = true
  try {
    const response = await apiFetch<{ remix: Pick<Remix, 'id' | 'status'> }>(`/api/content/${post.value.id}/remix`, {
      method: 'POST', body: { format: format.value, life_moment_id: selectedMoment.value }
    })
    if (await openWhenReady(response.remix) === 'failed') toast.error(t('feed.remixError'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('feed.remixError')))
  } finally { generating.value = false }
}

onMounted(async () => {
  try {
    const [contentResponse, momentsResponse] = await Promise.all([
      apiFetch<{ content: ContentPost }>(`/api/content/${route.params.id}`),
      apiFetch<{ moments: LifeMoment[] }>('/api/moments')
    ])
    post.value = contentResponse.content
    moments.value = momentsResponse.moments
    selectedMoment.value = moments.value[0]?.id || null
    if (contentResponse.content.analysis_status === 'pending') requestAnalysis()
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('content.loadError')))
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => clearTimeout(analysisTimer))
</script>

<template>
  <main v-if="post" class="page-shell pb-16 pt-2">
    <NuxtLink to="/feed" class="text-sm text-[var(--muted)]">{{ $t('content.backToFeed') }}</NuxtLink>
    <div class="mt-6 grid gap-10 lg:grid-cols-[.88fr_1.12fr]">
      <section class="lg:sticky lg:top-8 lg:self-start">
        <div class="relative aspect-[4/5] overflow-hidden rounded-[18px] bg-[var(--sand)]"><img :src="post.thumbnail_url || ''" :alt="post.hook" class="h-full w-full object-cover"><div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/5 to-transparent"/><div class="absolute inset-x-6 bottom-6"><span class="rounded-full bg-white/15 px-3 py-1.5 text-[11px] text-white backdrop-blur">{{ post.format }}</span><h1 class="mt-4 text-[28px] font-medium leading-[1.12] tracking-[-.03em] text-white">{{ post.hook }}</h1></div></div>
        <div class="mt-4 flex items-center gap-3"><a :href="creatorProfileUrl(post.creator.username)" target="_blank" rel="noopener noreferrer" class="flex flex-1 items-center gap-3"><img :src="post.creator.avatar_url || ''" alt="" class="h-9 w-9 rounded-full"><div class="flex-1"><p class="text-sm font-medium hover:underline">@{{ post.creator.username }}</p><p class="text-xs text-[var(--faint)]">{{ $t('content.followers', { count: compactNumber(post.creator.followers) }) }} · {{ relativeDate(post.published_at, locale) }}</p></div></a><a v-if="post.source_url" :href="post.source_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-[var(--muted)] transition hover:text-[var(--ink)]">{{ $t('content.openSource') }}<AppIcon name="arrow" :size="13" class="-rotate-45" /></a><p v-if="post.views > 0" class="text-xs text-[var(--muted)]">{{ $t('content.views', { count: compactNumber(post.views) }) }}</p></div>
      </section>

      <section>
        <div class="flex items-center justify-between gap-4">
          <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-[var(--faint)]">{{ $t('content.analysis') }}</p>
          <p v-if="post.analysis_status === 'pending'" class="inline-flex items-center gap-2 text-[11px] text-[var(--muted)]">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[var(--ai)]" />
            {{ $t('content.analysisImproving') }}
          </p>
        </div>
        <div class="mt-5 inline-flex items-baseline gap-2 rounded-2xl bg-[var(--accent-soft)] px-5 py-4 text-[var(--accent-ink)]"><span class="font-serif text-4xl">{{ post.performance_ratio.toFixed(1) }}×</span><span class="text-xs">{{ $t('content.usualPerformance') }}</span></div>
        <!-- The number is only worth what its denominator is, so the denominator
             is stated here rather than left to be trusted. -->
        <div class="mt-3 rounded-[14px] border border-[var(--line)] bg-[var(--surface)] p-4">
          <p class="mb-2 text-[11px] font-semibold uppercase tracking-[.16em] text-[var(--faint)]">{{ $t('performance.title') }}</p>
          <PerformanceNote :post="post" />
        </div>
        <div class="mt-8 divide-y divide-[var(--line-soft)] border-y border-[var(--line)]">
          <div class="py-6"><p class="text-xs font-semibold uppercase tracking-widest text-[var(--faint)]">{{ $t('content.hook') }}</p><p class="mt-2 text-[17px] leading-7">{{ post.hook_analysis }}</p></div>
          <div class="py-6"><p class="text-xs font-semibold uppercase tracking-widest text-[var(--faint)]">{{ $t('content.structure') }}</p><p class="mt-2 text-[17px] leading-7">{{ post.structure_analysis }}</p></div>
          <div class="py-6"><p class="text-xs font-semibold uppercase tracking-widest text-[var(--faint)]">{{ $t('content.whyOutperforming') }}</p><p class="mt-2 text-[17px] leading-7">{{ post.why_it_works }}<template v-if="post.views > 0 && post.creator.average_views > 0"> {{ $t('content.whyOutperformingSuffix', { average: compactNumber(post.creator.average_views), views: compactNumber(post.views) }) }}</template></p></div>
          <div class="py-6"><p class="text-xs font-semibold uppercase tracking-widest text-[var(--faint)]">{{ $t('content.whyFitsYou') }}</p><p class="mt-2 text-[17px] leading-7">{{ $t('content.whyFitsYouCopy') }}</p></div>
        </div>

        <div class="mt-8 overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]">
          <div class="border-b border-[var(--line-soft)] px-6 py-5">
            <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('content.makeItYours') }}</h2>
            <p class="mt-1.5 text-[13.5px] leading-6 text-[var(--muted)]">{{ $t('content.makeItYoursCopy') }}</p>
          </div>

          <!-- The three shapes, each saying what you actually get, so the choice
               is made on the outcome rather than on a word. -->
          <div class="grid gap-px bg-[var(--line-soft)] sm:grid-cols-3">
            <button
              v-for="item in (['reel', 'carousel', 'caption'] as const)"
              :key="item"
              class="group flex items-start gap-3 px-5 py-4 text-left transition sm:block"
              :class="format === item ? 'bg-[var(--accent-soft)]' : 'bg-[var(--surface)] hover:bg-[var(--paper)]'"
              :aria-pressed="format === item"
              @click="format = item"
            >
              <span
                class="grid h-9 w-9 shrink-0 place-items-center rounded-[10px] transition"
                :class="format === item ? 'bg-[var(--ink)] text-[var(--paper)]' : 'bg-[var(--sand-soft)] text-[var(--muted)]'"
              >
                <AppIcon :name="item === 'caption' ? 'text' : item" :size="17" />
              </span>
              <span class="block sm:mt-3">
                <span class="block text-[13.5px] font-medium">{{ $t(`remix.formats.${item}`) }}</span>
                <span class="mt-1 block text-[12.5px] leading-5 text-[var(--muted)]">{{ $t(`content.formatBlurb.${item}`) }}</span>
              </span>
            </button>
          </div>

          <!-- Grounding is what keeps the draft yours, so the moments are shown
               rather than hidden behind a dropdown. -->
          <div v-if="moments.length" class="border-t border-[var(--line-soft)] px-6 py-5">
            <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[var(--faint)]">{{ $t('content.groundInMoment') }}</p>
            <div class="mt-3 max-h-52 space-y-1.5 overflow-y-auto pr-1">
              <button
                class="moment-row"
                :class="selectedMoment === null ? 'moment-row-on' : 'moment-row-off'"
                @click="selectedMoment = null"
              >
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-[9px] bg-[var(--sand-soft)] text-[var(--muted)]"><AppIcon name="sparkles" :size="14" /></span>
                <span class="flex-1 text-[13.5px]">{{ $t('content.letPersonalChoose') }}</span>
              </button>
              <button
                v-for="moment in moments"
                :key="moment.id"
                class="moment-row"
                :class="selectedMoment === moment.id ? 'moment-row-on' : 'moment-row-off'"
                @click="selectedMoment = moment.id"
              >
                <span
                  class="grid h-8 w-8 shrink-0 place-items-center rounded-[9px] font-serif text-[15px]"
                  :class="selectedMoment === moment.id ? 'bg-[var(--ink)] text-[var(--paper)]' : 'bg-[var(--accent-soft)] text-[var(--accent-ink)]'"
                  :title="$t('content.storyScore')"
                >{{ moment.story_score }}</span>
                <span class="flex-1 truncate text-[13.5px]">{{ moment.content }}</span>
              </button>
            </div>
          </div>

          <div class="border-t border-[var(--line-soft)] px-6 py-5">
            <button
              class="flex h-12 w-full items-center justify-center gap-2 rounded-full b-btn-red text-[15px] font-medium transition disabled:cursor-default"
              :disabled="generating"
              :aria-busy="generating"
              @click="createRemix"
            >
              {{ $t('content.remixForMe') }}
              <AppIcon name="sparkles" :size="16" />
            </button>
            <p class="mt-3 text-center text-[12px] text-[var(--faint)]">{{ $t('content.remixFooter') }}</p>
          </div>
        </div>
      </section>
    </div>
  </main>

  <main v-else-if="loading" class="page-shell pb-16 pt-2">
    <div class="h-5 w-32 animate-pulse rounded-full bg-[var(--sand-soft)]" />
    <div class="mt-6 grid gap-10 lg:grid-cols-[.88fr_1.12fr]">
      <div class="aspect-[4/5] animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
      <div class="space-y-5">
        <div class="h-20 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
        <div class="h-64 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
      </div>
    </div>
  </main>
</template>

<style scoped>
.moment-row { @apply flex w-full items-center gap-3 rounded-[12px] border px-3 py-2 text-left transition; }
.moment-row-on { @apply border-[var(--ink)] bg-[var(--paper)]; }
.moment-row-off { @apply border-[var(--line)] hover:border-[var(--muted)]; }
</style>
