<script setup lang="ts">
import type { CreatorInspiration, CreatorInspirationResponse, InstagramSyncStatus } from '~/types/instagram'

definePageMeta({ layout: false })

const route = useRoute()
const { t, locale } = useI18n()
const { status, loading, error, connect, loadStatus, startPolling } = useInstagram()
const { apiFetch } = usePersonalApi()
const toast = useToast()
const inspirationData = ref<CreatorInspirationResponse | null>(null)
const inspirationLoading = ref(false)
const searchResults = ref<CreatorInspiration[]>([])
const searching = ref(false)
const saving = ref(false)
const selected = ref<CreatorInspiration[]>([])
const inspirationsLoaded = ref(false)
const handleInput = ref('')

// The card on the right stands in for the thing being connected: an Instagram
// profile with no face on it, because the only part Personal reads is what the
// posts did. The figures illustrate the shape of a profile — the card is
// labelled an aperçu, never presented as the visitor's own account.
const PREVIEW_TILES = [
  { id: 1, kind: 'carousel', angle: 148, light: .085 },
  { id: 2, kind: null, angle: 32, light: .05 },
  { id: 3, kind: 'reel', outlier: true, angle: 200, light: .07 },
  { id: 4, kind: null, angle: 210, light: .065 },
  { id: 5, kind: 'reel', angle: 118, light: .045 },
  { id: 6, kind: 'carousel', angle: 60, light: .075 }
] as const

// Each tile gets its own wash so the grid reads as six thumbnails rather than
// six empty boxes. The angle and the amount of light are all that separate
// them: enough at this size, and abstract enough that the card never pretends
// to be showing anyone's actual photos.
function tileWash(tile: (typeof PREVIEW_TILES)[number]) {
  return `linear-gradient(${tile.angle}deg, rgba(255, 255, 255, ${tile.light}) 0%, rgba(255, 255, 255, .012) 70%)`
}

const PREVIEW_RATIO = 6.2

const previewStats = computed(() => {
  const format = new Intl.NumberFormat(locale.value)
  return [
    { key: 'posts', value: format.format(128) },
    { key: 'followers', value: format.format(4820) },
    { key: 'following', value: format.format(310) }
  ]
})

const previewRatio = computed(() => new Intl.NumberFormat(locale.value, {
  minimumFractionDigits: 1,
  maximumFractionDigits: 1
}).format(PREVIEW_RATIO))

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

const minimum = computed(() => inspirationData.value?.minimum ?? 3)
const maximum = computed(() => inspirationData.value?.maximum ?? 6)

// Suggestions and search hits already picked live in the favorites card, so the
// browsable grid only offers what is still addable.
const availableCreators = computed(() => {
  const creators = [...searchResults.value, ...(inspirationData.value?.suggestions || [])]
  return Array.from(new Map(creators.map(creator => [creator.username.toLowerCase(), creator])).values())
    .filter(creator => !isSelected(creator.username))
})

const canContinue = computed(() => selected.value.length >= minimum.value && selected.value.length <= maximum.value)
const parsedHandle = computed(() => parseHandle(handleInput.value))

function isSelected(username: string) {
  return selected.value.some(creator => creator.username.toLowerCase() === username.toLowerCase())
}

// Accepts a bare handle, an @handle, or an instagram.com profile link, mirroring
// what the API resolves when the selection is saved.
function parseHandle(input: string): string | null {
  let candidate = input.trim()

  if (/^https?:\/\//i.test(candidate)) {
    try {
      const url = new URL(candidate)
      if (!/^(www\.)?instagram\.com$/i.test(url.hostname)) return null
      candidate = url.pathname.split('/').filter(Boolean)[0] || ''
    } catch {
      return null
    }
  }

  candidate = candidate.replace(/^@/, '')
  return /^[A-Za-z0-9._]{1,30}$/.test(candidate) ? candidate : null
}

function addCreator(creator: CreatorInspiration) {
  if (isSelected(creator.username)) return

  if (selected.value.length >= maximum.value) {
    toast.error(t('onboarding.inspirations.maximumError', { count: maximum.value }))
    return
  }

  selected.value = [...selected.value, { ...creator, is_selected: true }]
}

function removeCreator(username: string) {
  selected.value = selected.value.filter(item => item.username.toLowerCase() !== username.toLowerCase())
}

function toggleCreator(creator: CreatorInspiration) {
  if (isSelected(creator.username)) {
    removeCreator(creator.username)
    return
  }

  addCreator(creator)
}

