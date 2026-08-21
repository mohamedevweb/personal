<script setup lang="ts">
import type { CreatorInspiration, CreatorInspirationResponse, InstagramSyncStatus } from '~/types/instagram'

definePageMeta({ layout: false })

const route = useRoute()
const { t } = useI18n()
const { status, loading, error, connect, loadStatus, startPolling } = useInstagram()
const { apiFetch } = usePersonalApi()
const toast = useToast()
const inspirationData = ref<CreatorInspirationResponse | null>(null)
const inspirationLoading = ref(false)
const searchQuery = ref('')
const searchResults = ref<CreatorInspiration[]>([])
const searching = ref(false)
const saving = ref(false)
const selected = ref<CreatorInspiration[]>([])
const inspirationsLoaded = ref(false)

const stages = computed<{ key: InstagramSyncStatus; label: string }[]>(() => [
  { key: 'connecting', label: t('onboarding.stages.connecting') },
  { key: 'importing_content', label: t('onboarding.stages.importing_content') },
  { key: 'understanding_niche', label: t('onboarding.stages.understanding_niche') },
  { key: 'learning_style', label: t('onboarding.stages.learning_style') },
  { key: 'finding_patterns', label: t('onboarding.stages.finding_patterns') }
])

const activeStage = computed(() => {
  if (status.value.account?.sync_status === 'completed') return stages.value.length
  return Math.max(stages.value.findIndex(stage => stage.key === status.value.account?.sync_status), 0)
})

const callbackError = computed(() => route.query.instagram === 'error'
  ? String(route.query.message || t('onboarding.cancelledError'))
  : null)

const connectionError = computed(() => callbackError.value || status.value.account?.sync_error || error.value)
watch(connectionError, (message) => {
  if (message) toast.error(message)
}, { immediate: true })

const onboarded = useState('personal-onboarded', () => false)

const availableCreators = computed(() => {
  const creators = [...selected.value, ...(inspirationData.value?.suggestions || []), ...searchResults.value]
  return Array.from(new Map(creators.map(creator => [creator.username.toLowerCase(), creator])).values())
})

const canContinue = computed(() => selected.value.length >= 3 && selected.value.length <= 5)

function isSelected(username: string) {
  return selected.value.some(creator => creator.username.toLowerCase() === username.toLowerCase())
}

function toggleCreator(creator: CreatorInspiration) {
  if (isSelected(creator.username)) {
    selected.value = selected.value.filter(item => item.username.toLowerCase() !== creator.username.toLowerCase())
    return
  }

  if (selected.value.length >= 5) {
    toast.error(t('onboarding.inspirations.maximumError'))
    return
  }

  selected.value = [...selected.value, { ...creator, is_selected: true }]
}

function initials(creator: CreatorInspiration) {
  return (creator.display_name || creator.username).slice(0, 2).toUpperCase()
}

async function loadInspirations() {
  if (inspirationsLoaded.value || inspirationLoading.value) return
  inspirationLoading.value = true

  try {
    inspirationData.value = await apiFetch<CreatorInspirationResponse>('/api/creator-inspirations')
    selected.value = inspirationData.value.selected
    inspirationsLoaded.value = true
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('onboarding.inspirations.loadError')))
  } finally {
    inspirationLoading.value = false
  }
}

async function searchCreators() {
  const query = searchQuery.value.trim()
  if (query.length < 2 || searching.value) return
  searching.value = true

  try {
    const response = await apiFetch<{ items: CreatorInspiration[] }>(`/api/creator-inspirations/search?q=${encodeURIComponent(query)}`)
    searchResults.value = response.items
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('onboarding.inspirations.searchError')))
  } finally {
    searching.value = false
  }
}

async function saveInspirations() {
  if (!canContinue.value || saving.value) return
  saving.value = true

  try {
    await apiFetch('/api/creator-inspirations', {
      method: 'PUT',
      body: { handles: selected.value.map(creator => creator.username) }
    })
    onboarded.value = true
    await navigateTo('/feed')
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('onboarding.inspirations.saveError')))
  } finally {
    saving.value = false
  }
}

watch(() => status.value.account?.sync_status, (syncStatus) => {
  if (status.value.connected && syncStatus === 'completed') void loadInspirations()
})

// Let the user into the app without connecting Instagram. The cookie makes the
// choice survive reloads so the auth gate stops sending them back here.
const skipped = useCookie<boolean>('personal-onboarding-skipped', { maxAge: 60 * 60 * 24 * 365 })
function skipConnection() {
  skipped.value = true
  onboarded.value = true
  navigateTo('/feed')
}

onMounted(async () => {
  await loadStatus()
  if (status.value.connected && status.value.account?.sync_status === 'completed') {
    await loadInspirations()
  }
  if (route.query.instagram === 'connected' || (status.value.connected && status.value.account?.sync_status !== 'completed')) {
    startPolling()
  }
})
</script>

