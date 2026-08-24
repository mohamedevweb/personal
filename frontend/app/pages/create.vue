<script setup lang="ts">
import type { LifeMoment, Opportunity, Remix } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()
const { begin: beginRemix, attach: attachRemix, clear: clearRemix } = useRemixLaunch()
const opportunities = ref<Opportunity[]>([])
const moments = ref<LifeMoment[]>([])
const selectedMomentId = ref<number | null>(null)
const selectedAngleIndex = ref(0)
const loading = ref(true)
const drafting = ref(false)
const composerOpen = ref(false)

const opportunityKeys: Record<string, string> = {
  'Tell the story of your pivot using a failure → realization → new direction format.': 'pivot',
  'Turn one customer sentence into a sharp problem-awareness carousel.': 'customerSentence',
  'Build anticipation around the incubator decision before the outcome is known.': 'incubator',
  'Turn this moment into a story your audience can use': 'lifeMoment'
}

// A recommendation without a moment cannot be drafted, so it should not
// influence the selection or appear as an actionable suggestion.
const pick = computed(() => opportunities.value.find(opportunity => opportunity.life_moment))
const hasMaterial = computed(() => moments.value.length > 0)
const selectedMoment = computed(() => moments.value.find(moment => moment.id === selectedMomentId.value) || null)
const visibleMoments = computed(() => {
  const firstMoments = moments.value.slice(0, 4)
  if (!selectedMoment.value || firstMoments.some(moment => moment.id === selectedMoment.value?.id)) return firstMoments
  return [selectedMoment.value, ...firstMoments.slice(0, 3)]
})

function pickCopy(type: 'title' | 'explanation'): string | undefined {
  const opportunity = pick.value
  if (!opportunity) return undefined
  const key = opportunityKeys[opportunity.title]
  return key ? t(`create.opportunities.${key}.${type}`) : opportunity[type]
}

const angles = computed(() => [
  { title: t('create.cards.story.title'), copy: t('create.cards.story.copy'), format: 'carousel', icon: 'moments' },
  { title: t('create.cards.teach.title'), copy: t('create.cards.teach.copy'), format: 'carousel', icon: 'carousel' },
  { title: t('create.cards.opinion.title'), copy: t('create.cards.opinion.copy'), format: 'reel', icon: 'reel' }
] as const)
const selectedAngle = computed(() => angles.value[selectedAngleIndex.value]!)
const selectedMomentIsPick = computed(() => selectedMoment.value?.id === pick.value?.life_moment?.id)

/* Adding material used to hand the creator off to another page and drop them
   back here. The composer opens in place instead, and what they just wrote is
   selected so the next click is the one that matters. */
function addMoment() {
  composerOpen.value = true
}

function onMomentCreated(moment: LifeMoment) {
  moments.value.unshift(moment)
  selectedMomentId.value = moment.id
}

async function createFromMoment(moment: LifeMoment, format: Remix['format']) {
  if (drafting.value) return
  drafting.value = true
  try {
    const response = await apiFetch<{ remix: { id: number, status: string } }>(`/api/moments/${moment.id}/create-content`, { method: 'POST', body: { format } })
    /* An existing draft comes back untouched; only a generation that actually
       starts now earns the stage. */
    if (response.remix.status === 'generating') {
      beginRemix({ format, sourceHook: null, moment: moment.content })
      attachRemix(response.remix.id)
    }
    await navigateTo(`/remix/${response.remix.id}`)
  } catch (exception: unknown) {
    clearRemix()
    toast.error(apiErrorMessage(exception, t('create.draftError')))
  } finally {
    drafting.value = false
  }
}

function createSelected() {
  if (!selectedMoment.value) return
  return createFromMoment(selectedMoment.value, selectedAngle.value.format)
}

