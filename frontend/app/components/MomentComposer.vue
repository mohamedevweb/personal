<script setup lang="ts">
import type { LifeMoment } from '~/types/product'

/* The one place a moment is written. The studio and the moments page both mount
   it, so adding material never means leaving the page you are working on. */
defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [], created: [moment: LifeMoment] }>()

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()

const saving = ref(false)
const form = reactive({
  content: '',
  category: 'Lesson',
  happened_at: new Date().toISOString().slice(0, 10),
  upcoming_at: ''
})
const categories = ['Win', 'Failure', 'Lesson', 'Launch', 'Idea', 'Meeting', 'Milestone', 'Opinion', 'Upcoming event', 'Other']

async function submit() {
  if (saving.value) return
  saving.value = true
  try {
    const response = await apiFetch<{ moment: LifeMoment }>('/api/moments', {
      method: 'POST',
      body: { ...form, upcoming_at: form.upcoming_at || null }
    })
    form.content = ''
    emit('created', response.moment)
    emit('close')
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('moments.createError')))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 grid place-items-center bg-[var(--ink)]/40 p-5 backdrop-blur-sm" @click.self="emit('close')">
    <form class="w-full max-w-lg rounded-[24px] border border-[var(--line)] bg-[var(--surface)] p-6 shadow-[0_30px_80px_rgba(23,23,26,.25)] md:p-8" @submit.prevent="submit">
      <div class="flex items-center justify-between">
        <h2 class="font-serif text-[28px] tracking-[-.02em]">{{ $t('moments.modalTitle') }}</h2>
        <button type="button" class="text-sm text-[var(--faint)] transition hover:text-[var(--ink)]" @click="emit('close')">{{ $t('common.close') }}</button>
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
</template>
