<script setup lang="ts">
/**
 * 03 — a winning structure meets something only you lived.
 *
 * The source keeps the evidence that made it worth borrowing: its format,
 * reach, and lift over its creator's usual performance. The Moment stays
 * visibly separate, so the composition never implies that Personal copies the
 * source story. Only the structure crosses the join.
 */
const live = useScreenLive()

const MOMENTS = [
  { key: 'first', category: 'Failure' },
  { key: 'second', category: 'Win' }
] as const

const picked = ref(0)
const moment = computed(() => MOMENTS[picked.value]!)
const connected = ref(true)

function pick(index: number) {
  if (picked.value === index) return
  picked.value = index
}

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  connected.value = false

  watch(live, (on) => {
    if (on) connected.value = true
  }, { immediate: true })
})

const WASH = 'radial-gradient(78% 62% at 28% 18%, rgba(255, 255, 255, .72) 0%, rgba(255, 255, 255, 0) 62%), linear-gradient(146deg, rgba(233, 227, 214, .9) 0%, rgba(176, 166, 148, .75) 54%, rgba(104, 96, 82, .6) 100%)'
</script>

<template>
  <LandingMockStage>
    <!-- The source keeps its proof. This is why the format was selected. -->
    <LandingMockAction class="block w-full overflow-hidden rounded-[16px] border border-[var(--b-line)] bg-[var(--b-surface)] text-left shadow-[0_18px_38px_-32px_rgba(23,23,21,.65)]">
      <div class="flex items-center gap-3 p-3.5">
        <div class="relative h-[76px] w-[72px] shrink-0 overflow-hidden rounded-[11px]" :style="{ backgroundImage: WASH }">
          <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-[var(--b-black)] px-2 py-1 text-[9.5px] font-medium text-white">
            <AppIcon name="reel" :size="10" />
            {{ $t('landing.how.connect.source.format') }}
          </span>
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center justify-between gap-2">
            <span class="b-mono whitespace-nowrap text-[var(--b-red-600)]">{{ $t('landing.how.connect.source.label') }}</span>
            <span class="truncate text-[10.5px] text-[var(--b-stone)]">@{{ $t('landing.how.outliers.handle') }}</span>
          </div>
          <p class="mt-2 font-display text-[16px] leading-[1.2] tracking-[-.01em]">
            {{ $t('landing.how.connect.patternQuote') }}
          </p>
          <div class="mt-2.5 flex flex-wrap gap-1.5">
            <span class="inline-flex items-center gap-1 rounded-full bg-[var(--b-red-100)] px-2 py-1 text-[10px] font-semibold text-[var(--b-red-700)]">
              <AppIcon name="trend" :size="11" />
              {{ $t('landing.how.connect.source.outlier') }}
            </span>
            <span class="rounded-full bg-[var(--b-ivory)] px-2 py-1 text-[10px] font-medium text-[var(--b-stone)]">
              {{ $t('landing.how.connect.source.views') }}
            </span>
          </div>
        </div>
      </div>
    </LandingMockAction>

    <!-- The connector names the only thing that crosses from the winning post
         into the creator's draft: its proven structure. -->
    <div class="relative flex h-12 items-center justify-center" aria-hidden="true">
      <span class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-[var(--b-line)]" />
      <span
        class="relative inline-flex items-center gap-1.5 rounded-full border border-[var(--b-red-200)] bg-[var(--b-red-50)] px-3 py-1.5 text-[10px] font-semibold text-[var(--b-red-700)] transition-all duration-700"
        :class="connected ? 'translate-y-0 opacity-100' : '-translate-y-2 opacity-0'"
      >
        <AppIcon name="plus" :size="11" />
        {{ $t('landing.how.connect.join') }}
      </span>
    </div>

    <!-- The story remains yours. A second Moment can be tried without changing
         the winning source above. -->
    <div class="rounded-[16px] border border-[var(--b-line)] bg-[var(--b-surface)] p-4">
      <div class="flex items-center justify-between gap-3">
        <span class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.connect.momentLabel') }}</span>
        <span class="text-[10.5px] text-[var(--b-stone)]">{{ $t(`landing.how.connect.moments.${moment.key}.date`) }}</span>
      </div>
      <p class="mt-3 font-display text-[17px] leading-[1.28] tracking-[-.015em]">
        {{ $t(`landing.how.connect.moments.${moment.key}.quote`) }}
      </p>

      <div class="mt-4 flex gap-2">
        <button
          v-for="(entry, index) in MOMENTS"
          :key="entry.key"
          type="button"
          :aria-pressed="index === picked"
          class="b-focus inline-flex items-center rounded-full border px-3 py-1.5 text-[11px] transition-colors duration-300"
          :class="index === picked
            ? 'border-transparent bg-[var(--b-black)] font-medium text-[var(--b-ivory)]'
            : 'border-[var(--b-line)] bg-[var(--b-surface)] text-[var(--b-stone)] hover:border-[#d6cfc0] hover:text-[var(--b-black)]'"
          @click="pick(index)"
        >
          {{ $t(`moments.categories.${entry.category}`) }}
        </button>
      </div>
    </div>

    <p class="mt-4 flex items-center justify-center gap-2 text-[11px] font-medium text-[var(--b-stone)]">
      <AppIcon name="check" :size="12" class="text-[var(--b-red-600)]" />
      {{ $t('landing.how.connect.result') }}
    </p>
  </LandingMockStage>
</template>
