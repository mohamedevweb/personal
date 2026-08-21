<script setup lang="ts">
const KEYS = ['sound', 'different', 'post', 'cost'] as const
</script>

<template>
  <section id="faq" class="scroll-mt-24 border-t border-[var(--b-line)] px-5 py-24 md:px-10 md:py-36">
    <div class="mx-auto grid max-w-[1200px] gap-12 md:grid-cols-[minmax(0,340px)_1fr] md:gap-20">
      <div data-reveal class="md:sticky md:top-32 md:self-start">
        <p class="b-mono text-[var(--b-red-600)]">{{ $t('landing.faq.eyebrow') }}</p>
        <h2 class="mt-6 font-display text-[34px] leading-[1.06] tracking-[-.025em] md:text-[44px]">
          {{ $t('landing.faq.title') }}
        </h2>
        <p class="mt-6 max-w-xs text-[15px] leading-[1.65] text-[var(--b-stone)]">
          {{ $t('landing.faq.lede') }}
        </p>
      </div>

      <div data-reveal class="border-t border-[var(--b-line)]">
        <details v-for="key in KEYS" :key="key" class="faq group border-b border-[var(--b-line)]">
          <summary class="b-focus flex cursor-pointer list-none items-start justify-between gap-8 py-6 text-[17px] leading-[1.45] tracking-[-.01em] transition-colors hover:text-[var(--b-red-700)] md:text-[18.5px]">
            {{ $t(`landing.faq.items.${key}.q`) }}
            <span
              class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[var(--b-line)] text-[var(--b-stone)] transition-all duration-300 group-open:rotate-45 group-open:border-[var(--b-red-500)] group-open:bg-[var(--b-red-500)] group-open:text-white"
              aria-hidden="true"
            >
              <AppIcon name="plus" :size="15" />
            </span>
          </summary>
          <p class="max-w-2xl border-l-2 border-[var(--b-red-200)] pb-1 pl-4 text-[15.5px] leading-[1.7] text-[var(--b-stone)]">
            {{ $t(`landing.faq.items.${key}.a`) }}
          </p>
          <div class="h-7" aria-hidden="true" />
        </details>
      </div>
    </div>
  </section>
</template>

<style scoped>
.faq summary::-webkit-details-marker { display: none; }
</style>
