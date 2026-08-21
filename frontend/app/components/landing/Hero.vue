<script setup lang="ts">
/**
 * One claim, one line, one loop of the product working. The frame underneath
 * carries the explanation the copy used to carry, and the three readouts pinned
 * to it are the only numbers above the fold — each one a real thing Personal
 * found that morning.
 */
</script>

<template>
  <section class="relative overflow-hidden px-5 pb-16 md:px-10 md:pb-24">
    <!-- Ground: red light thrown from the top edge, over a halftone that dies
         out before it reaches the copy. -->
    <div class="b-dawn pointer-events-none absolute inset-x-0 top-0 h-[880px]" aria-hidden="true" />
    <div class="b-dots b-fade-radial pointer-events-none absolute inset-x-0 top-0 h-[720px] opacity-40" aria-hidden="true" />

    <div class="relative mx-auto max-w-[1200px] pt-24 md:pt-32">
      <div class="mx-auto max-w-[54rem] text-center">
        <p data-reveal class="flex justify-center">
          <span class="b-chip">
            <span class="b-live" aria-hidden="true" />
            <span class="b-mono !text-[10px]">{{ $t('landing.hero.badge') }}</span>
          </span>
        </p>

        <h1
          data-reveal
          class="font-display mt-8 text-[46px] leading-[.98] tracking-[-.03em] sm:text-[64px] md:text-[80px] lg:text-[92px]"
          style="--reveal-delay:60ms"
        >
          <span class="block">{{ $t('landing.hero.titleLineOne') }}</span>
          <!-- The turn in the sentence is the negative, so it gets the italic.
               It is a type decision, not a colour one. -->
          <span class="block italic">{{ $t('landing.hero.titleLineTwo') }}</span>
        </h1>

        <p
          data-reveal
          class="mx-auto mt-8 max-w-[38rem] text-balance text-[17px] leading-[1.6] text-[var(--b-stone)] md:text-[19.5px]"
          style="--reveal-delay:120ms"
        >
          {{ $t('landing.hero.subtitle') }}
        </p>

        <div data-reveal class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row" style="--reveal-delay:180ms">
          <LandingButtonLink to="/login" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.getAccess') }}
            <AppIcon name="arrow" :size="17" class="transition-transform duration-300 group-hover:translate-x-1" />
          </LandingButtonLink>
          <LandingButtonLink to="#how" variant="ghost" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.seeHow') }}
          </LandingButtonLink>
        </div>

        <p data-reveal class="mt-6 flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 text-[13px] text-[var(--b-stone)]" style="--reveal-delay:220ms">
          <span>{{ $t('landing.hero.reassuranceOne') }}</span>
          <span class="h-[3px] w-[3px] rounded-full bg-[#cdc6b8]" aria-hidden="true" />
          <span>{{ $t('landing.hero.reassuranceTwo') }}</span>
        </p>
      </div>

      <!-- Deliberately low, so the fold cuts through it. -->
      <div data-reveal class="relative mx-auto mt-16 max-w-[960px] md:mt-24" style="--reveal-delay:280ms">
        <div class="b-glow-blob pointer-events-none absolute -inset-x-16 -bottom-8 top-16 -z-10" aria-hidden="true" />

        <LandingMedia :src="LANDING_CLIPS.hero" :label="$t('landing.hero.mediaLabel')">
          <LandingMockBrief />
        </LandingMedia>

      </div>

      <!-- What the frame above actually found, read off it. Three facts on one
           rail: the strip is the caption the screenshot cannot carry itself. -->
      <div
        data-reveal
        class="mx-auto mt-8 grid max-w-[960px] gap-5 sm:grid-cols-3 sm:gap-0"
        style="--reveal-delay:360ms"
      >
        <LandingReadout
          v-for="key in (['outlier', 'match', 'speed'] as const)"
          :key="key"
          :label="$t(`landing.hero.readouts.${key}.label`)"
          :value="$t(`landing.hero.readouts.${key}.value`)"
          :note="$t(`landing.hero.readouts.${key}.note`)"
        />
      </div>
    </div>
  </section>
</template>
