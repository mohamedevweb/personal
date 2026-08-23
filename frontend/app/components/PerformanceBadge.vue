<script setup lang="ts">
import type { ContentPost } from '~/types/product'

const props = defineProps<{ post: ContentPost }>()

const { t, te } = useI18n()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const popoverId = useId()

const formatLabel = computed(() => {
  const format = props.post.benchmark?.format
  return format && te(`performance.formats.${format}`) ? t(`performance.formats.${format}`) : null
})
// The chip has to name what the ratio is over. "1.5× creator average" was read
// as a verdict on the post; it is a comparison to one shape of post.
const label = computed(() => (formatLabel.value
  ? t('contentCard.average', { ratio: props.post.performance_ratio.toFixed(1), format: formatLabel.value })
  : t('contentCard.averageAccount', { ratio: props.post.performance_ratio.toFixed(1) })))

function closeOnOutside(event: MouseEvent) {
  if (!root.value?.contains(event.target as Node)) open.value = false
}

function closeOnEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') open.value = false
}

function listen(isOpen: boolean) {
  if (import.meta.server) return

  const bind = isOpen ? document.addEventListener : document.removeEventListener
  bind.call(document, 'click', closeOnOutside as EventListener)
  bind.call(document, 'keydown', closeOnEscape as EventListener)
}

watch(open, listen)

watch(() => props.post.id, () => {
  open.value = false
})

onBeforeUnmount(() => listen(false))
</script>

<template>
  <span ref="root" class="relative shrink-0">
    <button
      type="button"
      class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-[var(--accent-soft)] px-2.5 py-1.5 text-[12px] font-semibold text-[var(--accent-ink)] transition hover:brightness-[.97]"
      :aria-expanded="open"
      :aria-controls="popoverId"
      :title="$t('performance.open')"
      @click="open = !open"
    >
      <AppIcon name="trend" :size="15" />{{ label }}
      <AppIcon name="info" :size="13" class="opacity-60" />
    </button>

    <!-- Opens upward: the card clips its overflow, and there is room above the
         chip but not below it. -->
    <div
      v-if="open"
      :id="popoverId"
      role="note"
      class="absolute bottom-full left-0 z-20 mb-2 w-[min(19rem,calc(100vw-3rem))] rounded-[14px] border border-[var(--line)] bg-[var(--surface)] p-3.5 text-left shadow-[0_12px_34px_rgba(23,23,26,.14)]"
    >
      <div class="mb-2 flex items-start justify-between gap-3">
        <p class="text-[12px] font-semibold uppercase tracking-[.12em] text-[var(--faint)]">{{ $t('performance.title') }}</p>
        <button type="button" class="-mr-1 -mt-1 shrink-0 rounded-full p-1 text-[var(--faint)] transition hover:text-[var(--ink)]" :aria-label="$t('performance.close')" @click="open = false">
          <AppIcon name="close" :size="14" />
        </button>
      </div>
      <PerformanceNote :post="post" />
    </div>
  </span>
</template>
