<script setup lang="ts">
const { t } = useI18n()

// A real 40-second note, sampled once. Hard-coded rather than generated so the
// server and the client render byte-identical markup.
const WAVEFORM = [22, 40, 31, 58, 74, 46, 35, 62, 88, 71, 49, 33, 55, 80, 96, 68, 42, 29, 51, 77, 63, 38, 26, 47, 70, 44, 30, 20]

const cards = computed(() => (['one', 'two', 'three', 'four', 'five'] as const).map((key, index) => ({
  key,
  text: t(`landing.moments.cards.${key}.text`),
  meta: t(`landing.moments.cards.${key}.meta`),
  isVoice: index === 3,
  // One Moment already came back as a Reel: proof that saving them pays off.
  used: index === 1
})))
</script>

<template>
  <section id="moments" class="relative scroll-mt-24 overflow-hidden px-5 py-24 md:px-10 md:py-36">
    <div class="b-dots b-fade-radial pointer-events-none absolute inset-0 opacity-40" aria-hidden="true" />

    <div class="relative mx-auto max-w-[1200px]">
      <div data-reveal class="mx-auto max-w-2xl text-center">
        <p class="b-mono text-[var(--b-red-600)]">{{ $t('landing.moments.eyebrow') }}</p>

        <!-- The italic emphasis lives in the translation, so each language puts
             the turn of the sentence in the right place. -->
        <h2
          class="mt-7 font-display text-[36px] leading-[1.04] tracking-[-.025em] sm:text-[46px] md:text-[56px]"
          v-html="$t('landing.moments.title')"
        />

        <p class="mx-auto mt-6 max-w-xl text-[16px] leading-[1.7] text-[var(--b-stone)] md:text-[17.5px]">
          {{ $t('landing.moments.lede') }}
        </p>
      </div>

      <!-- A wall of notes rather than a grid of cards: Moments arrive messy and
           short, and the layout should not pretend otherwise. -->
      <div class="mt-16 gap-5 md:mt-20 sm:columns-2 lg:columns-3 [column-fill:balance]">
        <article
          v-for="(card, index) in cards"
          :key="card.key"
          data-reveal
          :style="{ '--reveal-delay': `${index * 90}ms` }"
          class="b-panel relative mb-5 break-inside-avoid p-6 transition-shadow duration-500 hover:shadow-[0_24px_50px_-40px_rgba(23,23,21,.55)]"
          :class="card.used ? 'border-[var(--b-red-200)] bg-[var(--b-red-50)]' : ''"
        >
          <!-- The one Moment that shipped carries a red spine, so the wall has a
               single point of arrival rather than five equal notes. -->
          <span
            v-if="card.used"
            class="absolute inset-y-6 left-0 w-[2px] rounded-full bg-[var(--b-red-500)]"
            aria-hidden="true"
          />

          <p class="b-mono" :class="card.used ? 'text-[var(--b-red-600)]' : 'text-[var(--b-stone)]'">{{ card.meta }}</p>

          <p class="mt-4 text-[16px] leading-[1.6]">{{ card.text }}</p>

          <span v-if="card.isVoice" class="mt-5 flex h-7 items-end gap-[3px]" aria-hidden="true">
            <i
              v-for="(bar, barIndex) in WAVEFORM"
              :key="barIndex"
              class="w-[2px] rounded-full"
              :class="barIndex < 11 ? 'bg-[var(--b-red-400)]' : 'bg-[#cdc6b8]'"
              :style="{ height: `${bar}%` }"
            />
          </span>

          <p v-if="card.used" class="b-chip mt-5">
            <PersonalMark :size="11" />
            {{ $t('landing.moments.used') }}
          </p>
        </article>
      </div>

      <p data-reveal class="mx-auto mt-12 max-w-xl text-center font-display text-[22px] leading-[1.35] tracking-[-.015em] md:text-[26px]">
        {{ $t('landing.moments.note') }}
      </p>
    </div>
  </section>
</template>
