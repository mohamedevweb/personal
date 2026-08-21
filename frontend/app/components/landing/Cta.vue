<script setup lang="ts">
/** The page ends on the creator, not on the product. */
const config = useRuntimeConfig()
</script>

<template>
  <section class="b-night relative overflow-hidden px-5 py-28 text-[var(--b-ivory)] md:px-10 md:py-40">
    <div class="b-dots-lit pointer-events-none absolute inset-0 opacity-40" aria-hidden="true" />

    <!-- The mark, oversized and barely there. The only ornament on the page, and
         it is the brand itself rather than a shape invented to fill space. -->
    <PersonalMark
      :size="620"
      class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-white/[.022]"
    />
    <div class="b-glow-blob pointer-events-none absolute left-1/2 top-1/2 h-[420px] w-[720px] -translate-x-1/2 -translate-y-1/2 opacity-70" aria-hidden="true" />

    <div class="relative mx-auto max-w-3xl text-center">
      <h2 data-reveal class="font-display text-[42px] leading-[1.04] tracking-[-.03em] sm:text-[58px] md:text-[74px]">
        <span class="block">{{ $t('landing.cta.title') }}</span>
        <span class="block">{{ $t('landing.cta.copy') }}</span>
      </h2>

      <div data-reveal class="mt-11 flex flex-col items-center justify-center gap-3 sm:flex-row" style="--reveal-delay:160ms">
        <LandingButtonLink to="/login" size="lg" class="w-full sm:w-auto">
          {{ $t('landing.cta.button') }}
          <AppIcon name="arrow" :size="17" class="transition-transform duration-300 group-hover:translate-x-1" />
        </LandingButtonLink>
        <LandingButtonLink :to="config.public.demoBookingUrl" variant="light" size="lg" class="w-full sm:w-auto">
          {{ $t('landing.cta.bookDemo') }}
        </LandingButtonLink>
      </div>

      <p data-reveal class="b-mono mt-8 flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-[#8b8078]" style="--reveal-delay:200ms">
        <span class="b-live" aria-hidden="true" />
        <span>{{ $t('landing.cta.reassurance') }}</span>
      </p>
    </div>
  </section>
</template>
