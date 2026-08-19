<script setup lang="ts">
const STEPS = ['understand', 'discover', 'connect', 'create'] as const
</script>

<template>
  <section id="how" class="scroll-mt-24 border-y border-[var(--b-line)] bg-[#f2efe8] px-6 py-24 md:px-10 md:py-32">
    <div class="mx-auto max-w-[1200px]">
      <div data-reveal class="max-w-2xl">
        <p class="b-eyebrow">{{ $t('landing.loop.eyebrow') }}</p>
        <h2 class="mt-6 font-display text-[32px] leading-[1.08] tracking-[-.025em] md:text-[44px]">
          {{ $t('landing.loop.title') }}
        </h2>
      </div>

      <ol class="mt-16 grid gap-px border-t border-[var(--b-line)] md:mt-20 md:grid-cols-4">
        <li
          v-for="(step, index) in STEPS"
          :key="step"
          data-reveal
          :style="{ '--reveal-delay': `${index * 90}ms` }"
          class="border-b border-[var(--b-line)] py-8 md:border-b-0 md:border-r md:pr-8 md:last:border-r-0"
        >
          <p class="b-eyebrow tabular-nums text-[var(--b-signature)]">{{ String(index + 1).padStart(2, '0') }}</p>
          <h3 class="mt-5 font-display text-[26px] leading-none tracking-[-.02em] md:text-[30px]">
            {{ $t(`landing.loop.steps.${step}.title`) }}
          </h3>
          <p class="mt-4 max-w-[22rem] text-[14.5px] leading-[1.6] text-[var(--b-stone)]">
            {{ $t(`landing.loop.steps.${step}.body`) }}
          </p>
        </li>
      </ol>
    </div>
  </section>
</template>
