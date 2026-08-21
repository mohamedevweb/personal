<script setup lang="ts">
/**
 * The stage. Every other section of the launch page is an argument set on
 * ivory; this one is the product's night sky. The field behind the copy is the
 * posts Personal read overnight drifting past, with the outliers lit in the
 * signature, and the brief under the claim is what the product made of them.
 *
 * The composition is the page's original one — one claim centred, one frame of
 * the product below it, the numbers read off that frame — moved onto the field.
 * Only the field moves: the copy stays exactly where it was set.
 */

// The three facts above the fold, each one a real thing Personal found that
// morning. Same numbers the strip carried before, read straight off the night.
const STATS = [
  { key: 'outlier', icon: 'trend' },
  { key: 'match', icon: 'eye' },
  { key: 'speed', icon: 'clock' }
] as const
</script>

<template>
  <!-- The bar is sticky, so it reserves a strip of flow above the hero. The
       stage is pulled back under it: the field has to start at the very top of
       the window, and the nav floats on it rather than sitting above it. -->
  <section
    id="hero"
    class="b-night relative isolate -mt-[74px] min-h-[100svh] overflow-hidden md:-mt-[82px]"
  >
    <!-- Ground, back to front: the matrix, the column rail, the field drifting
         over both, and the light the field comes out of. The stack is numbered
         rather than left to document order: an animating canvas is promoted to
         its own compositing layer, and with every sibling on `z-index: auto`
         that layer wins and paints straight over the copy. -->
    <div class="b-dots-lit b-fade-b pointer-events-none absolute inset-0 z-0 opacity-50" aria-hidden="true" />

    <div class="pointer-events-none absolute inset-y-0 left-0 right-0 z-0 hidden lg:block" aria-hidden="true">
      <span v-for="at in [25, 50, 75]" :key="at" class="b-guide absolute inset-y-0 w-px" :style="{ left: `${at}%` }" />
    </div>

    <LandingSignalField class="pointer-events-none absolute inset-0 z-0" />

    <div class="b-floor pointer-events-none absolute inset-x-0 top-0 z-0 h-[100svh]" aria-hidden="true" />

    <div class="relative z-10 mx-auto max-w-[1200px] px-5 pb-20 pt-[136px] md:px-10 md:pt-[168px]">
      <div class="mx-auto max-w-[54rem] text-center">
        <h1
          data-reveal
          class="font-display text-[46px] leading-[.98] tracking-[-.03em] text-[var(--b-ivory)] sm:text-[64px] md:text-[80px] lg:text-[92px]"
          style="--reveal-delay:60ms"
        >
          <span class="block">{{ $t('landing.hero.titleLineOne') }}</span>
          <!-- The turn in the sentence is the negative, so it gets the italic.
               It is a type decision, not a colour one. -->
          <span class="block italic">{{ $t('landing.hero.titleLineTwo') }}</span>
        </h1>

        <p
          data-reveal
          class="mx-auto mt-8 max-w-[38rem] text-balance text-[17px] leading-[1.6] text-white/60 md:text-[19.5px]"
          style="--reveal-delay:120ms"
        >
          {{ $t('landing.hero.subtitle') }}
        </p>

        <div data-reveal class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row" style="--reveal-delay:180ms">
          <LandingButtonLink to="/login" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.getAccess') }}
            <AppIcon name="arrow" :size="17" class="transition-transform duration-300 group-hover:translate-x-1" />
          </LandingButtonLink>
          <LandingButtonLink to="#how" variant="dark" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.seeHow') }}
          </LandingButtonLink>
        </div>

        <p
          data-reveal
          class="mt-6 flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 text-[13px] text-white/45"
          style="--reveal-delay:220ms"
        >
          <span>{{ $t('landing.hero.reassuranceOne') }}</span>
          <span class="h-[3px] w-[3px] rounded-full bg-white/30" aria-hidden="true" />
          <span>{{ $t('landing.hero.reassuranceTwo') }}</span>
        </p>
      </div>

      <!-- Deliberately low, so the fold cuts through it. -->
      <div data-reveal class="relative mx-auto mt-16 max-w-[960px] md:mt-20" style="--reveal-delay:280ms">
        <div class="b-glow-blob pointer-events-none absolute -inset-x-16 -bottom-8 top-10 -z-10" aria-hidden="true" />

        <LandingMedia
          :src="LANDING_CLIPS.hero"
          :label="$t('landing.hero.mediaLabel')"
          class="!border-white/10 shadow-[0_54px_110px_-46px_rgba(0,0,0,.9)]"
        >
          <LandingMockBrief />
        </LandingMedia>

        <!-- The one control on the stage that is not a word. -->
        <a
          href="#how"
          class="b-knob b-focus absolute -bottom-5 -right-5"
          :aria-label="$t('landing.hero.openHow')"
        >
          <PersonalMark :size="19" />
        </a>
      </div>

      <!-- What the frame above actually found, read off it. Three facts on one
           rail: the strip is the caption the screenshot cannot carry itself. -->
      <div
        data-reveal
        class="mx-auto mt-14 grid max-w-[960px] gap-8 sm:grid-cols-3"
        style="--reveal-delay:360ms"
      >
        <LandingHeroStat
          v-for="stat in STATS"
          :key="stat.key"
          :icon="stat.icon"
          :label="$t(`landing.hero.readouts.${stat.key}.label`)"
          :value="$t(`landing.hero.readouts.${stat.key}.value`)"
          :note="$t(`landing.hero.readouts.${stat.key}.note`)"
        />
      </div>
    </div>
  </section>
</template>
