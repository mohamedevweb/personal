<script setup lang="ts">
/**
 * 02 — posts beating the account that published them, not the biggest posts.
 *
 * This is the app's feed: the card on the left is the one Personal cards are
 * built from — the post shown exactly as Instagram shows it, with everything
 * Personal adds sitting below the fold line. The rail on the right is the rest
 * of the morning's catch.
 */
const { t } = useI18n()

// The trace under each hook is that post's first 48 hours. The shape is the
// point: an outlier is still climbing when an ordinary post has flattened.
const CURVES = {
  two: [6, 16, 31, 48, 63, 74, 80, 84],
  three: [9, 22, 38, 52, 60, 65, 67, 68]
} as const

const rest = computed(() => (['two', 'three'] as const).map(key => ({
  key,
  hook: t(`landing.how.outliers.items.${key}.hook`),
  views: t('landing.how.outliers.views', { count: t(`landing.how.outliers.items.${key}.views`) }),
  ratio: t(`landing.how.outliers.items.${key}.ratio`),
  curve: CURVES[key]
})))

// One 96×24 box, eight samples, drawn as a path so the trace stays crisp at any
// size the frame is scaled to.
function trace(points: readonly number[]) {
  return points
    .map((value, index) => `${index === 0 ? 'M' : 'L'}${(index / (points.length - 1)) * 96},${24 - (value / 100) * 22}`)
    .join(' ')
}
</script>

