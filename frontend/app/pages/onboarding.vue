<script setup lang="ts">
import type { HandleAnalysis, HandleAnalysisStage, InstagramSyncStatus } from '~/types/instagram'

definePageMeta({ layout: false })

const route = useRoute()
const { t, te, locale } = useI18n()
const { status, loading, error, connect, loadStatus, startPolling, stopPolling } = useInstagram()
const { apiFetch } = usePersonalApi()
const toast = useToast()
const accountHandleInput = ref('')
const handleSaving = ref(false)
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
  outlier?: boolean
}

const PREVIEW_TILES: readonly PreviewTile[] = [
  { id: 1, kind: 'carousel', angle: 148, light: .085 },
  { id: 2, kind: null, angle: 32, light: .05 },
  { id: 3, kind: 'reel', outlier: true, angle: 200, light: .07 },
  { id: 4, kind: null, angle: 210, light: .065 },
  { id: 5, kind: 'reel', angle: 118, light: .045 },
  { id: 6, kind: 'carousel', angle: 60, light: .075 }
]

// Each tile gets its own wash so the grid reads as six thumbnails rather than
// six empty boxes. The angle and the amount of light are all that separate
// them: enough at this size, and abstract enough that the card never pretends
// to be showing anyone's actual photos.
function tileWash(tile: PreviewTile) {
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

function formatCount(value: number) {
  return new Intl.NumberFormat(locale.value).format(value)
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

const onboardingSteps = computed(() => [
  {
    key: 'understand',
    label: t('onboarding.flow.understand'),
    complete: false,
    active: true
  }
])
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
  await loadStatus()
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

    <section class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-6xl items-center gap-14 px-6 py-14 md:grid-cols-[1fr_0.88fr] md:px-10 md:py-20">
      <div class="max-w-xl animate-rise">
        <ol class="mb-9 grid grid-cols-1 gap-2" :aria-label="$t('onboarding.flow.label')">
          <li v-for="(step, index) in onboardingSteps" :key="step.key" class="min-w-0">
            <div class="mb-2 h-1 rounded-full transition" :class="step.complete || step.active ? 'bg-[var(--accent)]' : 'bg-[var(--line)]'" />
            <p class="truncate text-[10px] font-semibold uppercase tracking-[.12em]" :class="step.active ? 'text-[var(--ink)]' : 'text-[var(--faint)]'">
              {{ index + 1 }}. {{ step.label }}
            </p>
          </li>
        </ol>

        <template v-if="!status.connected && !status.instagram_username">
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

          <div class="my-8 flex items-center gap-4 text-xs font-medium uppercase tracking-[.16em] text-[var(--faint)]">
            <span class="h-px flex-1 bg-[var(--line)]" />
            {{ $t('onboarding.or') }}
            <span class="h-px flex-1 bg-[var(--line)]" />
          </div>

          <form class="rounded-[20px] border border-[var(--line)] bg-[var(--surface)] p-5" @submit.prevent="saveAccountHandle">
            <label for="instagram-account-handle" class="block text-sm font-semibold">
              {{ $t('onboarding.handleTitle') }}
            </label>
            <p class="mt-1.5 text-[13px] leading-5 text-[var(--muted)]">
              {{ $t('onboarding.handleCopy') }}
            </p>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
              <input
                id="instagram-account-handle"
                v-model="accountHandleInput"
                type="text"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                class="min-w-0 flex-1 rounded-full border border-[var(--line)] bg-[var(--paper)] px-5 py-3 text-sm outline-none transition focus:border-[var(--ink)]"
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
              <p v-else class="mt-4 flex items-start gap-2 text-[12px] leading-5 text-[var(--faint)]">
                <AppIcon name="shield" :size="14" class="mt-0.5 shrink-0 text-[var(--accent)]" />
                {{ $t('onboarding.analysis.lockNote') }}
              </p>
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
          {{ showAnalysis ? (analysisFailed ? $t('onboarding.analysis.previewFailedLabel') : $t('onboarding.analysis.previewLabel')) : status.connected ? $t('onboarding.profileLive') : status.instagram_username ? $t('onboarding.profileProvided') : $t('onboarding.preview.label') }}
        </p>

        <!-- The same reading as the list on the left, seen from the profile's
             side: the counters and the bio fill in as they are found, and
             nothing is drawn until it is real. -->
        <div v-if="showAnalysis" class="mt-10">
          <p class="font-display text-[26px] leading-none tracking-[-.02em]">{{ '@' + status.instagram_username }}</p>

          <p v-if="analysis?.bio" class="mt-4 max-w-[19rem] whitespace-pre-line text-[12.5px] leading-[1.6] text-white/60">{{ analysis.bio }}</p>
          <div v-else class="mt-5 space-y-2.5">
            <div v-for="width in ['w-56', 'w-40']" :key="width" class="h-2.5 max-w-full rounded-full bg-white/10" :class="[width, analysisFailed ? '' : 'animate-pulse']" />
          </div>

          <dl class="mt-7 flex gap-8 border-y border-white/10 py-4">
            <div>
              <dd class="font-display text-[21px] leading-none tabular-nums">
                {{ analysis?.followers_count == null ? '—' : formatCount(analysis.followers_count) }}
              </dd>
              <dt class="b-mono mt-2 text-white/35">{{ $t('onboarding.preview.followers') }}</dt>
            </div>
            <div>
              <dd class="font-display text-[21px] leading-none tabular-nums">
                {{ analysis?.analyzed_posts_count == null ? '—' : `${analysis.analyzed_posts_count}/${postsTarget}` }}
              </dd>
              <dt class="b-mono mt-2 text-white/35">{{ $t('onboarding.analysis.postsRead') }}</dt>
            </div>
          </dl>

          <p class="b-mono mt-6 text-white/35">{{ $t('onboarding.analysis.previewVoice') }}</p>
          <div v-if="analysis?.tone?.length" class="mt-3 flex flex-wrap gap-2">
            <span v-for="tone in analysis.tone" :key="tone" class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs text-white/70">{{ tone }}</span>
          </div>
          <div v-else class="mt-3 flex gap-2">
            <div v-for="i in 3" :key="i" class="h-7 w-20 rounded-full bg-white/[.07]" :class="analysisFailed ? '' : 'animate-pulse'" />
          </div>

          <p class="mt-7 font-serif text-xl leading-7 text-white/45">{{ $t('onboarding.analysis.previewNote') }}</p>
        </div>

        <div v-else-if="status.connected" class="mt-12 space-y-1">
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
          <p class="font-display text-[26px] leading-none tracking-[-.02em]">
            {{ status.instagram_username ? `@${status.instagram_username}` : $t('onboarding.preview.handle') }}
          </p>
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
                {{ $t('contentCard.averageAccount', { ratio: previewRatio }) }}
              </span>
            </div>
          </div>

          <p class="mt-7 font-serif text-xl leading-7 text-white/45">{{ $t('onboarding.placeholderQuote') }}</p>
        </div>
      </aside>
    </section>
  </main>
</template>
