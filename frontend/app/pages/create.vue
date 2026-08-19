<script setup lang="ts">
import type { LifeMoment, Opportunity } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const opportunities = ref<Opportunity[]>([])
const moments = ref<LifeMoment[]>([])
const error = ref<string | null>(null)
const drafting = ref(false)

const quickCards = computed(() => [
  { title: t('create.cards.story.title'), copy: t('create.cards.story.copy'), format: 'carousel' },
  { title: t('create.cards.teach.title'), copy: t('create.cards.teach.copy'), format: 'carousel' },
  { title: t('create.cards.opinion.title'), copy: t('create.cards.opinion.copy'), format: 'reel' }
])

async function createFromMoment(moment: LifeMoment, format: string = 'carousel') {
  if (drafting.value) return
  drafting.value = true
  error.value = null
  try {
    const response = await apiFetch<{ remix: { id: number } }>(`/api/moments/${moment.id}/create-content`, { method: 'POST', body: { format } })
    await navigateTo(`/remix/${response.remix.id}`)
  } catch (exception: any) {
    error.value = exception?.data?.message || t('create.draftError')
  } finally {
    drafting.value = false
  }
}

onMounted(async () => {
  const [ops, momentData] = await Promise.all([apiFetch<{ opportunities: Opportunity[] }>('/api/opportunities'), apiFetch<{ moments: LifeMoment[] }>('/api/moments')])
  opportunities.value = ops.opportunities; moments.value = momentData.moments
})
</script>

<template>
  <main class="mx-auto max-w-[1100px] px-5 pb-16 pt-2 md:px-8">
    <p v-if="error" role="alert" class="mb-5 rounded-[18px] border border-[#e6cfc7] bg-[#fbf1ee] p-4 text-sm text-[#8b402a]">{{ error }}</p>
    <p v-else-if="drafting" class="mb-5 text-sm text-[var(--muted)]">{{ $t('create.drafting') }}</p>

    <section class="hero-night relative overflow-hidden rounded-[26px] px-6 py-14 text-center text-white md:px-12 md:py-16">
      <p class="inline-flex items-center gap-2.5 text-[10px] font-semibold uppercase tracking-[.22em] text-[#e3b862]">
        <span class="grid h-6 w-6 place-items-center rounded-full border border-[#e3b862]/35"><AppIcon name="sparkles" :size="12" /></span>
        {{ $t('create.personalsPick') }}
      </p>
      <h2 class="mx-auto mt-7 max-w-3xl font-serif text-[34px] leading-[1.08] tracking-[-.03em] md:text-[50px]">{{ opportunities[0]?.title || $t('create.pickTitleFallback') }}</h2>
      <p class="mx-auto mt-5 max-w-xl text-[15px] leading-7 text-white/65">{{ opportunities[0]?.explanation || $t('create.pickCopyFallback') }}</p>
      <button
        v-if="opportunities[0]?.life_moment"
        class="mt-9 inline-flex h-12 items-center rounded-full bg-white px-6 text-sm font-medium text-[var(--night)] transition hover:bg-white/90 disabled:cursor-wait disabled:opacity-70"
        :disabled="drafting"
        @click="createFromMoment(opportunities[0].life_moment!)"
      >
        {{ $t('create.createThis') }}
      </button>
      <p class="mt-6 text-[12px] text-white/40">{{ $t('create.subtitle') }}</p>
    </section>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
      <button
        v-for="(item, index) in quickCards"
        :key="item.title"
        class="rounded-[20px] border border-[var(--line)] bg-[var(--surface)] p-6 text-left shadow-[0_1px_2px_rgba(23,23,26,.04)] transition hover:-translate-y-0.5 hover:shadow-[0_12px_30px_rgba(23,23,26,.07)]"
        @click="moments[index % Math.max(moments.length, 1)] && createFromMoment(moments[index % moments.length], item.format)"
      >
        <span class="grid h-10 w-10 place-items-center rounded-[12px] bg-[var(--accent-soft)] text-[13px] font-semibold text-[#8a6413]">0{{ index + 1 }}</span>
        <h3 class="mt-6 font-serif text-[24px] tracking-[-.02em]">{{ item.title }}</h3>
        <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ item.copy }}</p>
        <span class="mt-6 inline-flex items-center gap-2 text-xs font-medium">{{ $t('create.begin') }} <AppIcon name="arrow" :size="14" /></span>
      </button>
    </div>

    <div class="mt-12 flex items-center justify-between border-b border-[var(--line)] pb-4">
      <h2 class="text-sm font-medium">{{ $t('create.bestMaterial') }}</h2>
      <NuxtLink to="/moments" class="text-xs text-[var(--muted)] transition hover:text-[var(--ink)]">{{ $t('create.viewAllMoments') }}</NuxtLink>
    </div>

    <div class="mt-4 overflow-hidden rounded-[20px] border border-[var(--line)] bg-[var(--surface)]">
      <button
        v-for="moment in moments.slice(0, 3)"
        :key="moment.id"
        class="flex w-full items-center gap-5 border-b border-[var(--line-soft)] px-5 py-5 text-left transition last:border-0 hover:bg-[var(--paper)]"
        @click="createFromMoment(moment)"
      >
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[12px] bg-[var(--accent-soft)] font-serif text-[17px] text-[#8a6413]">{{ moment.story_score }}</span>
        <p class="flex-1 text-sm md:text-[15px]">{{ moment.content }}</p>
        <span class="hidden shrink-0 text-xs text-[var(--faint)] md:inline">{{ $t('create.turnIntoContent') }}</span>
        <AppIcon name="chevron" :size="15" class="shrink-0 text-[var(--faint)]" />
      </button>
    </div>
  </main>
</template>
