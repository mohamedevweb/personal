<script setup lang="ts">
const { t } = useI18n()

const items = computed(() => (['one', 'two', 'three'] as const).map(key => ({
  key,
  hook: t(`landing.outliers.items.${key}.hook`),
  views: t(`landing.outliers.items.${key}.views`),
  ratio: t(`landing.outliers.items.${key}.ratio`)
})))

const anatomy = computed(() => (['one', 'two', 'three', 'four'] as const)
  .map(key => t(`landing.outliers.anatomy.${key}`)))
</script>

<template>
  <section class="px-6 pb-24 md:px-10 md:pb-36">
    <div class="mx-auto max-w-[1200px]">
      <LandingStepHeading
        data-reveal
        :step="$t('landing.outliers.step')"
        :eyebrow="$t('landing.outliers.eyebrow')"
        :title="$t('landing.outliers.title')"
        :lede="$t('landing.outliers.lede')"
      />

      <div class="mt-16 md:mt-20">
        <p data-reveal class="b-eyebrow">{{ $t('landing.outliers.listLabel') }}</p>

        <ul class="mt-6 space-y-3">
          <li v-for="(item, index) in items" :key="item.key" data-reveal :style="{ '--reveal-delay': `${index * 100}ms` }">
            <!-- The first outlier is opened up: the ratio alone is a number, the
                 anatomy underneath is the reason it is worth your morning. -->
            <article v-if="index === 0" class="b-panel overflow-hidden">
              <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-start sm:gap-8 md:p-8">
                <p class="flex shrink-0 items-baseline gap-2 sm:w-[148px] sm:flex-col sm:items-start sm:gap-1">
                  <span class="font-display text-[52px] leading-none tracking-[-.03em] text-[var(--b-signature)] md:text-[64px]">{{ item.ratio }}×</span>
                  <span class="b-eyebrow">{{ $t('landing.outliers.statLabel') }}</span>
                </p>
                <div class="min-w-0 flex-1">
                  <p class="font-display text-[22px] leading-[1.25] tracking-[-.015em] md:text-[26px]">“{{ item.hook }}”</p>
                  <p class="mt-3 text-[13.5px] text-[var(--b-stone)]">
                    {{ $t('landing.outliers.views', { count: item.views }) }} · {{ $t('landing.outliers.baseline') }} · {{ $t('landing.outliers.still') }}
                  </p>
                </div>
              </div>

              <div class="border-t border-[var(--b-line-soft)] bg-[#faf8f4] px-6 py-5 md:px-8">
                <p class="b-eyebrow">{{ $t('landing.outliers.anatomyLabel') }}</p>
                <ul class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-[14px]">
                  <li v-for="(trait, traitIndex) in anatomy" :key="trait" class="flex items-center gap-3">
                    <span v-if="traitIndex" class="h-1 w-1 rounded-full bg-[#cdc6b8]" aria-hidden="true" />
                    {{ trait }}
                  </li>
                </ul>
              </div>
            </article>

            <article v-else class="b-panel flex items-center gap-5 p-5 md:px-8">
              <span class="font-display w-[148px] shrink-0 text-[26px] leading-none tracking-[-.02em] text-[var(--b-stone)]">{{ item.ratio }}×</span>
              <p class="min-w-0 flex-1 truncate text-[15px]">“{{ item.hook }}”</p>
              <p class="hidden shrink-0 text-[13px] text-[var(--b-stone)] sm:block">{{ $t('landing.outliers.views', { count: item.views }) }}</p>
            </article>
          </li>
        </ul>

        <p data-reveal class="mt-6 text-[13.5px] text-[var(--b-stone)]">{{ $t('landing.outliers.ranked') }}</p>
      </div>
    </div>
  </section>
</template>
