<script setup lang="ts">
/**
 * One claim, one line, one loop of the product working. Everything the old hero
 * explained in a paragraph is now the job of the clip underneath it.
 */
</script>

<template>
  <section class="relative px-6 md:px-10">
    <div class="mx-auto max-w-[1200px] pt-14 md:pt-24">
      <div class="mx-auto max-w-[52rem] text-center">
        <h1
          data-reveal
          class="font-display text-[44px] leading-[1.02] tracking-[-.03em] sm:text-[62px] md:text-[76px] lg:text-[86px]"
          style="--reveal-delay:60ms"
        >
          <span class="block">{{ $t('landing.hero.titleLineOne') }}</span>
          <span class="block">{{ $t('landing.hero.titleLineTwo') }}</span>
        </h1>

        <p
          data-reveal
          class="mx-auto mt-8 max-w-[36rem] text-balance text-[17px] leading-[1.65] text-[var(--b-stone)] md:text-[19px]"
          style="--reveal-delay:120ms"
        >
          {{ $t('landing.hero.subtitle') }}
        </p>

        <div data-reveal class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row" style="--reveal-delay:180ms">
          <LandingButtonLink to="/login" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.getAccess') }}
            <AppIcon name="arrow" :size="17" />
          </LandingButtonLink>
          <LandingButtonLink to="#how" variant="ghost" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.seeHow') }}
          </LandingButtonLink>
        </div>

        <p data-reveal class="mt-6 text-[13px] text-[var(--b-stone)]" style="--reveal-delay:220ms">
          {{ $t('landing.hero.reassurance') }}
        </p>
      </div>

      <!-- Deliberately low, so the fold cuts through it. -->
      <div data-reveal class="mx-auto mt-16 max-w-[940px] md:mt-24" style="--reveal-delay:280ms">
        <LandingMedia :src="LANDING_CLIPS.hero" :label="$t('landing.hero.mediaLabel')">
          <LandingMockBrief />
        </LandingMedia>
      </div>
    </div>
  </section>
</template>
