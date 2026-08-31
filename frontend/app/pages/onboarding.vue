<script setup lang="ts">
import type { HandleAnalysis, HandleAnalysisStage, InstagramSyncStatus } from '~/types/instagram'

definePageMeta({ layout: false })

const route = useRoute()
const { t, te, locale } = useI18n()
const { user } = useAuth()
const { status, connecting, error, connect, loadStatus, startPolling, stopPolling } = useInstagram()
const { apiFetch } = usePersonalApi()
const toast = useToast()
const accountHandleInput = ref('')
const handleSaving = ref(false)
const canUseInstagramOAuth = computed(() => user.value?.email.trim().toLowerCase() === 'hello@usepersonal.app')
// A profile that could not be read (a private account, a provider outage) must
// not lock a creator out of their own onboarding, so the failure screen offers
// a way through. Nothing else lets the flow past an unfinished analysis.
const analysisDismissed = ref(false)
const analysisRetrying = ref(false)

// The card on the right stands in for the thing being connected: an Instagram
// profile with no face on it, because the only part Personal reads is what the
// posts did. The figures illustrate the shape of a profile — the card is
// labelled an aperçu, never presented as the visitor's own account.
interface PreviewTile {
  id: number
  kind: 'carousel' | 'reel' | null
  angle: number
  light: number
  tint: string
  spot: [number, number]
  views?: number
  outlier?: boolean
}

const PREVIEW_TILES: readonly PreviewTile[] = [
  { id: 1, kind: 'carousel', angle: 148, light: .1, tint: '224, 128, 96', spot: [28, 22] },
  { id: 2, kind: null, angle: 32, light: .055, tint: '150, 165, 190', spot: [74, 32] },
  { id: 3, kind: 'reel', outlier: true, angle: 200, light: .08, tint: '255, 106, 77', spot: [50, 34] }
]

// Each tile gets its own wash so the grid reads as three thumbnails rather than
// three empty boxes: a lit corner, a coloured spot where a subject would sit.
// Abstract on purpose — the card never pretends to show anyone's actual photos.
function tileWash(tile: PreviewTile) {
  return [
    `radial-gradient(78% 62% at ${tile.spot[0]}% ${tile.spot[1]}%, rgba(${tile.tint}, ${(tile.light * 1.5).toFixed(3)}) 0%, rgba(${tile.tint}, 0) 72%)`,
    `linear-gradient(${tile.angle}deg, rgba(255, 255, 255, ${tile.light}) 0%, rgba(255, 255, 255, .012) 70%)`
  ].join(', ')
}

// Story highlights and the tab bar are the two things that make a profile read
// as the app rather than as a stat card, so the aperçu carries both.
const PREVIEW_HIGHLIGHTS = ['bestOf', 'behind', 'formats'] as const
const PREVIEW_TABS = [
  { key: 'posts', icon: 'grid' },
  { key: 'reels', icon: 'reel' },
  { key: 'tagged', icon: 'tagged' }
] as const

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

// The handle drives the rest of the profile the way it does on Instagram: it
// names the account, and its first letter stands in for the avatar.
const previewHandle = computed(() => status.value.instagram_username || t('onboarding.preview.handle').replace('@', ''))
const previewInitial = computed(() => previewHandle.value.charAt(0).toUpperCase())

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

// The handle a creator types is only useful once Personal has actually read the
// profile behind it, so onboarding shows that reading happening — profile,
// posts, voice, audience — and holds the next step until it is done.
const analysis = computed(() => status.value.analysis ?? null)

const analysisStages = computed<HandleAnalysisStage[]>(() => analysis.value?.stages?.length
  ? analysis.value.stages
  : ['reading_profile', 'importing_posts', 'reading_voice', 'mapping_audience'])

// 'idle' is a profile that predates the analysis: nothing is running, so nothing
// is worth waiting for either.
const analysisComplete = computed(() => analysis.value?.status === 'completed' || (analysis.value?.status ?? 'idle') === 'idle')
const analysisFailed = computed(() => analysis.value?.status === 'failed')

