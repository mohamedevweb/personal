<script setup lang="ts">
import type { LifeMoment } from '~/types/product'

/* The one place a moment is written. It sits inline inside the studio flow, so
   material is added on the spot instead of on a page of its own. */
const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [], created: [moment: LifeMoment] }>()

const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const toast = useToast()

const saving = ref(false)
const field = ref<HTMLTextAreaElement | null>(null)
const form = reactive({
  content: '',
  category: 'Lesson',
  happened_at: new Date().toISOString().slice(0, 10),
  upcoming_at: ''
})
const categories = ['Win', 'Failure', 'Lesson', 'Launch', 'Idea', 'Meeting', 'Milestone', 'Opinion', 'Upcoming event', 'Other']

watch(() => props.open, async (open) => {
  if (!open) return
  await nextTick()
  field.value?.focus()
})

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
  <form v-if="open" class="rounded-[14px] border border-[var(--accent)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(23,23,26,.04)]" @submit.prevent="submit">
    <p class="text-xs text-[var(--muted)]">{{ $t('moments.composerPrompt') }}</p>
    <textarea
      ref="field"
      v-model="form.content"
      required
      class="mt-3 min-h-28 w-full rounded-[12px] border border-[var(--line)] bg-[var(--paper)] p-3.5 text-[15px] leading-6 outline-none transition focus:border-[var(--muted)]"
      :placeholder="$t('moments.composerPlaceholder')"
    />
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
      <label class="text-xs text-[var(--muted)]">{{ $t('moments.category') }}
        <select v-model="form.category" class="mt-1.5 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 py-2.5 text-sm outline-none">
          <option v-for="category in categories" :key="category" :value="category">{{ $t('moments.categories.' + category) }}</option>
        </select>
      </label>
      <label class="text-xs text-[var(--muted)]">{{ $t('moments.date') }}
        <input v-model="form.happened_at" type="date" class="mt-1.5 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 py-2.5 text-sm outline-none">
      </label>
    </div>
    <div class="mt-4 flex items-center justify-end gap-4">
      <button type="button" class="text-xs text-[var(--faint)] transition hover:text-[var(--ink)]" :disabled="saving" @click="emit('close')">{{ $t('common.cancel') }}</button>
      <button type="submit" class="b-btn-red inline-flex h-10 items-center rounded-full px-5 text-[14px] font-medium transition disabled:cursor-wait disabled:opacity-70" :disabled="saving">
        {{ saving ? $t('moments.finding') : $t('moments.saveMoment') }}
      </button>
    </div>
  </form>
</template>
