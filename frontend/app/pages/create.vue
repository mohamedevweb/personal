<script setup lang="ts">
import type { LifeMoment, Opportunity, Remix } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()
const { begin: beginRemix, attach: attachRemix, clear: clearRemix } = useRemixLaunch()
const opportunities = ref<Opportunity[]>([])
const moments = ref<LifeMoment[]>([])
const loading = ref(true)
const drafting = ref(false)

const opportunityKeys: Record<string, string> = {
  'Tell the story of your pivot using a failure → realization → new direction format.': 'pivot',
  'Turn one customer sentence into a sharp problem-awareness carousel.': 'customerSentence',
  'Build anticipation around the incubator decision before the outcome is known.': 'incubator',
  'Turn this moment into a story your audience can use': 'lifeMoment'
}

// The pick is only worth showing when it can actually be acted on: an
// opportunity without a life moment has no material to draft from, so its
// button would be dead and the page would look broken.
const pick = computed(() => opportunities.value.find(opportunity => opportunity.life_moment))
const hasMaterial = computed(() => moments.value.length > 0)

function pickCopy(type: 'title' | 'explanation'): string | undefined {
  const opportunity = pick.value
  if (!opportunity) return undefined
  const key = opportunityKeys[opportunity.title]
  return key ? t(`create.opportunities.${key}.${type}`) : opportunity[type]
}

const quickCards = computed(() => [
  { title: t('create.cards.story.title'), copy: t('create.cards.story.copy'), format: 'carousel' },
  { title: t('create.cards.teach.title'), copy: t('create.cards.teach.copy'), format: 'carousel' },
  { title: t('create.cards.opinion.title'), copy: t('create.cards.opinion.copy'), format: 'reel' }
] as const)

function addMoment() {
  return navigateTo('/moments?new=1')
}

async function createFromMoment(moment: LifeMoment, format: Remix['format'] = 'carousel') {
  if (drafting.value) return
  drafting.value = true
  beginRemix({ format, sourceHook: null, moment: moment.content })
  try {
    const response = await apiFetch<{ remix: { id: number } }>(`/api/moments/${moment.id}/create-content`, { method: 'POST', body: { format } })
    attachRemix(response.remix.id)
    await navigateTo(`/remix/${response.remix.id}`)
  } catch (exception: unknown) {
    clearRemix()
    toast.error(apiErrorMessage(exception, t('create.draftError')))
  } finally {
    drafting.value = false
  }
}

// Without material every card would be a no-op, so the same click becomes an
// invitation to write the first moment instead of doing nothing at all.
function startCard(index: number, format: Remix['format']) {
  if (!hasMaterial.value) return addMoment()
  return createFromMoment(moments.value[index % moments.value.length]!, format)
}