const analysisIndex = computed(() => {
  if (analysis.value?.status === 'completed') return analysisStages.value.length
  const index = analysisStages.value.indexOf(analysis.value?.status as HandleAnalysisStage)
  // 'queued' sits before the first step rather than outside the list.
  return index < 0 ? 0 : index
})

const postsTarget = computed(() => analysis.value?.posts_target ?? 30)

// The grid under the tab bar is the reading made visible: a tile is drawn once
// the share of posts behind it has been read, so the profile fills in at the
// pace of the analysis instead of appearing whole at the end.
const analysisTilesRead = computed(() => {
  const read = analysis.value?.analyzed_posts_count
  if (!read) return 0
  return Math.min(PREVIEW_TILES.length, Math.floor(read / postsTarget.value * PREVIEW_TILES.length))
})

function formatCount(value: number) {
  return new Intl.NumberFormat(locale.value).format(value)
}

// Instagram shortens the play count under a reel thumbnail; the aperçu does too,
// so the tile reads as a thumbnail rather than as a figure in a table.
function formatViews(value: number) {
  return new Intl.NumberFormat(locale.value, { notation: 'compact', maximumFractionDigits: 1 }).format(value)
}

// What each step found, written the moment it lands so the wait shows real
// progress instead of four spinners in a row.
function analysisDetail(stage: HandleAnalysisStage): string | null {
  const found = analysis.value
  if (!found) return null

  if (stage === 'reading_profile') {
    return found.followers_count === null
      ? null
      : t('onboarding.analysis.details.profile', {
        handle: status.value.instagram_username || '',
        followers: formatCount(found.followers_count)
      })
  }

  if (stage === 'importing_posts') {
    return found.analyzed_posts_count === null
      ? null
      : t('onboarding.analysis.details.posts', { count: found.analyzed_posts_count, target: postsTarget.value })
  }

  if (stage === 'reading_voice') {
    return found.tone?.length ? found.tone.join(' · ') : null
  }

  return found.audience_description || found.niche || null
}

const analysisSteps = computed(() => analysisStages.value.map((stage, index) => {
  const detail = analysisDetail(stage)

  return {
    key: stage,
    label: t(`onboarding.analysis.steps.${stage}`),
    detail,
    // A failure loses the step it died on, so what was already found is what
    // says a step finished — otherwise a read profile would lose its tick.
    done: analysisFailed.value ? detail !== null : index < analysisIndex.value,
    active: index === analysisIndex.value && !analysisFailed.value
  }
}))

const analysisDoneCount = computed(() => analysisSteps.value.filter(step => step.done).length)

const analysisErrorMessage = computed(() => {
  const reason = analysis.value?.error
  if (!reason) return null
  const key = `onboarding.analysis.errors.${reason}`
  // Unknown reasons still say something useful rather than echoing a raw key.
  return te(key) ? t(key) : t('onboarding.analysis.errors.analysis_unavailable')
})

// The creator is held here until every piece is in, which is the whole point of
// the step: everything Personal knows about a creator comes from it.
const showAnalysis = computed(() => !status.value.connected
  && Boolean(status.value.instagram_username)
  && !analysisComplete.value
  && !analysisDismissed.value)

const callbackError = computed(() => route.query.instagram === 'error'
  ? String(route.query.message || t('onboarding.cancelledError'))
  : null)

const connectionError = computed(() => callbackError.value || status.value.account?.sync_error || error.value)
watch(connectionError, (message) => {
  if (message) toast.error(message)
}, { immediate: true })

const onboarded = useState('personal-onboarded', () => false)

const parsedAccountHandle = computed(() => parseHandle(accountHandleInput.value))

// Accepts a bare handle, an @handle, or an instagram.com profile link.
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

async function saveAccountHandle() {
  const username = parsedAccountHandle.value
  if (!username || handleSaving.value) {
    if (accountHandleInput.value.trim()) toast.error(t('onboarding.handleError'))
    return
  }

  handleSaving.value = true

  try {
    await submitHandle(username)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('onboarding.handleSaveError')))
  } finally {
    handleSaving.value = false
  }
}

