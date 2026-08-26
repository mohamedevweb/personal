<script setup lang="ts">
/**
 * 02 — one post beating the account that published it.
 *
 * The feed shows dozens. The step only has to make one point, so it shows one
 * post, and the only number on it is the one nobody else reports: how far past
 * its own creator's average it went. The ratio climbs to it rather than being
 * printed, because the claim is that Personal worked it out.
 *
 * The frame is deliberately Instagram's — gradient ring, square crop, the four
 * actions in their usual order — so nobody has to be told where the post came
 * from. The one thing that is not Instagram's is the line where the caption
 * would sit: that spot belongs to the number Personal added.
 *
 * Two cards sit behind, blank and dimmed. They say "there are more" without
 * spending a word on it.
 */
const { locale } = useI18n()

const live = useScreenLive()

// Tenths, so the count can arrive on a decimal the way the app reports it.
const ratio = useCountUp(84, live, 1300)
const shown = computed(() => (ratio.value / 10).toLocaleString(locale.value, {
  minimumFractionDigits: 1,
  maximumFractionDigits: 1
}))

const WASH = 'radial-gradient(78% 62% at 28% 18%, rgba(255, 255, 255, .72) 0%, rgba(255, 255, 255, 0) 62%), linear-gradient(146deg, rgba(233, 227, 214, .9) 0%, rgba(176, 166, 148, .75) 54%, rgba(104, 96, 82, .6) 100%)'
</script>

<template>
  <LandingMockStage>
    <div class="relative mx-auto w-[244px]">
      <!-- The rest of the morning's feed, stacked behind and out of the way. -->
      <span
        v-for="depth in 2"
        :key="depth"
        class="absolute inset-x-0 top-0 h-full rounded-[18px] border border-[var(--b-line)] bg-[var(--b-surface)]"
        :style="{ transform: `translateY(${depth * 10}px) scale(${1 - depth * 0.045})`, opacity: 0.5 - depth * 0.14 }"
        aria-hidden="true"
      />

      <LandingMockAction class="relative block overflow-hidden rounded-[18px] border border-[var(--b-line)] bg-[var(--b-surface)] text-left shadow-[0_22px_44px_-30px_rgba(23,23,21,.7)] transition-transform duration-300 hover:-translate-y-1">
        <div class="flex items-center gap-2.5 px-3 py-2.5">
          <span class="rounded-full bg-gradient-to-tr from-[#f9ce34] via-[#ee2a7b] to-[#6228d7] p-[2px]">
            <span class="block h-6 w-6 rounded-full border-2 border-[var(--b-surface)] bg-[#e2ddd2]" />
          </span>
          <span class="min-w-0 flex-1 truncate text-[12px] font-semibold">{{ $t('landing.how.outliers.handle') }}</span>
          <AppIcon name="dots" :size="15" class="shrink-0 text-[var(--b-stone)]" />
        </div>

        <div class="aspect-square" :style="{ backgroundImage: WASH }" />

        <!-- Instagram's own row, in Instagram's own order. -->
        <div class="flex items-center gap-3.5 px-3 pb-1.5 pt-2.5">
          <AppIcon name="heart" :size="19" :stroke-width="1.6" />
          <AppIcon name="chat" :size="19" :stroke-width="1.6" class="-scale-x-100" />
          <AppIcon name="paper-plane" :size="18" :stroke-width="1.6" />
          <AppIcon name="bookmark" :size="18" :stroke-width="1.6" class="ml-auto" />
        </div>

        <!-- Where the caption sits, the one number the step exists to show. -->
        <div class="flex items-center gap-2 px-3 pb-3 pt-1.5">
          <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-[var(--b-red-100)] px-2 py-1 text-[11px] font-semibold text-[var(--b-red-700)]">
            <AppIcon name="trend" :size="12" />
            <span class="b-metric tabular-nums">{{ shown }}×</span>
          </span>
          <span class="min-w-0 flex-1 text-[11.5px] leading-tight text-[var(--b-stone)]">
            {{ $t('landing.how.outliers.overAverage') }}
          </span>
        </div>
      </LandingMockAction>
    </div>
  </LandingMockStage>
</template>
