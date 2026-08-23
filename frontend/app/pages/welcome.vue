<script setup lang="ts">
definePageMeta({ layout: false })

const { user, completeOnboarding } = useAuth()
const { t } = useI18n()
const toast = useToast()
const step = ref(0)
const finishing = ref(false)

const steps = computed(() => [
  { key: 'welcome', icon: 'sparkles' },
  { key: 'discover', icon: 'trend' },
  { key: 'moments', icon: 'moments' },
  { key: 'create', icon: 'draft' }
])

const current = computed(() => steps.value[step.value])
const isFirst = computed(() => step.value === 0)
const isLast = computed(() => step.value === steps.value.length - 1)
const firstName = computed(() => user.value?.name?.trim().split(/\s+/)[0] || t('productGuide.creator'))

function next() {
  if (!isLast.value) step.value += 1
}

function previous() {
  if (!isFirst.value) step.value -= 1
}

async function finish(destination = '/feed') {
  if (finishing.value) return
  finishing.value = true

  try {
    await completeOnboarding()
    await navigateTo(destination)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('productGuide.error')))
    finishing.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-[var(--paper)] px-4 py-4 text-[var(--ink)] md:px-6 md:py-6">
    <section class="mx-auto flex min-h-[calc(100vh-2rem)] max-w-6xl flex-col overflow-hidden rounded-[26px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)] md:min-h-[calc(100vh-3rem)]">
      <header class="flex items-center justify-between px-5 py-5 md:px-8">
        <PersonalLogo :size="21" />
        <button
          type="button"
          class="rounded-full px-3 py-2 text-sm text-[var(--faint)] transition hover:bg-[var(--line-soft)] hover:text-[var(--ink)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)] disabled:opacity-50"
          :disabled="finishing"
          @click="finish()"
        >
          {{ $t('productGuide.skip') }}
        </button>
      </header>

      <div class="grid flex-1 md:grid-cols-[0.9fr_1.1fr]">
        <div class="flex flex-col justify-center px-6 py-10 md:px-12 lg:px-16">
          <div class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">
            <span class="grid h-8 w-8 place-items-center rounded-[11px] bg-[var(--accent-soft)] text-[#8a6413]">
              <AppIcon :name="current.icon" :size="16" />
            </span>
            {{ $t('productGuide.progress', { current: step + 1, total: steps.length }) }}
          </div>

          <h1 class="mt-7 max-w-lg font-serif text-[42px] leading-[1.02] tracking-[-.04em] md:text-[56px]">
            {{ $t(`productGuide.steps.${current.key}.title`, { name: firstName }) }}
          </h1>
          <p class="mt-6 max-w-md text-[16px] leading-7 text-[var(--muted)]">
            {{ $t(`productGuide.steps.${current.key}.copy`) }}
          </p>

          <div class="mt-10 flex items-center gap-3">
            <button
              v-if="!isFirst"
              type="button"
              class="rounded-full border border-[var(--line)] bg-[var(--surface)] px-5 py-3 text-sm font-medium transition hover:bg-[var(--line-soft)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)]"
              @click="previous"
            >
              {{ $t('productGuide.back') }}
            </button>
            <button
              v-if="!isLast"
              type="button"
              class="inline-flex items-center gap-2 rounded-full bg-[var(--ink)] px-6 py-3 text-sm font-medium text-white transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)] focus-visible:ring-offset-2"
              @click="next"
            >
              {{ $t('productGuide.next') }}
              <AppIcon name="arrow" :size="16" />
            </button>
          </div>

          <div class="mt-10 flex gap-2" :aria-label="$t('productGuide.progressLabel')">
            <button
              v-for="(_, index) in steps"
              :key="index"
              type="button"
              class="h-1.5 rounded-full transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)]"
              :class="index === step ? 'w-8 bg-[var(--accent)]' : 'w-3 bg-[var(--line)] hover:bg-[var(--faint)]'"
              :aria-label="$t('productGuide.goToStep', { step: index + 1 })"
              :aria-current="index === step ? 'step' : undefined"
              @click="step = index"
            />
          </div>
        </div>

        <div class="m-3 flex min-h-[430px] items-center justify-center overflow-hidden rounded-[24px] bg-[var(--night)] p-5 text-white md:m-4 md:p-10">
          <Transition name="guide" mode="out-in">
            <div :key="current.key" class="w-full max-w-lg">
              <div v-if="current.key === 'welcome'" class="text-center">
                <PersonalMark :size="80" tone="signature" class="mx-auto" />
                <p class="mx-auto mt-8 max-w-sm font-serif text-[34px] leading-tight tracking-[-.03em]">
                  {{ $t('productGuide.preview.welcome') }}
                </p>
                <div class="mx-auto mt-8 grid max-w-sm grid-cols-3 gap-2">
                  <div v-for="item in ['discover', 'moments', 'create']" :key="item" class="panel-night rounded-[15px] px-3 py-4 text-center">
                    <AppIcon :name="item === 'discover' ? 'trend' : item === 'moments' ? 'moments' : 'draft'" :size="18" class="mx-auto text-[var(--accent)]" />
                    <p class="mt-2 text-xs text-white/70">{{ $t(`productGuide.preview.labels.${item}`) }}</p>
                  </div>
                </div>
              </div>

              <div v-else-if="current.key === 'discover'" class="panel-night rounded-[20px] p-5 md:p-7">
                <div class="flex items-center justify-between">
                  <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-white/45">{{ $t('productGuide.preview.discover.eyebrow') }}</p>
                  <span class="rounded-full bg-[rgba(182,135,31,.18)] px-3 py-1 text-xs text-[#e4c26f]">{{ $t('productGuide.preview.discover.ratio') }}</span>
                </div>
                <p class="mt-8 font-serif text-[30px] leading-tight tracking-[-.03em]">{{ $t('productGuide.preview.discover.hook') }}</p>
                <div class="mt-8 flex items-center gap-5 border-t border-white/10 pt-5 text-xs text-white/50">
                  <span>{{ $t('productGuide.preview.discover.views') }}</span>
                  <span>{{ $t('productGuide.preview.discover.signal') }}</span>
                </div>
              </div>

              <div v-else-if="current.key === 'moments'" class="space-y-3">
                <div class="panel-night rounded-[20px] p-6">
                  <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-white/45">{{ $t('productGuide.preview.moments.eyebrow') }}</p>
                  <p class="mt-5 font-serif text-[28px] leading-snug tracking-[-.025em]">{{ $t('productGuide.preview.moments.quote') }}</p>
                  <div class="mt-6 flex gap-2">
                    <span v-for="reason in ['personal', 'change', 'lesson']" :key="reason" class="rounded-full border border-white/10 px-3 py-1.5 text-[11px] text-white/55">
                      {{ $t(`productGuide.preview.moments.reasons.${reason}`) }}
                    </span>
                  </div>
                </div>
                <p class="px-2 text-center text-xs leading-5 text-white/45">{{ $t('productGuide.preview.moments.note') }}</p>
              </div>

              <div v-else class="space-y-4">
                <div class="panel-night rounded-[20px] p-6">
                  <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-white/45">{{ $t('productGuide.preview.create.eyebrow') }}</p>
                  <div class="mt-5 space-y-3">
                    <div v-for="item in ['pattern', 'story', 'draft']" :key="item" class="flex items-center gap-3 rounded-[14px] bg-white/[.035] p-3">
                      <span class="grid h-8 w-8 place-items-center rounded-full bg-white/[.06] text-xs text-[#e4c26f]">
                        <AppIcon :name="item === 'pattern' ? 'trend' : item === 'story' ? 'moments' : 'sparkles'" :size="15" />
                      </span>
                      <span class="text-sm text-white/70">{{ $t(`productGuide.preview.create.${item}`) }}</span>
                      <AppIcon name="check" :size="15" class="ml-auto text-[#e4c26f]" />
                    </div>
                  </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-3">
                  <button
                    v-for="destination in [
                      { route: '/feed', key: 'feed', icon: 'sparkles' },
                      { route: '/moments', key: 'moments', icon: 'moments' },
                      { route: '/create', key: 'create', icon: 'plus' }
                    ]"
                    :key="destination.route"
                    type="button"
                    class="rounded-full bg-white px-4 py-3 text-sm font-medium text-[var(--ink)] transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)] disabled:cursor-wait disabled:opacity-60"
                    :disabled="finishing"
                    @click="finish(destination.route)"
                  >
                    <span class="inline-flex items-center gap-2">
                      <AppIcon :name="destination.icon" :size="15" />
                      {{ $t(`productGuide.actions.${destination.key}`) }}
                    </span>
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.guide-enter-active,
.guide-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}

.guide-enter-from {
  opacity: 0;
  transform: translateX(12px);
}

.guide-leave-to {
  opacity: 0;
  transform: translateX(-12px);
}

@media (prefers-reduced-motion: reduce) {
  .guide-enter-active,
  .guide-leave-active {
    transition: none;
  }
}
</style>
