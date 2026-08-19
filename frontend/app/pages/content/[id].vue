<script setup lang="ts">
import type { ContentPost, LifeMoment } from '~/types/product'
import { compactNumber, relativeDate } from '~/types/product'

const route = useRoute()
const { apiFetch } = usePersonalApi()
const post = ref<ContentPost | null>(null)
const moments = ref<LifeMoment[]>([])
const format = ref<'reel' | 'carousel' | 'caption'>('carousel')
const selectedMoment = ref<number | null>(null)
const generating = ref(false)

async function createRemix() {
  if (!post.value) return
  generating.value = true
  try {
    const response = await apiFetch<{ remix: { id: number } }>(`/api/content/${post.value.id}/remix`, {
      method: 'POST', body: { format: format.value, life_moment_id: selectedMoment.value }
    })
    await navigateTo(`/remix/${response.remix.id}`)
  } finally { generating.value = false }
}

onMounted(async () => {
  const [contentResponse, momentsResponse] = await Promise.all([
    apiFetch<{ content: ContentPost }>(`/api/content/${route.params.id}`),
    apiFetch<{ moments: LifeMoment[] }>('/api/moments')
  ])
  post.value = contentResponse.content
  moments.value = momentsResponse.moments
  selectedMoment.value = moments.value[0]?.id || null
})
</script>

<template>
  <main v-if="post" class="mx-auto max-w-[1180px] px-5 py-8 md:px-10 md:py-12">
    <NuxtLink to="/feed" class="text-sm text-[#7c7971]">{{ $t('content.backToFeed') }}</NuxtLink>
    <div class="mt-8 grid gap-10 lg:grid-cols-[.88fr_1.12fr]">
      <section class="lg:sticky lg:top-8 lg:self-start">
        <div class="relative aspect-[4/5] overflow-hidden rounded-[28px] bg-[#ddd8cf]"><img :src="post.thumbnail_url || ''" :alt="post.hook" class="h-full w-full object-cover"><div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/5 to-transparent"/><div class="absolute inset-x-6 bottom-6"><span class="rounded-full bg-white/15 px-3 py-1.5 text-[11px] text-white backdrop-blur">{{ post.format }}</span><h1 class="mt-4 text-[28px] font-medium leading-[1.12] tracking-[-.03em] text-white">{{ post.hook }}</h1></div></div>
        <div class="mt-4 flex items-center gap-3"><img :src="post.creator.avatar_url || ''" class="h-9 w-9 rounded-full"><div class="flex-1"><p class="text-sm font-medium">@{{ post.creator.username }}</p><p class="text-xs text-[#8c8880]">{{ $t('content.followers', { count: compactNumber(post.creator.followers) }) }} · {{ relativeDate(post.published_at) }}</p></div><p class="text-xs text-[#77736c]">{{ $t('content.views', { count: compactNumber(post.views) }) }}</p></div>
      </section>

      <section>
        <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-[#918d85]">{{ $t('content.analysis') }}</p>
        <div class="mt-5 inline-flex items-baseline gap-2 rounded-2xl bg-[#efe6dc] px-5 py-4 text-[#8b402a]"><span class="font-serif text-4xl">{{ post.performance_ratio.toFixed(1) }}×</span><span class="text-xs">{{ $t('content.usualPerformance') }}</span></div>
        <div class="mt-8 divide-y divide-[var(--line)] border-y border-[var(--line)]">
          <div class="py-6"><p class="text-xs font-semibold uppercase tracking-widest text-[#97938b]">{{ $t('content.hook') }}</p><p class="mt-2 text-[17px] leading-7">{{ post.hook_analysis }}</p></div>
          <div class="py-6"><p class="text-xs font-semibold uppercase tracking-widest text-[#97938b]">{{ $t('content.structure') }}</p><p class="mt-2 text-[17px] leading-7">{{ post.structure_analysis }}</p></div>
          <div class="py-6"><p class="text-xs font-semibold uppercase tracking-widest text-[#97938b]">{{ $t('content.whyOutperforming') }}</p><p class="mt-2 text-[17px] leading-7">{{ post.why_it_works }} {{ $t('content.whyOutperformingSuffix', { average: compactNumber(post.creator.average_views), views: compactNumber(post.views) }) }}</p></div>
          <div class="py-6"><p class="text-xs font-semibold uppercase tracking-widest text-[#97938b]">{{ $t('content.whyFitsYou') }}</p><p class="mt-2 text-[17px] leading-7">{{ $t('content.whyFitsYouCopy') }}</p></div>
        </div>

        <div class="mt-8 rounded-[24px] border border-[var(--line)] bg-[#fbfaf7] p-6">
          <h2 class="font-serif text-2xl">{{ $t('content.makeItYours') }}</h2><p class="mt-2 text-sm text-[#77736c]">{{ $t('content.makeItYoursCopy') }}</p>
          <div class="mt-5 flex flex-wrap gap-2"><button v-for="item in ['reel','carousel','caption']" :key="item" class="rounded-full border px-4 py-2 text-xs capitalize" :class="format === item ? 'border-[#1d1d1b] bg-[#1d1d1b] text-white' : 'border-[#d8d4cb]'" @click="format = item as any">{{ item === 'caption' ? $t('content.captionOption') : item }}</button></div>
          <label v-if="moments.length" class="mt-5 block text-xs text-[#77736c]">{{ $t('content.groundInMoment') }}<select v-model="selectedMoment" class="mt-2 w-full rounded-xl border border-[#d8d4cb] bg-white px-3 py-3 text-sm"><option :value="null">{{ $t('content.letPersonalChoose') }}</option><option v-for="moment in moments" :key="moment.id" :value="moment.id">{{ moment.content }}</option></select></label>
          <button class="mt-6 flex h-12 w-full items-center justify-center gap-2 rounded-full bg-[#1d1d1b] text-sm font-medium text-white disabled:opacity-60" :disabled="generating" @click="createRemix">{{ generating ? $t('content.creating') : $t('content.remixForMe') }} <AppIcon name="sparkles" :size="16" /></button>
        </div>
      </section>
    </div>
  </main>
</template>