onMounted(async () => {
  try {
    const [ops, momentData] = await Promise.all([
      apiFetch<{ opportunities: Opportunity[] }>('/api/opportunities'),
      apiFetch<{ moments: LifeMoment[] }>('/api/moments')
    ])
    opportunities.value = ops.opportunities
    moments.value = momentData.moments
    const recommendedMoment = moments.value.find(moment => moment.id === pick.value?.life_moment?.id)
    selectedMomentId.value = recommendedMoment?.id || moments.value[0]?.id || null
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('create.loadError')))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <header class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t('create.eyebrow') }}</p>
        <h1 class="mt-3 max-w-2xl font-serif text-[34px] leading-[1.08] tracking-[-.03em] md:text-[42px]">{{ $t('create.title') }}</h1>
        <p class="mt-3 max-w-2xl text-[15px] leading-6 text-[var(--muted)]">{{ $t('create.subtitle') }}</p>
      </div>
      <button
        type="button"
        class="inline-flex h-11 w-fit shrink-0 items-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-5 text-[13px] font-medium transition hover:border-[var(--muted)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]"
        @click="addMoment"
      >
        <AppIcon name="plus" :size="16" />{{ $t('create.addMoment') }}
      </button>
    </header>

    <div v-if="loading" class="mt-7 overflow-hidden rounded-[20px] border border-[var(--line)] bg-[var(--surface)]">
      <div class="space-y-3 p-5 md:p-7">
        <div class="h-5 w-52 animate-pulse rounded bg-[var(--sand-soft)]" />
        <div v-for="item in 3" :key="item" class="h-20 animate-pulse rounded-[14px] bg-[var(--sand-soft)]" />
      </div>
      <div class="grid gap-3 border-t border-[var(--line)] p-5 md:grid-cols-3 md:p-7">
        <div v-for="item in 3" :key="item" class="h-24 animate-pulse rounded-[14px] bg-[var(--sand-soft)]" />
      </div>
    </div>

    <section
      v-else-if="!hasMaterial"
      class="mt-7 rounded-[20px] border border-dashed border-[var(--line)] bg-[var(--surface)] p-6 md:p-8"
    >
      <span class="grid h-11 w-11 place-items-center rounded-[12px] bg-[var(--accent-soft)] text-[var(--accent-ink)]"><AppIcon name="moments" :size="19" /></span>
      <h2 class="mt-5 font-serif text-[28px] tracking-[-.03em]">{{ $t('create.empty.title') }}</h2>
      <p class="mt-3 max-w-xl text-[15px] leading-6 text-[var(--muted)]">{{ $t('create.empty.copy') }}</p>
      <button type="button" class="b-btn-red mt-6 inline-flex h-11 items-center gap-2 rounded-full px-5 text-[14px] font-medium" @click="addMoment">
        <AppIcon name="plus" :size="17" />{{ $t('create.empty.action') }}
      </button>
    </section>

    <section v-else class="mt-7 overflow-hidden rounded-[20px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)]">
      <div class="p-5 md:p-7">
        <div class="flex items-start justify-between gap-5">
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--accent-ink)]">{{ $t('create.stepMoment') }}</p>
            <h2 class="mt-2 font-serif text-[26px] tracking-[-.025em]">{{ $t('create.chooseMoment') }}</h2>
            <p class="mt-1 text-sm text-[var(--muted)]">{{ $t('create.momentHelp') }}</p>
          </div>
          <NuxtLink to="/moments" class="hidden shrink-0 text-xs text-[var(--muted)] underline-offset-4 transition hover:text-[var(--ink)] hover:underline sm:block">{{ $t('create.viewAllMoments') }}</NuxtLink>
        </div>

        <div class="mt-5 space-y-2">
          <button
            v-for="moment in visibleMoments"
            :key="moment.id"
            type="button"
            class="flex w-full items-center gap-4 rounded-[14px] border p-4 text-left transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)] disabled:cursor-wait disabled:opacity-70"
            :class="selectedMomentId === moment.id ? 'border-[var(--accent)] bg-[var(--b-red-50)]' : 'border-[var(--line-soft)] hover:border-[var(--line)] hover:bg-[var(--paper)]'"
            :aria-pressed="selectedMomentId === moment.id"
            :disabled="drafting"
            @click="selectedMomentId = moment.id"
          >
            <span
              class="grid h-5 w-5 shrink-0 place-items-center rounded-full border transition"
              :class="selectedMomentId === moment.id ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : 'border-[var(--line)] bg-[var(--surface)] text-transparent'"
            ><AppIcon name="check" :size="12" :stroke-width="2.2" /></span>
            <span class="min-w-0 flex-1">
              <span class="line-clamp-2 text-[14px] leading-5 text-[var(--copy)] md:text-[15px]">{{ moment.content }}</span>
              <span class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-[var(--faint)]">
                <span>{{ $t(`moments.categories.${moment.category}`) }}</span>
                <span>{{ $t('create.storyScore', { score: moment.story_score }) }}</span>
              </span>
            </span>
            <span v-if="pick?.life_moment?.id === moment.id" class="hidden shrink-0 items-center gap-1.5 rounded-full bg-[var(--accent-soft)] px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[.1em] text-[var(--accent-ink)] sm:inline-flex">
              <AppIcon name="sparkles" :size="12" />{{ $t('create.recommended') }}
            </span>
          </button>
        </div>

        <div v-if="selectedMomentIsPick" class="mt-3 flex items-start gap-3 rounded-[12px] bg-[var(--paper)] px-4 py-3 text-xs leading-5 text-[var(--muted)]">
          <AppIcon name="sparkles" :size="15" class="mt-0.5 shrink-0 text-[var(--accent)]" />
          <p><strong class="font-medium text-[var(--ink)]">{{ $t('create.whyRecommended') }}</strong> {{ pickCopy('explanation') }}</p>
        </div>

        <NuxtLink to="/moments" class="mt-4 inline-block text-xs text-[var(--muted)] underline-offset-4 hover:text-[var(--ink)] hover:underline sm:hidden">{{ $t('create.viewAllMoments') }}</NuxtLink>
      </div>

      <div class="border-t border-[var(--line)] bg-[var(--paper)]/55 p-5 md:p-7">
        <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--accent-ink)]">{{ $t('create.stepAngle') }}</p>
        <h2 class="mt-2 font-serif text-[26px] tracking-[-.025em]">{{ $t('create.chooseAngle') }}</h2>
        <p class="mt-1 text-sm text-[var(--muted)]">{{ $t('create.angleHelp') }}</p>

        <div class="mt-5 grid gap-2.5 md:grid-cols-3">
          <button
            v-for="(angle, index) in angles"
            :key="angle.title"
            type="button"
            class="flex items-center gap-3 rounded-[14px] border p-4 text-left transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)] disabled:cursor-wait disabled:opacity-70"
            :class="selectedAngleIndex === index ? 'border-[var(--accent)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)]' : 'border-[var(--line)] bg-transparent hover:bg-[var(--surface)]'"
            :aria-pressed="selectedAngleIndex === index"
            :disabled="drafting"
            @click="selectedAngleIndex = index"
          >
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[11px]" :class="selectedAngleIndex === index ? 'bg-[var(--accent-soft)] text-[var(--accent-ink)]' : 'bg-[var(--sand-soft)] text-[var(--muted)]'">
              <AppIcon :name="angle.icon" :size="18" />
            </span>
            <span class="min-w-0 flex-1">
              <strong class="text-sm font-medium">{{ angle.title }}</strong>
              <span class="mt-1 block text-xs leading-5 text-[var(--muted)]">{{ angle.copy }}</span>
            </span>
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-4 border-t border-[var(--line)] px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-7">
        <div class="min-w-0">
          <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('create.selectedMoment') }}</p>
          <p class="mt-1 truncate text-sm text-[var(--muted)]">{{ selectedMoment?.content }}</p>
        </div>
        <button
          type="button"
          class="b-btn-red inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-full px-6 text-[14px] font-medium transition disabled:cursor-wait disabled:opacity-70"
          :disabled="drafting || !selectedMoment"
          @click="createSelected"
        >
          {{ drafting ? $t('create.drafting') : $t('create.transform') }}
          <AppIcon v-if="!drafting" name="arrow" :size="16" />
        </button>
      </div>
      <p v-if="drafting" role="status" class="sr-only">{{ $t('create.drafting') }}</p>
    </section>

    <MomentComposer
      :open="composerOpen"
      @close="composerOpen = false"
      @created="onMomentCreated"
    />
  </main>
</template>
