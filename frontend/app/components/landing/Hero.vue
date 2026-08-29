<script setup lang="ts">
/**
 * The stage. Every other section of the launch page is an argument set on
 * ivory; this one is the product's night sky. The field behind the copy is the
 * posts Personal read overnight drifting past, with the outliers lit in the
 * signature.
 *
 * One claim, centred on the field, with the two ways in under it. Nothing else
 * shares the fold.
 */
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

    <!-- The stage is a screen tall, and on a phone the copy is a good deal
         shorter than that. Rather than sit against the top with a third of the
         fold left empty under it, the block centres itself in the room it has —
         the padding still holds it clear of the bar. Wider than a phone the
         copy already fills the stage, so it keeps the top edge it was set on. -->
    <div class="relative z-10 mx-auto flex min-h-[100svh] max-w-[1200px] flex-col justify-center px-5 pb-20 pt-[136px] md:block md:min-h-0 md:px-10 md:pt-[168px]">
      <div class="mx-auto max-w-[54rem] text-center">
        <h1
          data-reveal
          data-reveal-now
          class="font-display text-[46px] leading-[.98] tracking-[-.03em] text-[var(--b-ivory)] sm:text-[64px] md:text-[80px] lg:text-[92px]"
          style="--reveal-delay:60ms"
        >
          <!-- The word the whole claim turns on carries the signature, so it
               lives inside the translation and the line renders its markup. -->
          <span class="block" v-html="$t('landing.hero.titleLineOne')" />
          <!-- The turn in the sentence is the negative, so it gets the italic.
               It is a type decision, not a colour one. -->
          <span class="block">{{ $t('landing.hero.titleLineTwo') }}</span>
        </h1>

        <p
          data-reveal
          data-reveal-now
          class="mx-auto mt-8 max-w-[38rem] text-balance text-[17px] leading-[1.6] text-white/60 md:text-[19.5px]"
          style="--reveal-delay:120ms"
        >
          {{ $t('landing.hero.subtitle') }}
        </p>

        <div data-reveal data-reveal-now class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row" style="--reveal-delay:180ms">
          <LandingButtonLink to="/login?mode=register" size="lg" class="w-full sm:w-auto">
            {{ $t('landing.hero.getAccess') }}
            <AppIcon name="arrow" :size="17" class="transition-transform duration-300 group-hover:translate-x-1" />
          </LandingButtonLink>
        </div>

        <!-- Two facts on one line where there is room for them, two lines with
             no orphaned separator between them where there is not. -->
        <p
          data-reveal
          data-reveal-now
          class="mt-6 flex flex-col items-center justify-center gap-y-1 text-[13px] text-white/45 sm:flex-row sm:gap-x-2.5"
          style="--reveal-delay:220ms"
        >
          <span>{{ $t('landing.hero.reassuranceOne') }}</span>
          <span class="hidden h-[3px] w-[3px] rounded-full bg-white/30 sm:block" aria-hidden="true" />
          <span>{{ $t('landing.hero.reassuranceTwo') }}</span>
        </p>
      </div>
    </div>
  </section>
</template>