<template>
  <main class="min-h-screen overflow-x-hidden bg-[var(--paper)] text-[var(--ink)]">
    <header class="flex h-20 items-center justify-between px-6 md:px-10">
      <NuxtLink to="/feed" class="b-focus w-fit">
        <PersonalLogo :size="22" />
      </NuxtLink>
      <div class="flex items-center gap-4">
        <span class="hidden text-xs tracking-wide text-[var(--faint)] sm:inline">{{ $t('brand.privateIntelligence') }}</span>
        <LanguageSwitcher />
      </div>
    </header>

    <section class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-6xl items-center gap-14 px-6 py-14 md:grid-cols-[1fr_0.88fr] md:px-10 md:py-20">
      <div class="max-w-xl animate-rise">
        <div class="mb-8 flex items-center gap-2 text-xs font-medium uppercase tracking-[0.16em] text-[var(--faint)]">
          <span class="h-1.5 w-1.5 rounded-full bg-[var(--accent)]" />
          {{ $t('onboarding.eyebrow') }}
        </div>

        <template v-if="!status.connected">
          <h1 class="font-serif text-5xl leading-[1.02] tracking-[-0.045em] md:text-7xl" v-html="$t('onboarding.connectTitle')" />
          <p class="mt-7 max-w-lg text-[17px] leading-7 text-[var(--muted)]">
            {{ $t('onboarding.connectCopy') }}
          </p>

          <button
            class="mt-10 inline-flex h-[54px] items-center gap-3 rounded-full b-btn-red px-7 text-[15px] font-medium transition hover:-translate-y-0.5 disabled:cursor-wait disabled:opacity-60"
            :disabled="loading"
            @click="connect"
          >
            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
              <rect x="3" y="3" width="18" height="18" rx="5" />
              <circle cx="12" cy="12" r="4" />
              <circle cx="17.5" cy="6.5" r=".8" class="fill-current stroke-0" />
            </svg>
            {{ loading ? $t('onboarding.preparing') : $t('onboarding.continueWithInstagram') }}
            <span aria-hidden="true">↗</span>
          </button>

          <p class="mt-5 flex items-center gap-2 text-xs text-[var(--faint)]">
            <svg viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current" stroke-width="1.5"><rect x="4" y="8" width="12" height="9" rx="2"/><path d="M7 8V6a3 3 0 016 0v2"/></svg>
            {{ $t('onboarding.tokenNote') }}
          </p>

          <button
            type="button"
            class="mt-8 text-sm text-[var(--faint)] underline decoration-[var(--line)] underline-offset-4 transition hover:text-[var(--ink)]"
            @click="skipConnection"
          >
            {{ $t('onboarding.skip') }} →
          </button>
        </template>

        <template v-else>
          <div class="mb-8 inline-flex items-center gap-3 rounded-full border border-[var(--line)] bg-[var(--surface)] py-2 pl-2 pr-4">
            <img v-if="status.account?.profile_picture_url" :src="status.account.profile_picture_url" alt="" class="h-8 w-8 rounded-full object-cover">
            <span v-else class="grid h-8 w-8 place-items-center rounded-full bg-[var(--paper)] text-xs">IG</span>
            <span class="text-sm font-medium">@{{ status.account?.username }} {{ $t('onboarding.connectedSuffix') }}</span>
            <span class="text-[var(--positive)]">✓</span>
          </div>

          <template v-if="status.account?.sync_status === 'completed'">
            <h1 class="font-serif text-4xl leading-[1.02] tracking-[-0.045em] md:text-6xl">
              {{ $t('onboarding.inspirations.title') }}
            </h1>
            <p class="mt-5 max-w-lg text-[16px] leading-7 text-[var(--muted)]">
              {{ $t('onboarding.inspirations.copy') }}
            </p>

            <div class="mt-7 flex items-center justify-between text-sm">
              <span class="font-medium">{{ $t('onboarding.inspirations.selected', { count: selected.length }) }}</span>
              <span class="text-[var(--faint)]">{{ $t('onboarding.inspirations.range') }}</span>
            </div>

            <form class="mt-4 flex gap-2" @submit.prevent="searchCreators">
              <label for="creator-search" class="sr-only">{{ $t('onboarding.inspirations.searchLabel') }}</label>
              <input
                id="creator-search"
                v-model="searchQuery"
                type="text"
                autocomplete="off"
                class="min-w-0 flex-1 rounded-full border border-[var(--line)] bg-[var(--surface)] px-5 py-3 text-sm outline-none transition focus:border-[var(--ink)]"
                :placeholder="$t('onboarding.inspirations.searchPlaceholder')"
              >
              <button
                type="submit"
                class="rounded-full border border-[var(--line)] bg-[var(--surface)] px-5 py-3 text-sm font-medium transition hover:bg-white disabled:cursor-wait disabled:opacity-50"
                :disabled="searching || searchQuery.trim().length < 2"
              >
                {{ searching ? $t('onboarding.inspirations.searching') : $t('onboarding.inspirations.search') }}
              </button>
            </form>

            <div v-if="inspirationLoading" class="mt-5 grid grid-cols-2 gap-3">
              <div v-for="i in 4" :key="i" class="h-16 animate-pulse rounded-[16px] bg-[var(--line-soft)]" />
            </div>
            <div v-else class="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
              <button
                v-for="creator in availableCreators"
                :key="creator.username"
                type="button"
                class="flex min-w-0 items-center gap-3 rounded-[16px] border bg-[var(--surface)] p-3 text-left transition hover:border-[var(--ink)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
                :class="isSelected(creator.username) ? 'border-[var(--accent)] ring-1 ring-[var(--accent)]' : 'border-[var(--line)]'"
                @click="toggleCreator(creator)"
              >
                <img v-if="creator.avatar_url" :src="creator.avatar_url" :alt="creator.display_name" class="h-10 w-10 shrink-0 rounded-full object-cover">
                <span v-else class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[var(--paper)] text-xs font-semibold">{{ initials(creator) }}</span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-medium">{{ creator.display_name }}</span>
                  <span class="block truncate text-xs text-[var(--faint)]">@{{ creator.username }}</span>
                </span>
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full border text-xs" :class="isSelected(creator.username) ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : 'border-[var(--line)] text-[var(--faint)]'">
                  {{ isSelected(creator.username) ? '✓' : '+' }}
                </span>
              </button>
            </div>

            <button
              type="button"
              class="mt-7 inline-flex h-[52px] items-center rounded-full b-btn-red px-7 text-[15px] font-medium transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="!canContinue || saving"
              @click="saveInspirations"
            >
              {{ saving ? $t('onboarding.inspirations.saving') : $t('onboarding.inspirations.continue') }}&nbsp; →
            </button>
          </template>

          <template v-else>
            <h1 class="font-serif text-5xl leading-[1.02] tracking-[-0.045em] md:text-7xl">
              {{ $t('onboarding.understandingTitle') }}
            </h1>
            <p class="mt-7 max-w-lg text-[17px] leading-7 text-[var(--muted)]">
              {{ $t('onboarding.importingCopy') }}
            </p>
          </template>

          <button
            v-if="status.account?.sync_status === 'failed'"
            class="mt-8 inline-flex h-11 items-center rounded-full border border-[var(--line)] bg-[var(--surface)] px-6 text-[14px] font-medium transition hover:bg-[var(--paper)]"
            :disabled="loading"
            @click="connect"
          >
            {{ $t('onboarding.reconnect') }}
          </button>
        </template>

      </div>

      <aside class="b-night relative min-h-[430px] overflow-hidden rounded-[24px] p-7 text-white md:p-10">
        <div class="absolute right-8 top-8 flex gap-1.5">
          <span v-for="i in 3" :key="i" class="h-1.5 w-1.5 rounded-full bg-white/25" />
        </div>

        <p class="text-[10px] font-semibold uppercase tracking-[.22em] text-[var(--gold)]">{{ $t('onboarding.profileLive') }}</p>
        <div v-if="status.connected" class="mt-12 space-y-1">
          <div
            v-for="(stage, index) in stages"
            :key="stage.key"
            class="flex items-center gap-4 rounded-2xl px-3 py-3.5 transition-all duration-500"
            :class="index === activeStage ? 'panel-night' : ''"
          >
            <span class="grid h-7 w-7 place-items-center rounded-full border text-xs transition-all"
              :class="index < activeStage ? 'border-[var(--gold)] bg-[var(--gold)] text-[var(--night)]' : index === activeStage ? 'animate-breathe border-[var(--gold)] bg-[var(--gold)] text-[var(--night)]' : 'border-white/20 text-white/40'">
              {{ index < activeStage ? '✓' : index + 1 }}
            </span>
            <span class="text-sm" :class="index <= activeStage ? 'text-white' : 'text-white/40'">{{ stage.label }}</span>
          </div>
        </div>

        <div v-else class="mt-14 space-y-7">
          <div class="h-3 w-28 rounded-full bg-white/15" />
          <div class="space-y-3">
            <div class="h-10 w-4/5 rounded-xl bg-white/10" />
            <div class="h-10 w-3/5 rounded-xl bg-white/10" />
          </div>
          <div class="grid grid-cols-3 gap-3 pt-4">
            <div v-for="i in 3" :key="i" class="h-24 rounded-[14px] border border-white/10 bg-white/5" />
          </div>
          <p class="pt-3 font-serif text-xl leading-7 text-white/45">{{ $t('onboarding.placeholderQuote') }}</p>
        </div>
      </aside>
    </section>
  </main>
</template>