// Saving the handle starts the reading of the public profile behind it. The
// loader takes over from here and polling drives it to the next step.
async function submitHandle(username: string) {
  const response = await apiFetch<{ instagram_username: string, analysis: HandleAnalysis }>('/api/integrations/instagram/handle', {
    method: 'PUT',
    body: { username }
  })
  status.value.instagram_username = response.instagram_username
  status.value.analysis = response.analysis
  analysisDismissed.value = false
  startPolling()
}

// Sending the same handle again is what re-runs a reading that failed.
async function retryAnalysis() {
  const username = status.value.instagram_username
  if (!username || analysisRetrying.value) return

  analysisRetrying.value = true

  try {
    await submitHandle(username)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('onboarding.handleSaveError')))
  } finally {
    analysisRetrying.value = false
  }
}

// Only reachable once the reading has failed for good, so a profile Personal
// cannot read never locks a creator out of the rest of onboarding. Their memory
// stays empty until they fill it in from the profile page.
function continueWithoutAnalysis() {
  stopPolling()
  analysisDismissed.value = true
  void enterApp()
}

watch(() => status.value.account?.sync_status, (syncStatus) => {
  if (status.value.connected && syncStatus === 'completed') void enterApp()
})

// The analysis is the whole context Personal needs, so the moment it lands the
// creator can enter the app without another setup step.
watch(() => analysis.value?.status, (analysisStatus) => {
  if (analysisStatus === 'completed' && !status.value.connected) void enterApp()
})

function enterApp() {
  onboarded.value = true
  return navigateTo('/feed')
}

