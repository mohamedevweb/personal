<script setup lang="ts">
import type { LifeMoment } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { locale, t } = useI18n()
const toast = useToast()
const moments = ref<LifeMoment[]>([])
const loading = ref(true)
const modalOpen = ref(false)
const saving = ref(false)
const form = reactive({ content: '', category: 'Lesson', happened_at: new Date().toISOString().slice(0, 10), upcoming_at: '' })
const categories = ['Win', 'Failure', 'Lesson', 'Launch', 'Idea', 'Meeting', 'Milestone', 'Opinion', 'Upcoming event', 'Other']
const storyReasonKeys: Record<string, string> = {
  'personal and specific': 'personalSpecific',
  'personnel et précis': 'personalSpecific',
  'contains a clear narrative event': 'narrativeEvent',
  'contient un événement narratif clair': 'narrativeEvent',
  'strong transformation': 'transformation',
  'transformation forte': 'transformation',
  'enough detail to make the story credible': 'credibleDetail',
  'assez de détails pour rendre le récit crédible': 'credibleDetail',
  personal: 'personal',
  'relatable founder problem': 'founderProblem',
  'creates authority': 'authority',
  'future tension': 'futureTension',
  'creates anticipation': 'anticipation',
  'invites the audience into the journey': 'audienceJourney',
  'real customer insight': 'customerInsight',
  'specific pain point': 'painPoint',
  'supports your positioning': 'positioning'
}

function storyReason(reason: string): string {
  const key = storyReasonKeys[reason]
  return key ? t(`moments.reasons.${key}`) : reason
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString(locale.value, { month: 'short', day: 'numeric' })
}

async function load() {
  try {
    const response = await apiFetch<{ moments: LifeMoment[] }>('/api/moments')
    moments.value = response.moments
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('moments.loadError')))
  } finally {
    loading.value = false
  }
}

async function createMoment() {
  saving.value = true
  try {
    const response = await apiFetch<{ moment: LifeMoment }>('/api/moments', { method: 'POST', body: { ...form, upcoming_at: form.upcoming_at || null } })
    moments.value.unshift(response.moment)
    modalOpen.value = false
    form.content = ''
    toast.success(t('moments.created'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('moments.createError')))
  } finally { saving.value = false }
}

async function removeMoment(moment: LifeMoment) {
  try {
    await apiFetch(`/api/moments/${moment.id}`, { method: 'DELETE' })
    moments.value = moments.value.filter(item => item.id !== moment.id)
    toast.success(t('moments.deleted'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('moments.deleteError')))
  }
}

async function turnIntoContent(moment: LifeMoment) {
  try {
    const response = await apiFetch<{ remix: { id: number } }>(`/api/moments/${moment.id}/create-content`, { method: 'POST', body: { format: 'carousel' } })
    await navigateTo(`/remix/${response.remix.id}`)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('create.draftError')))
  }
}
onMounted(load)
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <header class="flex flex-col gap-4 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:flex-row md:items-center md:justify-between">
      <div class="flex items-start gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[12px] bg-[var(--accent-soft)] text-[var(--accent-ink)]"><AppIcon name="moments" :size="19" /></span>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t('moments.eyebrow') }}</p>
          <p class="mt-2 max-w-xl text-[15px] leading-6 text-[var(--muted)]">{{ $t('moments.subtitle') }}</p>
        </div>
      </div>
      <button class="inline-flex h-11 w-fit shrink-0 items-center justify-center gap-2 rounded-full b-btn-red px-5 text-[14px] font-medium transition" @click="modalOpen = true">
        <AppIcon name="plus" :size="17" />{{ $t('moments.addMoment') }}
      </button>
    </header>

    <div class="mt-5 space-y-4">
      <div v-if="loading" class="h-36 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
      <article v-for="moment in moments" :key="moment.id" class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 shadow-[0_1px_2px_rgba(23,23,26,.04)] transition hover:shadow-[0_12px_30px_rgba(23,23,26,.06)]">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="max-w-2xl">
            <div class="flex items-center gap-2">
              <span class="rounded-full bg-[var(--paper)] px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--muted)]">{{ $t('moments.categories.' + moment.category) }}</span>
              <span class="text-xs text-[var(--faint)]">{{ formatDate(moment.happened_at || moment.created_at) }}</span>
            </div>
            <p class="mt-4 text-[18px] leading-7">{{ moment.content }}</p>
          </div>
          <div class="rounded-[14px] bg-[var(--accent-soft)] px-4 py-3 text-center text-[var(--accent-ink)]">
            <strong class="font-serif text-2xl">{{ moment.story_score }}/10</strong>
            <p class="text-[9px] uppercase tracking-widest">{{ $t('moments.storyPotential') }}</p>
          </div>
        </div>
        <div class="mt-5 flex flex-wrap gap-x-4 gap-y-1.5">
          <span v-for="reason in moment.story_reasons" :key="reason" class="text-xs text-[var(--muted)]">✓ {{ storyReason(reason) }}</span>
        </div>
        <div class="mt-6 flex items-center gap-3 border-t border-[var(--line-soft)] pt-4">
          <button class="inline-flex h-9 items-center justify-center rounded-full b-btn-red px-4 text-[12.5px] font-medium transition" @click="turnIntoContent(moment)">{{ $t('moments.turnIntoContent') }}</button>
          <button class="text-xs text-[var(--faint)] transition hover:text-[var(--danger)]" @click="removeMoment(moment)">{{ $t('moments.delete') }}</button>
        </div>
      </article>
    </div>

    <div v-if="modalOpen" class="fixed inset-0 z-50 grid place-items-center bg-[var(--ink)]/40 p-5 backdrop-blur-sm" @click.self="modalOpen = false">
      <form class="w-full max-w-lg rounded-[24px] border border-[var(--line)] bg-[var(--surface)] p-6 shadow-[0_30px_80px_rgba(23,23,26,.25)] md:p-8" @submit.prevent="createMoment">
        <div class="flex items-center justify-between">
          <h2 class="font-serif text-[28px] tracking-[-.02em]">{{ $t('moments.modalTitle') }}</h2>
          <button type="button" class="text-sm text-[var(--faint)] transition hover:text-[var(--ink)]" @click="modalOpen = false">{{ $t('common.close') }}</button>
        </div>
        <p class="mt-2 text-sm text-[var(--muted)]">{{ $t('moments.modalPrompt') }}</p>
        <textarea v-model="form.content" required autofocus class="mt-6 min-h-36 w-full rounded-[14px] border border-[var(--line)] bg-[var(--paper)] p-4 text-[16px] leading-6 outline-none transition focus:border-[var(--muted)]" :placeholder="$t('moments.modalPlaceholder')" />
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="text-xs text-[var(--muted)]">{{ $t('moments.category') }}
            <select v-model="form.category" class="mt-2 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 py-3 text-sm outline-none">
              <option v-for="category in categories" :key="category" :value="category">{{ $t('moments.categories.' + category) }}</option>
            </select>
          </label>
          <label class="text-xs text-[var(--muted)]">{{ $t('moments.date') }}
            <input v-model="form.happened_at" type="date" class="mt-2 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 py-3 text-sm outline-none">
          </label>
        </div>
        <button class="mt-6 h-12 w-full rounded-full b-btn-red text-[15px] font-medium transition disabled:opacity-60" :disabled="saving">{{ saving ? $t('moments.finding') : $t('moments.saveMoment') }}</button>
      </form>
    </div>
  </main>
</template>
