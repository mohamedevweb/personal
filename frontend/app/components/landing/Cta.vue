<script setup lang="ts">
/** The page ends on the creator, not on the product. */
</script>

<template>
  <section class="relative overflow-hidden bg-[var(--b-black)] px-6 py-28 text-[var(--b-ivory)] md:px-10 md:py-40">
    <!-- The mark, oversized and barely there. The only ornament on the page, and
         it is the brand itself rather than a shape invented to fill space. -->
    <PersonalMark
      :size="620"
      class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-white/[.035]"
    />

    <div class="relative mx-auto max-w-3xl text-center">
      <h2 data-reveal class="font-display text-[42px] leading-[1.02] tracking-[-.03em] sm:text-[58px] md:text-[76px]">
        {{ $t('landing.cta.title') }}
      </h2>

      <p data-reveal class="mt-7 text-[19px] text-[#a8a196] md:text-[22px]" style="--reveal-delay:100ms">
        {{ $t('landing.cta.copy') }}
      </p>

      <div data-reveal class="mt-12" style="--reveal-delay:160ms">
        <LandingButtonLink to="/login" variant="light" size="lg">
          {{ $t('landing.cta.button') }}
          <AppIcon name="arrow" :size="17" />
        </LandingButtonLink>
      </div>

      <p data-reveal class="mt-7 text-[13px] text-[#7d776c]" style="--reveal-delay:200ms">
        {{ $t('landing.cta.reassurance') }}
      </p>
    </div>
  </section>
</template>
