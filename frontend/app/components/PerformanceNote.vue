<script setup lang="ts">
import type { ContentPost } from '~/types/product'
import { compactNumber } from '~/types/product'

const props = defineProps<{ post: ContentPost }>()

const { t, te } = useI18n()

// A post is compared to its own format when the account posts enough of them.
// Below that threshold the whole account stands in, and saying so is the point
// of this note: the number means nothing until you know its denominator.
const benchmark = computed(() => props.post.benchmark ?? null)
const formatLabel = computed(() => {
  const format = benchmark.value?.format
  return format && te(`performance.formats.${format}`) ? t(`performance.formats.${format}`) : null
})
const comparison = computed(() => {
  const against = benchmark.value

  if (!against?.posts) return t('performance.comparedUnknown', { username: props.post.creator.username })

  return formatLabel.value
    ? t('performance.comparedFormat', { username: props.post.creator.username, count: against.posts, format: formatLabel.value })
    : t('performance.comparedAccount', { username: props.post.creator.username, count: against.posts })
})
// The median is the denominator of the ratio, so it is shown as figures you can
// read at a glance rather than as one more sentence in a wall of them.
const stats = computed(() => {
  const against = benchmark.value
  if (!against) return []

  return [
    against.views ? { label: t('performance.metrics.views'), value: compactNumber(against.views) } : null,
    against.engagement ? { label: t('performance.metrics.engagement'), value: compactNumber(against.engagement) } : null,
  ].filter(Boolean) as { label: string, value: string }[]
})
// Without views there is nothing to weight, so the ratio is engagement alone —
// which is a different formula and worth admitting rather than papering over.
const formula = computed(() => (benchmark.value && !benchmark.value.views ? t('performance.formulaEngagement') : t('performance.formulaBoth')))
const notes = computed(() => [formula.value, t('performance.median')])
</script>

<template>
  <div class="space-y-3">
    <p class="text-[13px] leading-[1.55] text-[var(--copy)]">{{ comparison }}</p>

    <div v-if="stats.length" class="rounded-[12px] border border-[var(--line-soft)] bg-[var(--paper)] px-3.5 py-2.5">
      <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('performance.normalLabel') }}</p>
      <dl class="mt-1.5 flex flex-wrap gap-x-7 gap-y-2">
        <div v-for="stat in stats" :key="stat.label">
          <dd class="font-serif text-[22px] leading-none tabular-nums text-[var(--ink)]">{{ stat.value }}</dd>
          <dt class="mt-1 text-[11px] text-[var(--muted)]">{{ stat.label }}</dt>
        </div>
      </dl>
    </div>

    <ul class="space-y-1.5 text-[12.5px] leading-[1.5] text-[var(--muted)]">
      <li v-for="note in notes" :key="note" class="flex gap-2">
        <span class="mt-[7px] h-[3px] w-[3px] shrink-0 rounded-full bg-[var(--line)]" />
        <span>{{ note }}</span>
      </li>
    </ul>

    <!-- The feed's entry rule sits apart: it is about what you see, not about
         how this one post was scored. -->
    <p class="border-t border-[var(--line-soft)] pt-2.5 text-[11.5px] leading-[1.45] text-[var(--faint)]">{{ $t('performance.floor') }}</p>
  </div>
</template>
