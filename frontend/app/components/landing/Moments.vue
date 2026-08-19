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
  <section id="moments" class="scroll-mt-24 px-6 py-24 md:px-10 md:py-36">
    <div class="mx-auto max-w-[1200px]">
      <LandingStepHeading
        data-reveal
        align="center"
        :eyebrow="$t('landing.moments.eyebrow')"
        :title="$t('landing.moments.title')"
        :lede="$t('landing.moments.lede')"
      />

      <!-- A wall of notes rather than a grid of cards: Moments arrive messy and
           short, and the layout should not pretend otherwise. -->
      <div class="mt-16 gap-5 md:mt-20 sm:columns-2 lg:columns-3 [column-fill:balance]">
        <article
          v-for="(card, index) in cards"
          :key="card.key"
          data-reveal
          :style="{ '--reveal-delay': `${index * 90}ms` }"
          class="b-panel mb-5 break-inside-avoid p-6"
        >
          <p class="b-eyebrow">{{ card.meta }}</p>

          <p class="mt-4 text-[16px] leading-[1.6]">{{ card.text }}</p>

          <span v-if="card.isVoice" class="mt-5 flex h-6 items-end gap-[3px]" aria-hidden="true">
            <i
              v-for="(bar, index) in WAVEFORM"
              :key="index"
              class="w-[2px] rounded-full bg-[#cdc6b8]"
              :style="{ height: `${bar}%` }"
            />
          </span>

          <p v-if="card.used" class="mt-5 flex items-center gap-2 text-[12.5px] text-[var(--b-signature)]">
            <PersonalMark :size="12" />
            {{ $t('landing.moments.used') }}
          </p>
        </article>
      </div>

      <p data-reveal class="mx-auto mt-12 max-w-xl text-center text-[15px] leading-[1.65] text-[var(--b-stone)]">
        {{ $t('landing.moments.note') }}
      </p>
    </div>
  </section>
</template>
