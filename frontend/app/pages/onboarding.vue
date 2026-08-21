<script setup lang="ts">
import type { InstagramSyncStatus } from '~/types/instagram'

definePageMeta({ layout: false })

const route = useRoute()
const { t } = useI18n()
const { status, loading, error, connect, loadStatus, startPolling } = useInstagram()
const toast = useToast()

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

// Keep the onboarding gate (auth.global) in sync the moment the import finishes,
// so leaving for the feed is instant instead of being re-checked at the gate.
const onboarded = useState('personal-onboarded', () => false)
watch(() => status.value.account?.sync_status, (syncStatus) => {
  if (status.value.connected && syncStatus === 'completed') onboarded.value = true
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
  if (route.query.instagram === 'connected' || (status.value.connected && status.value.account?.sync_status !== 'completed')) {
    startPolling()
  }
})
</script>

<template>
  <main class="min-h-screen overflow-hidden bg-[var(--paper)] text-[var(--ink)]">
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
            class="mt-10 inline-flex h-[54px] items-center gap-3 rounded-full bg-[var(--ink)] px-7 text-[15px] font-medium text-[var(--paper)] transition hover:-translate-y-0.5 hover:bg-black disabled:cursor-wait disabled:opacity-60"
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

          <h1 class="font-serif text-5xl leading-[1.02] tracking-[-0.045em] md:text-7xl">
            {{ status.account?.sync_status === 'completed' ? $t('onboarding.readyTitle') : $t('onboarding.understandingTitle') }}
          </h1>
          <p class="mt-7 max-w-lg text-[17px] leading-7 text-[var(--muted)]">
            <template v-if="status.account?.sync_status === 'completed'">
              {{ $t('onboarding.importedCopy', { count: status.account.imported_media_count }) }}
            </template>
            <template v-else>
              {{ $t('onboarding.importingCopy') }}
            </template>
          </p>

          <NuxtLink
            v-if="status.account?.sync_status === 'completed'"
            to="/feed"
            class="mt-10 inline-flex h-[54px] items-center rounded-full bg-[var(--ink)] px-7 text-[15px] font-medium text-[var(--paper)] transition hover:-translate-y-0.5 hover:bg-black"
          >
            {{ $t('onboarding.startPersonal') }}&nbsp; →
          </NuxtLink>

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

      <aside class="hero-night relative min-h-[430px] overflow-hidden rounded-[24px] p-7 text-white md:p-10">
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
