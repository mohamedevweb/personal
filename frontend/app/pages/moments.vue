<script setup lang="ts">
import type { LifeMoment } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { locale } = useI18n()
const moments = ref<LifeMoment[]>([])
const loading = ref(true)
const modalOpen = ref(false)
const saving = ref(false)
const form = reactive({ content: '', category: 'Lesson', happened_at: new Date().toISOString().slice(0, 10), upcoming_at: '' })
const categories = ['Win', 'Failure', 'Lesson', 'Launch', 'Idea', 'Meeting', 'Milestone', 'Opinion', 'Upcoming event', 'Other']

function formatDate(value: string) {
  return new Date(value).toLocaleDateString(locale.value, { month: 'short', day: 'numeric' })
}

async function load() { const response = await apiFetch<{ moments: LifeMoment[] }>('/api/moments'); moments.value = response.moments; loading.value = false }
async function createMoment() {
  saving.value = true
  try {
    const response = await apiFetch<{ moment: LifeMoment }>('/api/moments', { method: 'POST', body: { ...form, upcoming_at: form.upcoming_at || null } })
    moments.value.unshift(response.moment); modalOpen.value = false; form.content = ''
  } finally { saving.value = false }
}
async function removeMoment(moment: LifeMoment) { await apiFetch(`/api/moments/${moment.id}`, { method: 'DELETE' }); moments.value = moments.value.filter(item => item.id !== moment.id) }
async function turnIntoContent(moment: LifeMoment) {
  const response = await apiFetch<{ remix: { id: number } }>(`/api/moments/${moment.id}/create-content`, { method: 'POST', body: { format: 'carousel' } })
  await navigateTo(`/remix/${response.remix.id}`)
}
onMounted(load)
</script>

<template>
  <main class="mx-auto max-w-5xl px-5 py-10 md:px-10 md:py-14">
    <header class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between"><div><p class="text-[11px] font-semibold uppercase tracking-[.17em] text-[#918d85]">{{ $t('moments.eyebrow') }}</p><h1 class="mt-4 font-serif text-4xl tracking-[-.04em] md:text-[54px]" v-html="$t('moments.title')" /><p class="mt-4 max-w-2xl text-[16px] leading-7 text-[#716e67]">{{ $t('moments.subtitle') }}</p></div><button class="inline-flex w-fit items-center gap-2 rounded-full bg-[#1d1d1b] px-5 py-3 text-sm text-white" @click="modalOpen = true"><AppIcon name="plus" :size="17"/>{{ $t('moments.addMoment') }}</button></header>
    <div class="mt-12 space-y-4">
      <div v-if="loading" class="h-36 animate-pulse rounded-3xl bg-[#e8e4db]" />
      <article v-for="moment in moments" :key="moment.id" class="rounded-[24px] border border-[var(--line)] bg-[#fbfaf7] p-6 transition hover:shadow-[0_12px_35px_rgba(50,43,32,.06)]">
        <div class="flex flex-wrap items-start justify-between gap-4"><div class="max-w-2xl"><div class="flex items-center gap-2"><span class="rounded-full bg-[#efebe3] px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-[#77736c]">{{ $t('moments.categories.' + moment.category) }}</span><span class="text-xs text-[#aaa69d]">{{ formatDate(moment.happened_at || moment.created_at) }}</span></div><p class="mt-4 text-[19px] leading-7">{{ moment.content }}</p></div><div class="rounded-2xl bg-[#efe6dc] px-4 py-3 text-center text-[#8b402a]"><strong class="font-serif text-2xl">{{ moment.story_score }}/10</strong><p class="text-[9px] uppercase tracking-widest">{{ $t('moments.storyPotential') }}</p></div></div>
        <div class="mt-5 flex flex-wrap gap-2"><span v-for="reason in moment.story_reasons" :key="reason" class="text-xs text-[#77736c]">✓ {{ reason }}</span></div>
        <div class="mt-6 flex items-center gap-3 border-t border-[var(--line)] pt-4"><button class="rounded-full bg-[#1d1d1b] px-4 py-2.5 text-xs font-medium text-white" @click="turnIntoContent(moment)">{{ $t('moments.turnIntoContent') }}</button><button class="text-xs text-[#9a6555]" @click="removeMoment(moment)">{{ $t('moments.delete') }}</button></div>
      </article>
    </div>

    <div v-if="modalOpen" class="fixed inset-0 z-50 grid place-items-center bg-black/35 p-5 backdrop-blur-sm" @click.self="modalOpen = false"><form class="w-full max-w-lg rounded-[28px] bg-[#f9f7f2] p-6 shadow-2xl md:p-8" @submit.prevent="createMoment"><div class="flex items-center justify-between"><h2 class="font-serif text-3xl">{{ $t('moments.modalTitle') }}</h2><button type="button" class="text-[#88847d]" @click="modalOpen = false">{{ $t('common.close') }}</button></div><p class="mt-2 text-sm text-[#77736c]">{{ $t('moments.modalPrompt') }}</p><textarea v-model="form.content" required autofocus class="mt-6 min-h-36 w-full rounded-2xl border border-[#d8d4cb] bg-white p-4 text-[16px] leading-6 outline-none" :placeholder="$t('moments.modalPlaceholder')"/><div class="mt-4 grid gap-4 sm:grid-cols-2"><label class="text-xs text-[#77736c]">{{ $t('moments.category') }}<select v-model="form.category" class="mt-2 w-full rounded-xl border border-[#d8d4cb] bg-white px-3 py-3 text-sm"><option v-for="category in categories" :key="category" :value="category">{{ $t('moments.categories.' + category) }}</option></select></label><label class="text-xs text-[#77736c]">{{ $t('moments.date') }}<input v-model="form.happened_at" type="date" class="mt-2 w-full rounded-xl border border-[#d8d4cb] bg-white px-3 py-3 text-sm"></label></div><button class="mt-6 h-12 w-full rounded-full bg-[#1d1d1b] text-sm font-medium text-white" :disabled="saving">{{ saving ? $t('moments.finding') : $t('moments.saveMoment') }}</button></form></div>
  </main>
</template>
