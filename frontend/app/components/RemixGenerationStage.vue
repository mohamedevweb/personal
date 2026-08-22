<script setup lang="ts">
import type { RemixLaunchFormat } from '~/composables/useRemixLaunch'

const props = withDefaults(defineProps<{
  format: RemixLaunchFormat
  sourceHook?: string | null
  moment?: string | null
  startedAt?: number
  overlay?: boolean
  complete?: boolean
}>(), {
  sourceHook: null,
  moment: null,
  startedAt: 0,
  overlay: false,
  complete: false
})

const { t } = useI18n()
const beganAt = ref(props.startedAt || Date.now())
const elapsed = ref(Math.max(0, Date.now() - beganAt.value))
let progressTimer: ReturnType<typeof setInterval> | undefined

const phase = computed(() => {
  if (props.complete) return 3
  if (elapsed.value < 1600) return 0
  if (elapsed.value < 4200) return 1
  return 2
})

const progress = computed(() => props.complete ? 100 : Math.min(94, 10 + elapsed.value / 115))
const steps = ['pattern', 'story', 'voice'] as const
const lineWidths = computed(() => props.format === 'caption'
  ? ['w-full', 'w-11/12', 'w-4/5', 'w-full', 'w-2/3']
  : props.format === 'reel'
    ? ['w-2/3', 'w-full', 'w-11/12', 'w-4/5', 'w-3/5']
    : ['w-3/4', 'w-full', 'w-5/6', 'w-11/12', 'w-2/3'])

const source = computed(() => props.sourceHook || t('remix.generation.patternFallback'))
const personalMaterial = computed(() => props.moment || t('remix.generation.profileFallback'))

function syncElapsed() {
  elapsed.value = Math.max(0, Date.now() - beganAt.value)
}

watch(() => props.startedAt, (startedAt) => {
  if (!startedAt) return
  beganAt.value = startedAt
  syncElapsed()
})

onMounted(() => {
  syncElapsed()
  if (!props.complete) progressTimer = setInterval(syncElapsed, 100)
})

onBeforeUnmount(() => clearInterval(progressTimer))
</script>

