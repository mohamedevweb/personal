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
const normal = computed(() => {
  const against = benchmark.value
  if (!against) return null

  const parts = [
    against.views ? t('performance.normalViews', { count: compactNumber(against.views) }) : null,
    against.engagement ? t('performance.normalEngagement', { count: compactNumber(against.engagement) }) : null,
  ].filter(Boolean)

  return parts.length > 0 ? t('performance.normal', { parts: parts.join(' · ') }) : null
})
// Without views there is nothing to weight, so the ratio is engagement alone —
// which is a different formula and worth admitting rather than papering over.
const formula = computed(() => (benchmark.value && !benchmark.value.views ? t('performance.formulaEngagement') : t('performance.formulaBoth')))
</script>

<template>
  <div class="space-y-2 text-[12.5px] leading-[1.5] text-[var(--muted)]">
    <p>{{ comparison }}</p>
    <p v-if="normal" class="text-[var(--ink)]">{{ normal }}</p>
    <p>{{ formula }}</p>
    <p>{{ $t('performance.median') }}</p>
    <p class="text-[var(--faint)]">{{ $t('performance.floor') }}</p>
  </div>
</template>