onMounted(async () => {
  await loadStatus(false)
  if (status.value.onboarding_complete) return enterApp()

  if (route.query.instagram === 'connected'
    || (status.value.connected && status.value.account?.sync_status !== 'completed')
    // A reload during the reading picks it back up where it was.
    || showAnalysis.value) {
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

    <section class="mx-auto grid min-h-[calc(100dvh-5rem)] max-w-6xl items-start gap-14 px-6 py-14 md:grid-cols-[1fr_0.74fr] md:px-10 md:py-20">
      <div class="max-w-xl animate-rise">
        <template v-if="!status.connected && !status.instagram_username">
          <h1 class="font-serif text-5xl leading-[1.02] tracking-[-0.045em] md:text-7xl" v-html="$t('onboarding.connectTitle')" />
          <p class="mt-7 max-w-lg text-[17px] leading-7 text-[var(--muted)]">
            {{ $t('onboarding.connectCopy') }}
          </p>

          <template v-if="canUseInstagramOAuth">
            <button
              class="mt-10 inline-flex h-[54px] w-full items-center justify-center gap-3 rounded-full b-btn-red px-7 text-[15px] font-medium transition hover:-translate-y-0.5 disabled:cursor-wait disabled:opacity-60 sm:w-auto"
              :disabled="connecting"
              @click="connect"
            >
              <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                <rect x="3" y="3" width="18" height="18" rx="5" />
                <circle cx="12" cy="12" r="4" />
                <circle cx="17.5" cy="6.5" r=".8" class="fill-current stroke-0" />
              </svg>
              {{ connecting ? $t('onboarding.preparing') : $t('onboarding.continueWithInstagram') }}
              <span aria-hidden="true">↗</span>
            </button>

            <div class="my-8 flex items-center gap-4 text-xs font-medium uppercase tracking-[.16em] text-[var(--faint)]">
              <span class="h-px flex-1 bg-[var(--line)]" />
              {{ $t('onboarding.or') }}
              <span class="h-px flex-1 bg-[var(--line)]" />
            </div>
          </template>

          <form
            class="rounded-[20px] border border-[var(--line)] bg-[var(--surface)] p-5"
            :class="canUseInstagramOAuth ? '' : 'mt-8'"
            @submit.prevent="saveAccountHandle"
          >
            <div class="flex flex-col gap-2 sm:flex-row">
              <input
                id="instagram-account-handle"
                v-model="accountHandleInput"
                type="text"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                class="min-w-0 flex-1 rounded-full border border-[var(--line)] bg-[var(--paper)] px-5 py-3 text-sm outline-none transition focus:border-[var(--ink)] [@media(pointer:coarse)]:text-[16px]"
                :placeholder="$t('onboarding.handlePlaceholder')"
                :aria-label="$t('onboarding.handleLabel')"
              >
              <button
                type="submit"
                class="rounded-full bg-[var(--ink)] px-5 py-3 text-sm font-medium text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!parsedAccountHandle || handleSaving"
              >
                {{ handleSaving ? $t('onboarding.savingHandle') : $t('onboarding.saveHandle') }}
              </button>
            </div>
          </form>
        </template>

        <template v-else>
          <div v-if="status.connected" class="mb-8 inline-flex items-center gap-3 rounded-full border border-[var(--line)] bg-[var(--surface)] py-2 pl-2 pr-4">
            <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--paper)] text-xs">IG</span>
            <span class="text-sm font-medium">Instagram {{ $t('onboarding.connectedSuffix') }}</span>
            <span class="text-[var(--positive)]">✓</span>
          </div>
          <div v-else-if="status.instagram_username" class="mb-8 inline-flex items-center gap-3 rounded-full border border-[var(--line)] bg-[var(--surface)] py-2 pl-2 pr-4">
            <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--paper)] text-xs">IG</span>
            <span class="text-sm font-medium">@{{ status.instagram_username }} {{ $t('onboarding.handleSuffix') }}</span>
            <span class="text-[var(--positive)]">✓</span>
          </div>

          <template v-if="showAnalysis">
            <!-- The card below says what is happening step by step, so a title
                 announcing it again would only push it down the screen. Only a
                 failure gets a heading, because the card cannot explain itself. -->
            <template v-if="analysisFailed">
              <h1 class="font-serif text-4xl leading-[1.02] tracking-[-0.045em] md:text-6xl">
                {{ $t('onboarding.analysis.failedTitle') }}
              </h1>
              <p class="mt-5 max-w-lg text-[16px] leading-7 text-[var(--muted)]">
                {{ analysisErrorMessage }}
              </p>
            </template>

            <section
              class="rounded-[24px] border border-[var(--line)] bg-[var(--surface)] p-5 md:p-6"
              :class="analysisFailed ? 'mt-7' : ''"
              aria-live="polite"
              :aria-busy="!analysisFailed"
            >
              <div class="flex items-center justify-between gap-3">
                <h2 class="truncate text-sm font-semibold">{{ '@' + status.instagram_username }}</h2>
                <span class="shrink-0 text-xs tabular-nums text-[var(--faint)]">
                  {{ $t('onboarding.analysis.progress', { done: analysisDoneCount, total: analysisSteps.length }) }}
                </span>
              </div>

              <div class="mt-3 h-1 overflow-hidden rounded-full bg-[var(--line)]">
                <div
                  class="h-full rounded-full bg-[var(--accent)] transition-all duration-700"
                  :style="{ width: `${(analysisDoneCount / analysisSteps.length) * 100}%` }"
                />
              </div>

              <ol class="mt-5 space-y-1">
                <li
                  v-for="(step, index) in analysisSteps"
                  :key="step.key"
                  class="flex items-start gap-3 rounded-[14px] px-3 py-3 transition"
                  :class="step.active ? 'bg-[var(--paper)]' : ''"
                >
                  <span
                    class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full border text-[11px] transition"
                    :class="step.done ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : step.active ? 'animate-breathe border-[var(--accent)] text-[var(--accent)]' : 'border-[var(--line)] text-[var(--faint)]'"
                  >
                    {{ step.done ? '✓' : index + 1 }}
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block text-[13.5px] font-medium" :class="step.done || step.active ? 'text-[var(--ink)]' : 'text-[var(--faint)]'">
                      {{ step.label }}
                    </span>
                    <span v-if="step.detail" class="mt-1 block truncate text-[12.5px] text-[var(--muted)]">{{ step.detail }}</span>
                    <span v-else-if="step.active" class="mt-1 block text-[12.5px] text-[var(--faint)]">{{ $t('onboarding.analysis.working') }}</span>
                  </span>
                </li>
              </ol>

              <div v-if="analysisFailed" class="mt-5 flex flex-wrap gap-2">
                <button
                  type="button"
                  class="rounded-full bg-[var(--ink)] px-5 py-2.5 text-sm font-medium text-white transition hover:-translate-y-0.5 disabled:cursor-wait disabled:opacity-50"
                  :disabled="analysisRetrying"
                  @click="retryAnalysis"
                >
                  {{ analysisRetrying ? $t('onboarding.analysis.retrying') : $t('onboarding.analysis.retry') }}
                </button>
                <button
                  type="button"
                  class="rounded-full border border-[var(--line)] bg-[var(--paper)] px-5 py-2.5 text-sm font-medium transition hover:border-[var(--muted)]"
                  @click="continueWithoutAnalysis"
                >
                  {{ $t('onboarding.analysis.continueAnyway') }}
                </button>
              </div>
            </section>

            <button
              v-if="!analysisFailed"
              type="button"
              class="mt-7 inline-flex h-[52px] cursor-not-allowed items-center gap-2 rounded-full b-btn-red px-7 text-[15px] font-medium opacity-40"
              disabled
            >
              {{ $t('onboarding.analysis.locked') }}
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
            v-if="canUseInstagramOAuth && status.account?.sync_status === 'failed'"
            class="mt-8 inline-flex h-11 items-center rounded-full border border-[var(--line)] bg-[var(--surface)] px-6 text-[14px] font-medium transition hover:bg-[var(--paper)]"
            :disabled="connecting"
            @click="connect"
          >
            {{ $t('onboarding.reconnect') }}
          </button>
        </template>

      </div>

      <aside class="b-night relative min-h-[360px] overflow-hidden rounded-[24px] p-7 text-white md:p-10">
        <!-- The same reading as the list on the left, seen from the profile's
             side: the profile is laid out the way Instagram lays it out, and
             each part fills in as it is found — nothing is drawn until it is
             real. -->
        <div v-if="showAnalysis">
          <div class="flex items-center gap-2">
            <p class="min-w-0 truncate font-display text-[22px] leading-none tracking-[-.02em]">
              {{ `@${previewHandle}` }}
            </p>
            <AppIcon name="chevron" :size="14" class="shrink-0 rotate-90 text-white/40" />
            <AppIcon name="plus" :size="17" class="ml-auto shrink-0 text-white/40" />
            <AppIcon name="text" :size="17" class="shrink-0 text-white/40" />
          </div>

          <div class="mt-6 flex items-center gap-5">
            <span class="shrink-0 rounded-full bg-gradient-to-tr from-[#f9ce34] via-[var(--b-red-500)] to-[#6228d7] p-[2.5px]">
              <span class="grid h-[68px] w-[68px] place-items-center rounded-full border-[3px] border-[#0d0c0b] bg-white/[.07] font-display text-[26px] leading-none text-white/70">
                {{ previewInitial }}
              </span>
            </span>
            <dl class="flex flex-1 justify-around text-center">
              <div>
                <dd class="font-display text-[19px] leading-none tabular-nums">
                  {{ analysis?.followers_count == null ? '—' : formatCount(analysis.followers_count) }}
                </dd>
                <dt class="mt-1.5 text-[11px] text-white/40">{{ $t('onboarding.preview.followers') }}</dt>
              </div>
              <div>
                <dd class="font-display text-[19px] leading-none tabular-nums">
                  {{ analysis?.analyzed_posts_count == null ? '—' : `${analysis.analyzed_posts_count}/${postsTarget}` }}
                </dd>
                <dt class="mt-1.5 text-[11px] text-white/40">{{ $t('onboarding.analysis.postsRead') }}</dt>
              </div>
            </dl>
          </div>

          <p class="mt-5 text-[13px] font-semibold leading-none">{{ previewHandle }}</p>
          <p v-if="analysis?.niche" class="mt-1.5 text-[12px] leading-none text-white/35">{{ analysis.niche }}</p>

          <p v-if="analysis?.bio" class="mt-2 max-w-[21rem] whitespace-pre-line text-[12.5px] leading-[1.55] text-white/50">{{ analysis.bio }}</p>
          <div v-else class="mt-3 space-y-2">
            <div v-for="width in ['w-56', 'w-40']" :key="width" class="h-2.5 max-w-full rounded-full bg-white/10" :class="[width, analysisFailed ? '' : 'animate-pulse']" />
          </div>

          <div class="mt-4 flex gap-1.5">
            <span class="grid h-8 flex-1 place-items-center rounded-lg bg-white/[.09] text-[12px] font-semibold text-white/80">{{ $t('onboarding.preview.editProfile') }}</span>
            <span class="grid h-8 flex-1 place-items-center rounded-lg bg-white/[.09] text-[12px] font-semibold text-white/80">{{ $t('onboarding.preview.shareProfile') }}</span>
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/[.09] text-white/70"><AppIcon name="user" :size="14" /></span>
          </div>

          <div class="-mx-7 mt-6 grid grid-cols-3 border-t border-white/10 md:-mx-10">
            <span
              v-for="(tab, index) in PREVIEW_TABS"
              :key="tab.key"
              class="grid h-11 place-items-center border-b"
              :class="index === 0 ? 'border-white/80 text-white' : 'border-transparent text-white/25'"
              :title="$t(`onboarding.preview.tabs.${tab.key}`)"
            >
              <AppIcon :name="tab.icon" :size="17" />
            </span>
          </div>

          <!-- The grid is the reading itself: a tile lights up as the posts
               behind it are read, and the rest stay empty until they are. -->
          <div class="-mx-7 grid grid-cols-3 gap-[2px] md:-mx-10">
            <div
              v-for="(tile, index) in PREVIEW_TILES"
              :key="tile.id"
              class="aspect-square"
              :class="index < analysisTilesRead ? '' : ['bg-white/[.03]', analysisFailed ? '' : 'animate-pulse']"
              :style="index < analysisTilesRead ? { backgroundImage: tileWash(tile) } : undefined"
            />
          </div>
        </div>

        <div v-else-if="status.connected" class="mt-2 space-y-1">
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

        <!-- The profile, without the face: the aperçu is laid out the way the
             Instagram app lays a profile out — handle bar, avatar beside the
             three counters, name and bio, the two profile buttons, highlights,
             the tab bar, then the grid edge to edge. One tile is the reason the
             grid is drawn at all: the post that beat the account's own average,
             which is the first thing Personal looks for once connected. -->
        <div v-else>
          <div class="flex items-center gap-2">
            <p class="min-w-0 truncate font-display text-[22px] leading-none tracking-[-.02em]">
              {{ `@${previewHandle}` }}
            </p>
            <AppIcon name="chevron" :size="14" class="shrink-0 rotate-90 text-white/40" />
            <AppIcon name="plus" :size="17" class="ml-auto shrink-0 text-white/40" />
            <AppIcon name="text" :size="17" class="shrink-0 text-white/40" />
          </div>

          <div class="mt-6 flex items-center gap-5">
            <span class="shrink-0 rounded-full bg-gradient-to-tr from-[#f9ce34] via-[var(--b-red-500)] to-[#6228d7] p-[2.5px]">
              <span class="grid h-[68px] w-[68px] place-items-center rounded-full border-[3px] border-[#0d0c0b] bg-white/[.07] font-display text-[26px] leading-none text-white/70">
                {{ previewInitial }}
              </span>
            </span>
            <dl class="flex flex-1 justify-around text-center">
              <div v-for="stat in previewStats" :key="stat.key">
                <dd class="font-display text-[19px] leading-none tabular-nums">{{ stat.value }}</dd>
                <dt class="mt-1.5 text-[11px] text-white/40">{{ $t(`onboarding.preview.${stat.key}`) }}</dt>
              </div>
            </dl>
          </div>

          <p class="mt-5 text-[13px] font-semibold leading-none">{{ previewHandle }}</p>
          <p class="mt-1.5 text-[12px] leading-none text-white/35">{{ $t('onboarding.preview.category') }}</p>
          <p class="mt-2 max-w-[21rem] text-[12.5px] leading-[1.55] text-white/50">{{ $t('onboarding.preview.bio') }}</p>

          <div class="mt-4 flex gap-1.5">
            <span class="grid h-8 flex-1 place-items-center rounded-lg bg-white/[.09] text-[12px] font-semibold text-white/80">{{ $t('onboarding.preview.editProfile') }}</span>
            <span class="grid h-8 flex-1 place-items-center rounded-lg bg-white/[.09] text-[12px] font-semibold text-white/80">{{ $t('onboarding.preview.shareProfile') }}</span>
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/[.09] text-white/70"><AppIcon name="user" :size="14" /></span>
          </div>

          <div class="mt-5 flex gap-5">
            <span v-for="(highlight, index) in PREVIEW_HIGHLIGHTS" :key="highlight" class="flex w-[52px] shrink-0 flex-col items-center gap-1.5">
              <span class="h-[52px] w-[52px] rounded-full border border-white/15 bg-white/[.045]" :style="{ backgroundImage: tileWash(PREVIEW_TILES[index]!) }" />
              <span class="w-full truncate text-center text-[10.5px] text-white/40">{{ $t(`onboarding.preview.highlights.${highlight}`) }}</span>
            </span>
          </div>

          <!-- Tab bar and grid run to the panel's edges, the way they run to the
               screen's edges in the app. -->
          <div class="-mx-7 mt-6 grid grid-cols-3 border-t border-white/10 md:-mx-10">
            <span
              v-for="(tab, index) in PREVIEW_TABS"
              :key="tab.key"
              class="grid h-11 place-items-center border-b"
              :class="index === 0 ? 'border-white/80 text-white' : 'border-transparent text-white/25'"
              :title="$t(`onboarding.preview.tabs.${tab.key}`)"
            >
              <AppIcon :name="tab.icon" :size="17" />
            </span>
          </div>

          <div class="-mx-7 grid grid-cols-3 gap-[2px] md:-mx-10">
            <div
              v-for="tile in PREVIEW_TILES"
              :key="tile.id"
              class="relative aspect-square overflow-hidden"
              :class="tile.outlier ? 'bg-[rgba(224,79,54,.16)] ring-1 ring-inset ring-[rgba(255,106,77,.4)]' : 'bg-white/[.045]'"
              :style="{ backgroundImage: tileWash(tile) }"
            >
              <AppIcon
                v-if="tile.kind"
                :name="tile.kind"
                :size="14"
                class="absolute right-2 top-2 drop-shadow-[0_1px_2px_rgba(0,0,0,.5)]"
                :class="tile.outlier ? 'text-[var(--b-red-lit)]' : 'text-white/55'"
              />
              <span v-if="tile.views" class="absolute bottom-1.5 left-2 flex items-center gap-1 text-[10.5px] font-medium tabular-nums text-white/70 drop-shadow-[0_1px_2px_rgba(0,0,0,.5)]">
                <AppIcon name="play" :size="11" filled />{{ formatViews(tile.views) }}
              </span>
              <span
                v-if="tile.outlier"
                class="absolute inset-x-1.5 bottom-1.5 flex items-center justify-center gap-1 rounded-[6px] bg-[rgba(11,10,9,.62)] px-1.5 py-1 text-[9.5px] font-semibold tabular-nums text-[var(--b-red-lit)]"
              >
                <AppIcon name="trend" :size="11" class="shrink-0" />{{ $t('contentCard.averageAccount', { ratio: previewRatio }) }}
              </span>
            </div>
          </div>
        </div>
      </aside>
    </section>
  </main>
</template>
