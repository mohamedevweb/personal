<script setup lang="ts">
/**
 * The section the rest of the page exists to set up: a pattern that is working
 * and a thing that happened to you, held side by side until they fit.
 */
</script>

<template>
  <section class="px-6 py-24 md:px-10 md:py-40">
    <div class="mx-auto max-w-[1200px]">
      <LandingStepHeading
        data-reveal
        align="center"
        :step="$t('landing.connection.step')"
        :eyebrow="$t('landing.connection.eyebrow')"
        :title="$t('landing.connection.title')"
        :lede="$t('landing.connection.lede')"
      />

      <div class="mt-20 grid items-stretch gap-6 md:mt-24 md:grid-cols-[1fr_auto_1fr] md:gap-0">
        <article data-reveal class="b-panel flex flex-col justify-between p-7 md:p-9">
          <p class="b-eyebrow">{{ $t('landing.connection.patternLabel') }}</p>
          <p class="font-display mt-6 text-[24px] leading-[1.25] tracking-[-.015em] md:text-[28px]">
            {{ $t('landing.connection.patternQuote') }}
          </p>
          <p class="mt-6 text-[13.5px] leading-[1.5] text-[var(--b-stone)]">{{ $t('landing.connection.patternMeta') }}</p>
        </article>

        <!-- The join. Two hairlines draw inward and the mark lands between them. -->
        <div data-reveal class="b-connector flex flex-col items-center justify-center md:w-[132px] md:flex-row" style="--reveal-delay:220ms" aria-hidden="true">
          <span class="b-joint" />
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] text-[var(--b-signature)]">
            <PersonalMark :size="17" />
          </span>
          <span class="b-joint" />
        </div>

        <article data-reveal class="b-panel flex flex-col justify-between p-7 md:p-9" style="--reveal-delay:110ms">
          <p class="b-eyebrow">{{ $t('landing.connection.momentLabel') }}</p>
          <p class="font-display mt-6 text-[24px] leading-[1.25] tracking-[-.015em] md:text-[28px]">
            {{ $t('landing.connection.momentQuote') }}
          </p>
          <p class="mt-6 text-[13.5px] leading-[1.5] text-[var(--b-stone)]">{{ $t('landing.connection.momentMeta') }}</p>
        </article>
      </div>

      <blockquote data-reveal class="mx-auto mt-16 max-w-3xl text-center md:mt-20" style="--reveal-delay:340ms">
        <p class="font-display text-[27px] leading-[1.2] tracking-[-.02em] text-balance md:text-[40px]">
          {{ $t('landing.connection.verdict') }}
        </p>
        <footer class="b-eyebrow mt-7">{{ $t('landing.connection.attribution') }}</footer>
      </blockquote>
    </div>
  </section>
</template>