function addHandle() {
  const handle = parsedHandle.value
  if (!handle) {
    if (handleInput.value.trim()) toast.error(t('onboarding.inspirations.handleError'))
    return
  }

  if (isSelected(handle)) {
    handleInput.value = ''
    return
  }

  const known = availableCreators.value.find(creator => creator.username.toLowerCase() === handle.toLowerCase())
  addCreator(known || {
    username: handle,
    display_name: handle,
    avatar_url: null,
    followers: 0,
    niche: null,
    is_selected: true,
    is_measured: false
  })
  handleInput.value = ''
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
  const query = handleInput.value.trim()
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

            <section class="mt-7 rounded-[24px] border border-[var(--line)] bg-[var(--surface)] p-5 md:p-6">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">{{ $t('onboarding.inspirations.cardTitle') }}</h2>
                <span class="text-xs text-[var(--faint)]">
                  {{ $t('onboarding.inspirations.counter', { count: selected.length, max: maximum }) }}
                </span>
              </div>
              <p class="mt-1.5 text-[13px] leading-5 text-[var(--muted)]">
                {{ $t('onboarding.inspirations.cardCopy', { min: minimum, max: maximum }) }}
              </p>

              <form class="mt-4 flex flex-wrap gap-2" @submit.prevent="addHandle">
                <label for="creator-search" class="sr-only">{{ $t('onboarding.inspirations.searchLabel') }}</label>
                <input
                  id="creator-search"
                  v-model="handleInput"
                  type="text"
                  autocomplete="off"
                  spellcheck="false"
                  class="min-w-0 flex-1 rounded-full border border-[var(--line)] bg-[var(--paper)] px-5 py-3 text-sm outline-none transition focus:border-[var(--ink)]"
                  :placeholder="$t('onboarding.inspirations.searchPlaceholder')"
                >
                <button
                  type="button"
                  class="rounded-full border border-[var(--line)] bg-[var(--paper)] px-4 py-3 text-sm font-medium transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="searching || handleInput.trim().length < 2"
                  @click="searchCreators"
                >
                  {{ searching ? $t('onboarding.inspirations.searching') : $t('onboarding.inspirations.search') }}
                </button>
                <button
                  type="submit"
                  class="rounded-full border border-[var(--ink)] bg-[var(--ink)] px-4 py-3 text-sm font-medium text-[var(--paper)] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-40"
                  :disabled="!parsedHandle || selected.length >= maximum"
                >
                  {{ $t('onboarding.inspirations.add') }}
                </button>
              </form>

              <ul v-if="selected.length" class="mt-4 flex flex-wrap gap-2">
                <li
                  v-for="creator in selected"
                  :key="creator.username"
                  class="flex max-w-full items-center gap-2 rounded-full border border-[var(--accent)] bg-[var(--paper)] py-1 pl-1 pr-2 text-sm"
                >
                  <img v-if="creator.avatar_url" :src="creator.avatar_url" :alt="creator.display_name" class="h-7 w-7 shrink-0 rounded-full object-cover">
                  <span v-else class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[var(--surface)] text-[10px] font-semibold">{{ initials(creator) }}</span>
                  <span class="truncate">@{{ creator.username }}</span>
                  <button
                    type="button"
                    class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-[var(--faint)] transition hover:bg-[var(--surface)] hover:text-[var(--ink)]"
                    :aria-label="$t('onboarding.inspirations.remove', { handle: creator.username })"
                    @click="removeCreator(creator.username)"
                  >
                    ×
                  </button>
                </li>
              </ul>
              <p v-else class="mt-4 text-[13px] text-[var(--faint)]">
                {{ $t('onboarding.inspirations.empty') }}
              </p>

              <p class="mt-5 text-xs font-medium uppercase tracking-[0.14em] text-[var(--faint)]">
                {{ searchResults.length ? $t('onboarding.inspirations.resultsLabel') : $t('onboarding.inspirations.suggestionsLabel') }}
              </p>

              <div v-if="inspirationLoading" class="mt-3 grid grid-cols-2 gap-2">
                <div v-for="i in 4" :key="i" class="h-16 animate-pulse rounded-[16px] bg-[var(--line-soft)]" />
              </div>
              <div v-else-if="availableCreators.length" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                  v-for="creator in availableCreators"
                  :key="creator.username"
                  type="button"
                  class="flex min-w-0 items-center gap-3 rounded-[16px] border border-[var(--line)] bg-[var(--paper)] p-3 text-left transition hover:border-[var(--ink)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)] disabled:cursor-not-allowed disabled:opacity-40"
                  :disabled="selected.length >= maximum"
                  @click="toggleCreator(creator)"
                >
                  <img v-if="creator.avatar_url" :src="creator.avatar_url" :alt="creator.display_name" class="h-10 w-10 shrink-0 rounded-full object-cover">
                  <span v-else class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[var(--surface)] text-xs font-semibold">{{ initials(creator) }}</span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium">{{ creator.display_name }}</span>
                    <span class="block truncate text-xs text-[var(--faint)]">@{{ creator.username }}</span>
                  </span>
                  <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full border border-[var(--line)] text-xs text-[var(--faint)]">+</span>
                </button>
              </div>
              <p v-else class="mt-3 text-[13px] text-[var(--faint)]">
                {{ $t('onboarding.inspirations.noResults') }}
              </p>
            </section>

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

        <p class="text-[10px] font-semibold uppercase tracking-[.22em] text-[var(--b-red-lit)]">
          {{ status.connected ? $t('onboarding.profileLive') : $t('onboarding.preview.label') }}
        </p>

        <div v-if="status.connected" class="mt-12 space-y-1">
          <div
            v-for="(stage, index) in stages"
            :key="stage.key"
            class="flex items-center gap-4 rounded-2xl px-3 py-3.5 transition-all duration-500"
            :class="index === activeStage ? 'panel-night' : ''"
          >
            <span class="grid h-7 w-7 place-items-center rounded-full border text-xs transition-all"
              :class="index < activeStage ? 'border-[var(--b-red-lit)] bg-[var(--b-red-lit)] text-[var(--night)]' : index === activeStage ? 'animate-breathe border-[var(--b-red-lit)] bg-[var(--b-red-lit)] text-[var(--night)]' : 'border-white/20 text-white/40'">
              {{ index < activeStage ? '✓' : index + 1 }}
            </span>
            <span class="text-sm" :class="index <= activeStage ? 'text-white' : 'text-white/40'">{{ stage.label }}</span>
          </div>
        </div>

        <!-- The profile, without the face: a handle, the three counters every
             Instagram profile carries, and the grid underneath them. One tile
             is the reason the grid is drawn at all — the post that beat the
             account's own average, which is the first thing Personal looks
             for once the connection is made. -->
        <div v-else class="mt-10">
          <p class="font-display text-[26px] leading-none tracking-[-.02em]">{{ $t('onboarding.preview.handle') }}</p>
          <p class="mt-3 max-w-[19rem] text-[12.5px] leading-[1.6] text-white/45">{{ $t('onboarding.preview.bio') }}</p>

          <dl class="mt-7 flex gap-8 border-y border-white/10 py-4">
            <div v-for="stat in previewStats" :key="stat.key">
              <dd class="font-display text-[21px] leading-none tabular-nums">{{ stat.value }}</dd>
              <dt class="b-mono mt-2 text-white/35">{{ $t(`onboarding.preview.${stat.key}`) }}</dt>
            </div>
          </dl>

          <p class="b-mono mt-6 text-white/35">{{ $t('onboarding.preview.grid') }}</p>

          <div class="mt-3 grid grid-cols-3 gap-2">
            <div
              v-for="tile in PREVIEW_TILES"
              :key="tile.id"
              class="relative aspect-square overflow-hidden rounded-[10px] border"
              :class="tile.outlier ? 'border-[rgba(255,106,77,.45)] bg-[rgba(224,79,54,.13)]' : 'border-white/[.08] bg-white/[.045]'"
              :style="{ backgroundImage: tileWash(tile) }"
            >
              <AppIcon
                v-if="tile.kind"
                :name="tile.kind"
                :size="13"
                class="absolute right-2 top-2"
                :class="tile.outlier ? 'text-[var(--b-red-lit)]' : 'text-white/25'"
              />
              <span
                v-if="tile.outlier"
                class="absolute inset-x-1.5 bottom-1.5 rounded-[6px] bg-[rgba(11,10,9,.55)] px-1.5 py-1 text-center text-[9.5px] font-semibold tabular-nums text-[var(--b-red-lit)]"
              >
                {{ $t('contentCard.average', { ratio: previewRatio }) }}
              </span>
            </div>
          </div>

          <p class="mt-7 font-serif text-xl leading-7 text-white/45">{{ $t('onboarding.placeholderQuote') }}</p>
        </div>
      </aside>
    </section>
  </main>
</template>
