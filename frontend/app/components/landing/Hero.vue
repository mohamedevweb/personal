<script setup lang="ts">
/**
 * One claim, one line, one loop of the product working. The frame underneath
 * carries the explanation the copy used to carry, and the three readouts pinned
 * to it are the only numbers above the fold — each one a real thing Personal
 * found that morning.
 */
</script>

<template>
  <section class="relative overflow-hidden px-5 md:px-10">
    <!-- Ground: red light thrown from the top edge, over a halftone that dies
         out before it reaches the copy. -->
    <div class="b-dawn pointer-events-none absolute inset-x-0 top-0 h-[880px]" aria-hidden="true" />
    <div class="b-dots b-fade-radial pointer-events-none absolute inset-x-0 top-0 h-[720px] opacity-[.55]" aria-hidden="true" />

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
          <!-- The turn in the sentence is the negative, so it gets the italic
               and the signature. It is the only red the headline needs. -->
          <span class="block italic text-[var(--b-red-600)]">{{ $t('landing.hero.titleLineTwo') }}</span>
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
          <span class="h-[3px] w-[3px] rounded-full bg-[var(--b-red-400)]" aria-hidden="true" />
          <span>{{ $t('landing.hero.reassuranceTwo') }}</span>
        </p>
      </div>

      <!-- Deliberately low, so the fold cuts through it. -->
      <div data-reveal class="relative mx-auto mt-16 max-w-[960px] md:mt-24" style="--reveal-delay:280ms">
        <div class="b-glow-blob pointer-events-none absolute -inset-x-16 -bottom-8 top-16 -z-10 opacity-70" aria-hidden="true" />

        <LandingMedia :src="LANDING_CLIPS.hero" :label="$t('landing.hero.mediaLabel')">
          <LandingMockBrief />
        </LandingMedia>

        <!-- Readouts pinned to the frame. They hang off the edges on a desktop
             and are folded into the frame itself on a phone, where there is no
             margin to hang anything in. -->
        <LandingReadout
          class="absolute -left-6 top-[26%] hidden lg:flex"
          :label="$t('landing.hero.readouts.outlier.label')"
          :value="$t('landing.hero.readouts.outlier.value')"
          :note="$t('landing.hero.readouts.outlier.note')"
          style="--reveal-delay:520ms"
        />
        <LandingReadout
          class="absolute -right-8 bottom-[22%] hidden lg:flex"
          :label="$t('landing.hero.readouts.match.label')"
          :value="$t('landing.hero.readouts.match.value')"
          :note="$t('landing.hero.readouts.match.note')"
          style="--reveal-delay:640ms"
        />
      </div>
    </div>
  </section>
</template>