<template>
  <section
    role="status"
    aria-live="polite"
    :class="overlay ? 'fixed inset-0 z-50 overflow-y-auto bg-[var(--paper)] py-4 md:py-7' : 'page-shell pb-24 pt-4'"
  >
    <div :class="overlay ? 'mx-auto w-full max-w-[1180px] px-5 md:px-8' : ''">
      <div
        class="relative overflow-hidden rounded-[26px] bg-[var(--night)] px-6 py-7 text-white md:px-10 md:py-10"
        :class="overlay && 'min-h-[calc(100vh-2rem)] md:min-h-[calc(100vh-3.5rem)]'"
      >
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-[var(--ai)]/20 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-32 left-1/4 h-72 w-72 rounded-full bg-[var(--accent)]/15 blur-3xl" />

        <div class="relative flex items-center justify-between gap-4">
          <div class="inline-flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.18em] text-white/55">
            <span
              class="grid h-7 w-7 place-items-center rounded-full"
              :class="complete ? 'bg-[var(--positive)] text-white' : 'bg-white/10 text-white'"
            >
              <AppIcon :name="complete ? 'check' : 'sparkles'" :size="13" :class="!complete && 'motion-safe:animate-breathe'" />
            </span>
            {{ complete ? $t('remix.generation.completeEyebrow') : $t('remix.generation.eyebrow') }}
          </div>
          <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] text-white/65">
            {{ $t(`remix.formats.${format}`) }}
          </span>
        </div>

        <div class="relative mt-8 grid gap-8 lg:grid-cols-[1.02fr_.98fr] lg:items-center lg:gap-12">
          <div>
            <h1 class="max-w-[13ch] font-serif text-[40px] leading-[.98] tracking-[-.04em] md:text-[58px]">
              {{ complete ? $t('remix.generation.completeTitle') : $t('remix.generation.title') }}
            </h1>
            <p class="mt-5 max-w-[48ch] text-[14px] leading-6 text-white/60 md:text-[15px]">
              {{ complete ? $t('remix.generation.completeCopy') : $t('remix.generation.copy') }}
            </p>

            <div class="mt-8 grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-stretch">
              <div class="panel-night rounded-[18px] p-4">
                <p class="text-[9px] font-semibold uppercase tracking-[.17em] text-white/40">{{ $t('remix.generation.patternLabel') }}</p>
                <p class="mt-3 line-clamp-3 font-serif text-[19px] leading-[1.2] text-white/90">“{{ source }}”</p>
              </div>
              <span class="grid h-8 w-8 place-items-center self-center justify-self-center rounded-full bg-white/10 text-white/55">
                <AppIcon name="plus" :size="14" />
              </span>
              <div class="panel-night rounded-[18px] p-4">
                <p class="text-[9px] font-semibold uppercase tracking-[.17em] text-white/40">{{ $t('remix.generation.materialLabel') }}</p>
                <p class="mt-3 line-clamp-3 text-[13px] leading-5 text-white/75">{{ personalMaterial }}</p>
              </div>
            </div>
          </div>

          <div class="relative mx-auto w-full max-w-[430px]">
            <div class="absolute inset-x-8 -bottom-3 h-full rounded-[22px] border border-white/10 bg-white/[.035]" />
            <div class="relative overflow-hidden rounded-[22px] border border-white/15 bg-[#fdfdfb] text-[var(--ink)] shadow-[0_30px_80px_rgba(0,0,0,.3)]">
              <div class="flex items-center justify-between border-b border-[var(--line-soft)] px-5 py-4">
                <span class="inline-flex items-center gap-2 text-[11px] font-medium text-[var(--muted)]">
                  <AppIcon :name="format === 'caption' ? 'text' : format" :size="15" />
                  {{ $t('remix.generation.previewLabel') }}
                </span>
                <span class="inline-flex items-center gap-1.5 text-[10px]" :class="complete ? 'text-[var(--positive)]' : 'text-[var(--ai)]'">
                  <span class="h-1.5 w-1.5 rounded-full bg-current" :class="!complete && 'motion-safe:animate-pulse'" />
                  {{ complete ? $t('remix.generation.previewReady') : $t('remix.generation.previewWriting') }}
                </span>
              </div>

              <div v-if="format === 'carousel'" class="grid grid-cols-2 gap-3 p-5">
                <div
                  v-for="slide in 6"
                  :key="slide"
                  class="flex aspect-[4/3] flex-col justify-between rounded-[13px] border p-3"
                  :class="slide === 1 ? 'border-[var(--ink)] bg-[var(--ink)] text-white' : 'border-[var(--line)] bg-[var(--paper)]'"
                >
                  <span class="text-[9px] opacity-45">0{{ slide }}</span>
                  <span class="h-1.5 rounded-full" :class="[slide === 1 ? 'bg-white/55' : 'bg-[var(--line)]', !complete && 'motion-safe:animate-pulse', slide % 2 ? 'w-4/5' : 'w-2/3']" />
                </div>
              </div>

              <div v-else class="space-y-5 p-6 md:p-7">
                <div class="space-y-2.5">
                  <div
                    v-for="(width, index) in lineWidths"
                    :key="index"
                    class="h-2 rounded-full bg-[var(--line)]"
                    :class="[width, !complete && 'motion-safe:animate-pulse', index === 0 && 'h-3 bg-[var(--ink)]/75']"
                  />
                </div>
                <div v-if="format === 'reel'" class="flex items-center gap-3 border-t border-[var(--line-soft)] pt-4">
                  <span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--accent-soft)] text-[var(--accent-ink)]"><AppIcon name="reel" :size="15" /></span>
                  <div class="flex-1 space-y-2"><div class="h-1.5 w-full rounded-full bg-[var(--line)]" /><div class="h-1.5 w-2/3 rounded-full bg-[var(--line)]" /></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="relative mt-9 border-t border-white/10 pt-6">
          <div class="h-1 overflow-hidden rounded-full bg-white/10">
            <span class="block h-full rounded-full bg-white transition-[width] duration-300 motion-reduce:transition-none" :style="{ width: `${progress}%` }" />
          </div>
          <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div v-for="(step, index) in steps" :key="step" class="flex items-center gap-3">
              <span
                class="grid h-7 w-7 shrink-0 place-items-center rounded-full border text-[10px] transition"
                :class="index < phase || complete
                  ? 'border-[var(--positive)] bg-[var(--positive)] text-white'
                  : index === phase
                    ? 'border-white/35 bg-white/10 text-white'
                    : 'border-white/10 text-white/30'"
              >
                <AppIcon v-if="index < phase || complete" name="check" :size="12" />
                <span v-else>{{ index + 1 }}</span>
              </span>
              <span class="text-[11.5px]" :class="index <= phase || complete ? 'text-white/80' : 'text-white/35'">{{ $t(`remix.generation.steps.${step}`) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
