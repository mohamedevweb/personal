<script setup lang="ts">
/**
 * The outlier idea, given a page of its own. The number is the headline; the
 * chart beside it is the definition, because "8.4×" only means something once
 * you can see the baseline it is 8.4 times bigger than.
 *
 * A real account's last fourteen posts, indexed against its own average. Fixed
 * rather than generated so the server and the client agree on the markup.
 */
const POSTS = [64, 38, 91, 52, 77, 44, 108, 61, 33, 86, 840, 49, 72, 58]
const AVERAGE = 100
// The scale is the outlier, so everything else is drawn against it. A square
// root keeps the ordinary posts legible instead of flattening them to nothing.
const MAX = Math.max(...POSTS)
const height = (v: number) => `${Math.max(4, Math.round((Math.sqrt(v) / Math.sqrt(MAX)) * 100))}%`
const outlier = POSTS.indexOf(MAX)
</script>

<template>
  <section class="b-night relative overflow-hidden px-5 py-24 text-[var(--b-ivory)] md:px-10 md:py-32">
    <div class="b-dots-lit pointer-events-none absolute inset-0 opacity-60" aria-hidden="true" />

    <div class="relative mx-auto grid max-w-[1200px] items-center gap-14 md:grid-cols-2 md:gap-20">
      <div data-reveal>
        <p class="b-mono flex items-center gap-2.5 text-[#c9a79c]">
          <span class="b-live" aria-hidden="true" />
          {{ $t('landing.stat.label') }}
        </p>

        <p class="font-display mt-6 text-[112px] leading-[.82] tracking-[-.045em] text-[var(--b-red-lit)] md:text-[176px]">
          {{ $t('landing.stat.value') }}
        </p>

        <p class="mt-8 max-w-md text-[17px] leading-[1.6] text-[#cfc7bb] md:text-[19px]">
          {{ $t('landing.stat.copy') }}
        </p>
      </div>

      <!-- Fourteen posts from one account, the eleventh of them the reason this
           section exists. -->
      <figure data-reveal class="relative" style="--reveal-delay:140ms">
        <figcaption class="b-mono mb-5 flex items-center justify-between text-[#8b8078]">
          <span>{{ $t('landing.stat.chart.title') }}</span>
          <span>{{ $t('landing.stat.chart.window') }}</span>
        </figcaption>

        <div class="relative h-[248px] rounded-[16px] border border-white/10 bg-white/[.03] px-5 pb-9 pt-5 md:h-[300px]">
          <!-- The account's own average, drawn where it actually falls. -->
          <div class="pointer-events-none absolute inset-x-5 bottom-9 top-5" aria-hidden="true">
            <div class="absolute inset-x-0" :style="{ bottom: height(AVERAGE) }">
              <div class="border-t border-dashed border-white/25" />
              <span class="b-mono absolute -top-4 right-0 text-[#8b8078]">{{ $t('landing.stat.chart.baseline') }}</span>
            </div>
          </div>

          <div class="flex h-full items-end gap-[3px] md:gap-1.5">
            <div
              v-for="(post, index) in POSTS"
              :key="index"
              class="group relative flex-1 origin-bottom rounded-t-[2px]"
              :class="index === outlier
                ? 'bg-gradient-to-t from-[var(--b-red-600)] to-[var(--b-red-lit)] shadow-[0_0_28px_rgba(255,106,77,.55)]'
                : 'bg-white/[.14]'"
              :style="{ height: height(post) }"
            />
          </div>

          <p class="b-mono absolute bottom-3.5 left-5 text-[#6f665f]">{{ $t('landing.stat.chart.axis') }}</p>
        </div>
      </figure>
    </div>
  </section>
</template>
