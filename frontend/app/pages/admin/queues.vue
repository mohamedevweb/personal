<script setup lang="ts">
interface QueueSummary {
  queue: string
  ready: number
  delayed: number
  reserved: number
  failed: number
  oldest_pending_at: string | null
}

interface QueueJob {
  id: number
  queue: string
  state: 'ready' | 'delayed' | 'reserved'
  job: string
  attempts: number
  created_at: string
}

interface QueueStatus {
  generated_at: string
  queues: QueueSummary[]
  jobs: QueueJob[]
}

const { apiFetch } = usePersonalApi()
const { locale, t } = useI18n()
const status = ref<QueueStatus | null>(null)
const loading = ref(true)
const refreshing = ref(false)
const error = ref('')
let refreshTimer: number | undefined

const totalJobs = computed(() => status.value?.queues.reduce((total, queue) => total + queue.ready + queue.delayed + queue.reserved, 0) ?? 0)
const totalFailed = computed(() => status.value?.queues.reduce((total, queue) => total + queue.failed, 0) ?? 0)

function dateLabel(value: string | null): string {
  if (!value) return t('queues.none')

  return new Date(value).toLocaleString(locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short'
  })
}

function stateLabel(state: QueueJob['state']): string {
  return t(`queues.states.${state}`)
}

async function loadQueues(showLoading = false) {
  if (showLoading) loading.value = true
  else refreshing.value = true

  try {
    status.value = await apiFetch<QueueStatus>('/api/admin/queues?limit=100')
    error.value = ''
  } catch (exception: unknown) {
    error.value = apiErrorMessage(exception, t('queues.loadError'))
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

onMounted(async () => {
  await loadQueues(true)
  refreshTimer = window.setInterval(() => { void loadQueues() }, 5000)
})

onBeforeUnmount(() => {
  if (refreshTimer) window.clearInterval(refreshTimer)
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        <p class="b-eyebrow">{{ $t('queues.eyebrow') }}</p>
        <h1 class="mt-2 font-serif text-[34px] tracking-[-.03em]">{{ $t('queues.title') }}</h1>
        <p class="mt-2 max-w-[620px] text-sm leading-6 text-[var(--muted)]">{{ $t('queues.copy') }}</p>
      </div>
      <div class="flex items-center gap-3 text-xs text-[var(--faint)]">
        <span v-if="status">{{ $t('queues.updated', { date: dateLabel(status.generated_at) }) }}</span>
        <span v-if="refreshing" class="h-2 w-2 animate-pulse rounded-full bg-[var(--accent)]" aria-hidden="true" />
        <button type="button" class="b-focus inline-flex h-9 items-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-3 font-medium text-[var(--ink)] transition hover:border-[var(--accent)] disabled:opacity-60" :disabled="refreshing" @click="loadQueues()">
          <AppIcon name="refresh" :size="15" />
          {{ $t('queues.refresh') }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="mt-8 grid gap-4 sm:grid-cols-3">
      <div v-for="index in 3" :key="index" class="h-28 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
    </div>

    <div v-else-if="error" role="alert" class="mt-8 rounded-[18px] border border-[var(--danger-line)] bg-[var(--danger-soft)] px-6 py-5 text-sm text-[var(--danger)]">
      {{ error }}
    </div>

    <template v-else-if="status">
      <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <article class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)]">
          <p class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('queues.metrics.active') }}</p>
          <p class="mt-3 font-serif text-[34px] tracking-[-.03em]">{{ totalJobs }}</p>
        </article>
        <article class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)]">
          <p class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('queues.metrics.queues') }}</p>
          <p class="mt-3 font-serif text-[34px] tracking-[-.03em]">{{ status.queues.length }}</p>
        </article>
        <article class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)]">
          <p class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('queues.metrics.failed') }}</p>
          <p class="mt-3 font-serif text-[34px] tracking-[-.03em]" :class="totalFailed ? 'text-[var(--danger)]' : ''">{{ totalFailed }}</p>
        </article>
      </div>

      <section class="mt-5 overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)]">
        <div class="border-b border-[var(--line)] px-5 py-4 md:px-6">
          <h2 class="font-serif text-[24px] tracking-[-.02em]">{{ $t('queues.overview') }}</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[680px] text-left text-sm">
            <thead class="bg-[var(--paper)] text-[10px] font-semibold uppercase tracking-[.14em] text-[var(--faint)]">
              <tr>
                <th class="px-5 py-3 font-semibold md:px-6">{{ $t('queues.columns.queue') }}</th>
                <th class="px-3 py-3 font-semibold">{{ $t('queues.columns.ready') }}</th>
                <th class="px-3 py-3 font-semibold">{{ $t('queues.columns.delayed') }}</th>
                <th class="px-3 py-3 font-semibold">{{ $t('queues.columns.reserved') }}</th>
                <th class="px-3 py-3 font-semibold">{{ $t('queues.columns.failed') }}</th>
                <th class="px-5 py-3 font-semibold md:px-6">{{ $t('queues.columns.oldest') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--line-soft)]">
              <tr v-for="queue in status.queues" :key="queue.queue">
                <td class="px-5 py-4 font-medium md:px-6">{{ queue.queue }}</td>
                <td class="px-3 py-4 text-[var(--muted)]">{{ queue.ready }}</td>
                <td class="px-3 py-4 text-[var(--muted)]">{{ queue.delayed }}</td>
                <td class="px-3 py-4" :class="queue.reserved ? 'font-medium text-[var(--accent)]' : 'text-[var(--muted)]'">{{ queue.reserved }}</td>
                <td class="px-3 py-4" :class="queue.failed ? 'font-medium text-[var(--danger)]' : 'text-[var(--muted)]'">{{ queue.failed }}</td>
                <td class="px-5 py-4 text-xs text-[var(--muted)] md:px-6">{{ dateLabel(queue.oldest_pending_at) }}</td>
              </tr>
              <tr v-if="!status.queues.length">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-[var(--muted)]">{{ $t('queues.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="mt-5 overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)]">
        <div class="border-b border-[var(--line)] px-5 py-4 md:px-6">
          <h2 class="font-serif text-[24px] tracking-[-.02em]">{{ $t('queues.jobsTitle') }}</h2>
          <p class="mt-1 text-xs text-[var(--muted)]">{{ $t('queues.jobsCopy') }}</p>
        </div>
        <div class="divide-y divide-[var(--line-soft)]">
          <div v-for="job in status.jobs" :key="job.id" class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium">{{ job.job }}</p>
              <p class="mt-1 text-xs text-[var(--faint)]">{{ job.queue }} · #{{ job.id }} · {{ $t('queues.attempts', { count: job.attempts }) }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-3 text-xs text-[var(--muted)]">
              <span class="rounded-full border border-[var(--line)] bg-[var(--paper)] px-2.5 py-1 font-medium">{{ stateLabel(job.state) }}</span>
              <span>{{ dateLabel(job.created_at) }}</span>
            </div>
          </div>
          <p v-if="!status.jobs.length" class="px-5 py-10 text-center text-sm text-[var(--muted)]">{{ $t('queues.emptyJobs') }}</p>
        </div>
      </section>
    </template>
  </main>
</template>