onMounted(async () => {
  try {
    const [ops, momentData] = await Promise.all([apiFetch<{ opportunities: Opportunity[] }>('/api/opportunities'), apiFetch<{ moments: LifeMoment[] }>('/api/moments')])
    opportunities.value = ops.opportunities
    moments.value = momentData.moments
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('create.loadError')))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <header>
      <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t('create.eyebrow') }}</p>
      <h1 class="mt-3 max-w-2xl font-serif text-[32px] leading-[1.1] tracking-[-.02em] md:text-[38px]">{{ $t('create.title') }}</h1>
      <p class="mt-3 max-w-xl text-[15px] leading-7 text-[var(--muted)]">{{ $t('create.subtitle') }}</p>
    </header>

    <p v-if="drafting" class="mt-5 text-sm text-[var(--muted)]">{{ $t('create.drafting') }}</p>

    <div v-if="loading" class="mt-6 h-40 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />

    <section
      v-else-if="pick"
      class="mt-6 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 shadow-[0_1px_2px_rgba(23,23,26,.04)] md:p-8"
    >
      <p class="inline-flex items-center gap-2.5 text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--accent-ink)]">
        <span class="grid h-6 w-6 place-items-center rounded-full bg-[var(--accent-soft)]"><AppIcon name="sparkles" :size="12" /></span>
        {{ $t('create.personalsPick') }}
      </p>
      <h2 class="mt-5 max-w-2xl font-serif text-[26px] leading-[1.15] tracking-[-.02em] md:text-[32px]">{{ pickCopy('title') }}</h2>
      <p class="mt-3 max-w-xl text-[15px] leading-7 text-[var(--muted)]">{{ pickCopy('explanation') }}</p>
      <button
        class="b-btn-red mt-7 inline-flex h-12 items-center rounded-full px-6 text-[14px] font-medium disabled:cursor-wait disabled:opacity-70"
        :disabled="drafting"
        @click="createFromMoment(pick.life_moment!)"
      >
        {{ $t('create.createThis') }}
      </button>
    </section>

    <section
      v-else-if="!hasMaterial"
      class="mt-6 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] p-6 md:p-8"
    >
      <span class="grid h-11 w-11 place-items-center rounded-[12px] bg-[var(--accent-soft)] text-[var(--accent-ink)]"><AppIcon name="moments" :size="19" /></span>
      <h2 class="mt-5 font-serif text-[26px] tracking-[-.02em]">{{ $t('create.empty.title') }}</h2>
      <p class="mt-3 max-w-xl text-[15px] leading-7 text-[var(--muted)]">{{ $t('create.empty.copy') }}</p>
      <button class="b-btn-red mt-7 inline-flex h-12 items-center gap-2 rounded-full px-6 text-[14px] font-medium" @click="addMoment()">
        <AppIcon name="plus" :size="17" />{{ $t('create.empty.action') }}
      </button>
    </section>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
      <button
        v-for="(item, index) in quickCards"
        :key="item.title"
        class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 text-left shadow-[0_1px_2px_rgba(23,23,26,.04)] transition hover:-translate-y-0.5 hover:shadow-[0_12px_30px_rgba(23,23,26,.07)] disabled:cursor-wait disabled:opacity-70"
        :disabled="drafting"
        @click="startCard(index, item.format)"
      >
        <span class="grid h-10 w-10 place-items-center rounded-[12px] bg-[var(--accent-soft)] text-[13px] font-semibold text-[var(--accent-ink)]">0{{ index + 1 }}</span>
        <h3 class="mt-6 font-serif text-[24px] tracking-[-.02em]">{{ item.title }}</h3>
        <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ item.copy }}</p>
        <span class="mt-6 inline-flex items-center gap-2 text-xs font-medium">{{ hasMaterial ? $t('create.begin') : $t('create.empty.action') }} <AppIcon name="arrow" :size="14" /></span>
      </button>
    </div>

    <template v-if="hasMaterial">
      <div class="mt-12 flex items-center justify-between border-b border-[var(--line)] pb-4">
        <h2 class="text-[11px] font-semibold uppercase tracking-[.18em] text-[var(--muted)]">{{ $t('create.bestMaterial') }}</h2>
        <NuxtLink to="/moments" class="text-xs text-[var(--muted)] transition hover:text-[var(--ink)]">{{ $t('create.viewAllMoments') }}</NuxtLink>
      </div>

      <div class="mt-4 overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]">
        <button
          v-for="moment in moments.slice(0, 3)"
          :key="moment.id"
          class="flex w-full items-center gap-5 border-b border-[var(--line-soft)] px-5 py-5 text-left transition last:border-0 hover:bg-[var(--paper)] disabled:cursor-wait disabled:opacity-70"
          :disabled="drafting"
          @click="createFromMoment(moment)"
        >
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[12px] bg-[var(--accent-soft)] font-serif text-[17px] text-[var(--accent-ink)]">{{ moment.story_score }}</span>
          <p class="flex-1 text-sm md:text-[15px]">{{ moment.content }}</p>
          <span class="hidden shrink-0 text-xs text-[var(--faint)] md:inline">{{ $t('create.turnIntoContent') }}</span>
          <AppIcon name="chevron" :size="15" class="shrink-0 text-[var(--faint)]" />
        </button>
      </div>
    </template>
  </main>
</template>
