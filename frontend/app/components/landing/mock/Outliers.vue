<script setup lang="ts">
/** 02 — posts beating the account that published them, not the biggest posts. */
const { t } = useI18n()

// The trace under each hook is that post's first 48 hours. The shape is the
// point: an outlier is still climbing when an ordinary post has flattened.
const CURVES = {
  one: [4, 9, 18, 34, 55, 74, 88, 100],
  two: [6, 16, 31, 48, 63, 74, 80, 84],
  three: [9, 22, 38, 52, 60, 65, 67, 68]
} as const

const items = computed(() => (['one', 'two', 'three'] as const).map((key, index) => ({
  key,
  hook: t(`landing.how.outliers.items.${key}.hook`),
  views: t('landing.how.outliers.views', { count: t(`landing.how.outliers.items.${key}.views`) }),
  ratio: t(`landing.how.outliers.items.${key}.ratio`),
  curve: CURVES[key],
  lead: index === 0
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
  <div class="p-6 md:p-8">
    <div class="flex items-start justify-between gap-6">
      <p class="b-mono flex items-center gap-2.5 text-[var(--b-red-600)]">
        <span class="b-live" aria-hidden="true" />
        {{ $t('landing.how.outliers.label') }}
      </p>
      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.outliers.window') }}</p>
    </div>

    <ul class="mt-6 divide-y divide-[var(--b-line-soft)] border-t border-[var(--b-line-soft)]">
      <li v-for="item in items" :key="item.key" class="flex items-center gap-4 py-4">
        <div class="min-w-0 flex-1">
          <p class="text-[14.5px] leading-[1.45] tracking-[-.01em]">{{ item.hook }}</p>
          <p class="mt-1.5 text-[12.5px] text-[var(--b-stone)]">{{ item.views }}</p>
        </div>

        <svg
          class="hidden h-6 w-24 shrink-0 sm:block"
          viewBox="0 0 96 24"
          fill="none"
          preserveAspectRatio="none"
          aria-hidden="true"
        >
          <path
            :d="trace(item.curve)"
            :stroke="item.lead ? 'var(--b-red-500)' : '#cdc6b8'"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <circle
            :cx="96"
            :cy="24 - (item.curve[item.curve.length - 1]! / 100) * 22"
            r="2.2"
            :fill="item.lead ? 'var(--b-red-500)' : '#cdc6b8'"
          />
        </svg>

        <span
          class="b-metric shrink-0 rounded-full px-2.5 py-1 text-[12.5px] font-medium"
          :class="item.lead
            ? 'bg-[var(--b-red-500)] text-white'
            : 'border border-[var(--b-red-200)] bg-[var(--b-red-100)]'"
        >
          {{ item.ratio }}×
        </span>
      </li>
    </ul>

    <p class="b-mono mt-5 flex items-center justify-between text-[var(--b-stone)]">
      <span>{{ $t('landing.how.outliers.baseline') }}</span>
      <span class="text-[var(--b-red-600)]">{{ $t('landing.how.outliers.more') }}</span>
    </p>
  </div>
</template>
