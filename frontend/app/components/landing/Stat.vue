<script setup lang="ts">
/** The outlier idea, given a page of its own. Typography does all the work. */
</script>

<template>
  <section class="bg-[var(--b-black)] px-6 py-24 text-[var(--b-ivory)] md:px-10 md:py-32">
    <div class="mx-auto grid max-w-[1200px] items-start gap-10 md:grid-cols-[auto_1fr] md:gap-20">
      <div data-reveal>
        <p class="b-eyebrow !text-[#8b857a]">{{ $t('landing.outliers.statLabel') }}</p>
        <p class="font-display mt-4 text-[104px] leading-[.85] tracking-[-.04em] md:text-[168px]">
          {{ $t('landing.outliers.statValue') }}
        </p>
      </div>

      <div data-reveal class="max-w-xl md:pt-6" style="--reveal-delay:120ms">
        <p class="text-[18px] leading-[1.6] text-[#d8d2c6] md:text-[21px]">
          {{ $t('landing.outliers.statCopy') }}
        </p>
        <p class="mt-8 border-t border-white/12 pt-8 text-[15px] leading-[1.6] text-[#8b857a]">
          {{ $t('landing.outliers.statFoot') }}
        </p>
      </div>
    </div>
  </section>
</template>
