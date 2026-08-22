<script setup lang="ts">
import type { RemixSummary } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { locale, t } = useI18n()
const remixes = ref<RemixSummary[]>([])
const loading = ref(true)
const error = ref('')

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

onMounted(async () => {
  try {
    const response = await apiFetch<{ remixes: RemixSummary[] }>('/api/remixes')
    remixes.value = response.remixes
  } catch (exception: unknown) {
    error.value = apiErrorMessage(exception, t('drafts.loadError'))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <header class="flex flex-col gap-4 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-start gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[12px] bg-[var(--accent-soft)] text-[var(--accent-ink)]">
          <AppIcon name="draft" :size="19" />
        </span>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t('drafts.eyebrow') }}</p>
          <p class="mt-2 max-w-xl text-[15px] leading-6 text-[var(--muted)]">{{ $t('drafts.subtitle') }}</p>
        </div>
      </div>
      <NuxtLink to="/create" class="inline-flex h-11 w-fit shrink-0 items-center justify-center gap-2 rounded-full b-btn-red px-5 text-[14px] font-medium transition">
        <AppIcon name="plus" :size="17" />{{ $t('drafts.newDraft') }}
      </NuxtLink>
    </header>

    <div v-if="loading" class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <div v-for="index in 3" :key="index" class="h-56 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
    </div>

    <div v-else-if="error" role="alert" class="mt-5 rounded-[18px] border border-[var(--danger-line)] bg-[var(--danger-soft)] px-6 py-5 text-sm text-[var(--danger)]">
      {{ error }}
    </div>

    <div v-else-if="remixes.length" class="mt-5 grid auto-rows-fr gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <NuxtLink
        v-for="remix in remixes"
        :key="remix.id"
        :to="`/remix/${remix.id}`"
        class="group flex min-h-56 flex-col rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)] transition hover:-translate-y-0.5 hover:shadow-[0_12px_30px_rgba(23,23,26,.07)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)]"
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
          <span class="inline-flex shrink-0 items-center gap-1 text-[12px] font-medium text-[var(--ink)]">
            {{ $t('drafts.open') }}<AppIcon name="arrow" :size="14" class="transition group-hover:translate-x-0.5" />
          </span>
        </div>
      </NuxtLink>
    </div>

    <div v-else class="mt-5 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-16 text-center">
      <p class="font-serif text-[26px] tracking-[-.02em]">{{ $t('drafts.emptyTitle') }}</p>
      <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">{{ $t('drafts.emptyCopy') }}</p>
      <NuxtLink to="/create" class="mt-6 inline-flex h-11 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition">{{ $t('drafts.createFirst') }}</NuxtLink>
    </div>
  </main>
</template>