<template>
  <LandingMockScreen :title="$t('landing.how.screens.feed')">
    <!-- The feed's own toolbar: which feed you are reading, and the one button
         that goes looking again. -->
    <div class="flex items-center justify-between gap-3">
      <div class="inline-flex items-center gap-1 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] p-1">
        <span class="inline-flex h-7 items-center rounded-full bg-[var(--b-black)] px-3.5 text-[12px] font-medium text-[var(--b-ivory)]">
          {{ $t('feed.forYou') }}
        </span>
        <span class="inline-flex h-7 items-center rounded-full px-3.5 text-[12px] text-[var(--b-stone)]">
          {{ $t('feed.global') }}
        </span>
      </div>

      <span class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] px-3.5 text-[12px]">
        <AppIcon name="sparkles" :size="13" />
        <span class="hidden sm:inline">{{ $t('feed.refresh') }}</span>
      </span>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,15.5rem)_1fr]">
      <!-- The card, reproduced: Instagram's own header, media, action bar and
           caption, then the fold line and the part that is Personal's. -->
      <article class="overflow-hidden rounded-[16px] border border-[var(--b-line)] bg-[var(--b-surface)] shadow-[0_1px_2px_rgba(23,23,21,.04)]">
        <header class="flex items-center gap-2.5 px-3 py-2.5">
          <span class="rounded-full bg-gradient-to-tr from-[#f9ce34] via-[#ee2a7b] to-[#6228d7] p-[2px]">
            <span class="block h-7 w-7 rounded-full border-2 border-[var(--b-surface)] bg-[#e2ddd2]" />
          </span>
          <span class="min-w-0 flex-1 leading-tight">
            <span class="block truncate text-[12px] font-semibold">
              {{ $t('landing.how.outliers.card.handle') }}
              <span class="font-normal text-[var(--b-stone)]"> · {{ $t('landing.how.outliers.card.date') }}</span>
            </span>
            <span class="block truncate text-[11px] text-[var(--b-stone)]">
              {{ $t('contentCard.followers', { count: $t('landing.how.outliers.card.followers') }) }}
            </span>
          </span>
          <AppIcon name="dots" :size="15" class="shrink-0 text-[var(--b-stone)]" />
        </header>

        <!-- Instagram's CDN refuses to be hotlinked, so the thumbnail is a
             wash rather than a borrowed photograph: light falling across a
             frame, out of focus, with the format mark on top of it. -->
        <div class="relative aspect-[4/3] bg-[#cbc2b1] [background-image:radial-gradient(78%_62%_at_28%_18%,rgba(255,255,255,.72)_0%,rgba(255,255,255,0)_62%),linear-gradient(146deg,rgba(233,227,214,.9)_0%,rgba(176,166,148,.75)_54%,rgba(104,96,82,.6)_100%)]">
          <AppIcon name="reel" :size="20" :stroke-width="1.9" class="absolute right-3 top-3 text-white drop-shadow-[0_1px_3px_rgba(0,0,0,.45)]" />
        </div>

        <div class="flex items-center gap-3.5 px-3 pb-1 pt-2.5">
          <AppIcon name="heart" :size="19" :stroke-width="1.6" />
          <AppIcon name="chat" :size="19" :stroke-width="1.6" class="-scale-x-100" />
          <AppIcon name="paper-plane" :size="19" :stroke-width="1.6" />
          <AppIcon name="bookmark" :size="19" :stroke-width="1.6" class="ml-auto" />
        </div>

        <div class="px-3 pb-3 text-[12px] leading-[17px]">
          <p class="font-semibold">{{ $t('contentCard.likes', { count: $t('landing.how.outliers.card.likes') }) }}</p>
          <p class="mt-1">
            <span class="font-semibold">{{ $t('landing.how.outliers.card.handle') }}</span>
            {{ ' ' }}{{ $t('landing.how.outliers.items.one.hook') }}
          </p>
        </div>

        <!-- Everything Personal adds on top of the post lives below this line. -->
        <div class="border-t border-[var(--b-line)] bg-[var(--b-ivory)] p-3">
          <div class="flex items-center gap-1">
            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-[var(--b-red-100)] px-1.5 py-1 text-[10px] font-semibold text-[var(--b-red-700)]">
              <AppIcon name="trend" :size="12" />
              {{ $t('contentCard.average', { ratio: $t('landing.how.outliers.items.one.ratio') }) }}
            </span>
            <span class="shrink-0 rounded-full border border-[var(--b-line)] px-1.5 py-1 text-[10px] text-[var(--b-stone)]">
              {{ $t('contentCard.views', { count: $t('landing.how.outliers.items.one.views') }) }}
            </span>
          </div>

          <div class="mt-3 grid gap-2">
            <span class="inline-flex h-8 items-center justify-center gap-1.5 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] text-[12px]">
              <AppIcon name="bookmark" :size="13" />
              {{ $t('contentCard.save') }}
            </span>
            <span class="inline-flex h-9 items-center justify-center gap-2 rounded-full bg-[var(--b-black)] text-[12.5px] font-medium text-[var(--b-ivory)]">
              {{ $t('contentCard.remixForMe') }}
              <AppIcon name="arrow" :size="13" />
            </span>
          </div>
        </div>
      </article>

      <!-- The rest of the catch, at list density. -->
      <div class="min-w-0">
        <div class="flex items-start justify-between gap-4">
          <p class="b-mono flex items-center gap-2.5 text-[var(--b-stone)]">
            <span class="b-live" aria-hidden="true" />
            {{ $t('landing.how.outliers.label') }}
          </p>
          <p class="b-mono shrink-0 text-[var(--b-stone)]">{{ $t('landing.how.outliers.window') }}</p>
        </div>

        <ul class="mt-4 divide-y divide-[var(--b-line-soft)] border-t border-[var(--b-line-soft)]">
          <li v-for="item in rest" :key="item.key" class="flex items-center gap-3 py-3.5">
            <div class="min-w-0 flex-1">
              <p class="text-[13.5px] leading-[1.4] tracking-[-.01em]">{{ item.hook }}</p>
              <p class="mt-1.5 text-[12px] text-[var(--b-stone)]">{{ item.views }}</p>
            </div>

            <svg
              class="hidden h-6 w-20 shrink-0 md:block"
              viewBox="0 0 96 24"
              fill="none"
              preserveAspectRatio="none"
              aria-hidden="true"
            >
              <path :d="trace(item.curve)" stroke="#cdc6b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <circle :cx="96" :cy="24 - (item.curve[item.curve.length - 1]! / 100) * 22" r="2.2" fill="#cdc6b8" />
            </svg>

            <span class="shrink-0 rounded-full border border-[var(--b-line)] px-2.5 py-1 text-[12px] tabular-nums text-[var(--b-stone)]">
              {{ item.ratio }}×
            </span>
          </li>
        </ul>

        <p class="b-mono mt-4 flex items-center justify-between text-[var(--b-stone)]">
          <span>{{ $t('landing.how.outliers.baseline') }}</span>
          <span>{{ $t('landing.how.outliers.more') }}</span>
        </p>
      </div>
    </div>
  </LandingMockScreen>
</template>
