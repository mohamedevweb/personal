<script setup lang="ts">
/** 02 — posts beating the account that published them, not the biggest posts. */
const { t } = useI18n()

const items = computed(() => (['one', 'two', 'three'] as const).map(key => ({
  key,
  hook: t(`landing.how.outliers.items.${key}.hook`),
  views: t('landing.how.outliers.views', { count: t(`landing.how.outliers.items.${key}.views`) }),
  ratio: t(`landing.how.outliers.items.${key}.ratio`)
})))
</script>

<template>
  <div class="p-6 md:p-8">
    <p class="b-eyebrow">{{ $t('landing.how.outliers.label') }}</p>

    <ul class="mt-6 divide-y divide-[var(--b-line-soft)] border-t border-[var(--b-line-soft)]">
      <li v-for="item in items" :key="item.key" class="flex items-start gap-4 py-4">
        <div class="min-w-0 flex-1">
          <p class="text-[14.5px] leading-[1.45] tracking-[-.01em]">{{ item.hook }}</p>
          <p class="mt-1.5 text-[12.5px] text-[var(--b-stone)]">{{ item.views }}</p>
        </div>
        <span class="shrink-0 rounded-full bg-[var(--b-signature)]/10 px-2.5 py-1 text-[12.5px] font-medium tabular-nums text-[var(--b-signature)]">
          {{ item.ratio }}×
        </span>
      </li>
    </ul>

    <p class="mt-5 text-[12.5px] text-[var(--b-stone)]">{{ $t('landing.how.outliers.baseline') }}</p>
  </div>
</template>
