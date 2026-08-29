<script setup lang="ts">
import type { RemixSummary } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { locale, t } = useI18n()
const toast = useToast()
const remixes = ref<RemixSummary[]>([])
const loading = ref(true)
const error = ref('')
/** A draft holds the creator's own words, so the trash asks once before it fires. */
const confirmingDeleteId = ref<number | null>(null)
const deletingId = ref<number | null>(null)
let refreshTimer: ReturnType<typeof setTimeout> | undefined
let confirmTimer: ReturnType<typeof setTimeout> | undefined

function preview(remix: RemixSummary): string {
  if (remix.status === 'generating') return t('drafts.preparing')
  if (remix.status === 'failed') return t('drafts.failedPreview')

  const content = remix.generated_content
  if (remix.format === 'carousel') return content.slides?.[0]?.text || content.your_version || remix.source_content.hook
  if (remix.format === 'reel') return content.hook || content.script || content.your_version || remix.source_content.hook
  return content.caption || content.your_version || remix.source_content.hook
}

function statusLabel(status: RemixSummary['status']): string {
  return t(`drafts.status.${status}`)
}

function formattedDate(value: string): string {
  return new Date(value).toLocaleDateString(locale.value, {
    day: 'numeric',
    month: 'short',
    year: new Date(value).getFullYear() === new Date().getFullYear() ? undefined : 'numeric'
  })
}

async function loadDrafts(showError = true) {
  clearTimeout(refreshTimer)

  try {
    const response = await apiFetch<{ remixes: RemixSummary[] }>('/api/remixes')
    remixes.value = response.remixes
    error.value = ''
    if (remixes.value.some(remix => remix.status === 'generating')) {
      refreshTimer = setTimeout(() => loadDrafts(false), 1200)
    }
  } catch (exception: unknown) {
    if (showError) error.value = apiErrorMessage(exception, t('drafts.loadError'))
    else refreshTimer = setTimeout(() => loadDrafts(false), 2500)
  } finally {
    loading.value = false
  }
}

/** First click arms the delete, second one runs it. It disarms on its own so a
    stray click never sits there waiting to wipe a draft. */
function askDelete(remix: RemixSummary) {
  if (confirmingDeleteId.value !== remix.id) {
    confirmingDeleteId.value = remix.id
    clearTimeout(confirmTimer)
    confirmTimer = setTimeout(() => { confirmingDeleteId.value = null }, 4000)
    return
  }
  clearTimeout(confirmTimer)
  confirmingDeleteId.value = null
  void deleteDraft(remix)
}

async function deleteDraft(remix: RemixSummary) {
  if (deletingId.value) return
  deletingId.value = remix.id
  try {
    await apiFetch(`/api/remixes/${remix.id}`, { method: 'DELETE' })
    remixes.value = remixes.value.filter(item => item.id !== remix.id)
    toast.success(t('drafts.deleted'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('drafts.deleteError')))
  } finally {
    deletingId.value = null
  }
}

onMounted(loadDrafts)
onBeforeUnmount(() => {
  clearTimeout(refreshTimer)
  clearTimeout(confirmTimer)
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <div v-if="loading" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <div v-for="index in 3" :key="index" class="h-56 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
    </div>

    <div v-else-if="error" role="alert" class="rounded-[18px] border border-[var(--danger-line)] bg-[var(--danger-soft)] px-6 py-5 text-sm text-[var(--danger)]">
      {{ error }}
    </div>

    <div v-else-if="remixes.length" class="grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <!-- The card holds two actions now, so opening the draft is a link stretched
           over it and the trash stays a button of its own above that surface. -->
      <article
        v-for="remix in remixes"
        :key="remix.id"
        class="group relative flex min-h-56 min-w-0 flex-col rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)] transition focus-within:ring-2 focus-within:ring-[var(--accent)]"
        :class="remix.status !== 'generating' && 'hover:-translate-y-0.5 hover:shadow-[0_12px_30px_rgba(23,23,26,.07)]'"
      >
        <div class="flex items-center justify-between gap-3">
          <span class="inline-flex items-center gap-2 text-[12px] font-medium text-[var(--muted)]">
            <AppIcon :name="remix.format === 'caption' ? 'text' : remix.format" :size="16" />
            {{ $t(`remix.formats.${remix.format}`) }}
          </span>
          <span
            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10.5px] font-medium"
            :class="remix.status === 'ready'
              ? 'border-[var(--positive-line)] bg-[var(--positive-soft)] text-[var(--positive)]'
              : remix.status === 'failed'
                ? 'border-[var(--danger-line)] bg-[var(--danger-soft)] text-[var(--danger)]'
                : 'border-[var(--line)] bg-[var(--paper)] text-[var(--muted)]'"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-current" />{{ statusLabel(remix.status) }}
          </span>
        </div>

        <p class="mt-5 line-clamp-4 font-serif text-[23px] leading-[1.25] tracking-[-.018em]">{{ preview(remix) }}</p>

        <div class="mt-auto flex items-end justify-between gap-4 border-t border-[var(--line-soft)] pt-4">
          <div class="min-w-0">
            <p class="truncate text-[12px] text-[var(--muted)]">@{{ remix.source_content.creator.username }}</p>
            <p class="mt-1 text-[10.5px] text-[var(--faint)]">{{ $t('drafts.updated', { date: formattedDate(remix.updated_at) }) }}</p>
          </div>
          <div v-if="remix.status !== 'generating'" class="flex shrink-0 items-center gap-2">
            <button
              type="button"
              class="relative z-10 inline-flex h-9 min-w-9 items-center justify-center gap-1.5 rounded-full px-2 text-[12px] font-medium text-[var(--faint)] transition after:absolute after:-inset-1.5 after:content-[''] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)] disabled:opacity-60"
              :class="confirmingDeleteId === remix.id && 'bg-[var(--danger-soft)] text-[var(--danger)]'"
              :aria-label="confirmingDeleteId === remix.id ? $t('drafts.deleteConfirm') : $t('drafts.delete')"
              :disabled="deletingId === remix.id"
              @click="askDelete(remix)"
            >
              <AppIcon name="trash" :size="15" />
              <span v-if="confirmingDeleteId === remix.id">{{ $t('drafts.deleteConfirm') }}</span>
            </button>
            <NuxtLink
              :to="`/remix/${remix.id}`"
              class="inline-flex items-center gap-1 rounded-full text-[12px] font-medium text-[var(--ink)] after:absolute after:inset-0 after:rounded-[18px] after:content-[''] focus-visible:outline-none"
            >
              {{ $t('drafts.open') }}<AppIcon name="arrow" :size="14" class="transition group-hover:translate-x-0.5" />
            </NuxtLink>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <p class="font-serif text-[26px] tracking-[-.02em]">{{ $t('drafts.emptyTitle') }}</p>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('drafts.emptyCopy') }}</p>
    </div>
  </main>
</template>
