<script setup lang="ts">
const { t } = useI18n()

const fields = computed(() => (['niche', 'audience', 'tone', 'positioning'] as const).map(key => ({
  key,
  label: t(`landing.understand.fields.${key}`),
  value: t(`landing.understand.fields.${key}Value`)
})))

const learned = computed(() => (['one', 'two', 'three', 'four'] as const).map((key, index) => ({
  key,
  text: t(`landing.understand.learned.${key}.text`),
  source: t(`landing.understand.learned.${key}.source`),
  // The last signal came from a Moment rather than from Instagram, which is the
  // point of the section: the picture keeps growing after the import.
  fromMoment: index === 3
})))
</script>

<template>
  <section id="product" class="scroll-mt-24 px-6 py-24 md:px-10 md:py-36">
    <div class="mx-auto max-w-[1200px]">
      <LandingStepHeading
        data-reveal
        :step="$t('landing.understand.step')"
        :eyebrow="$t('landing.understand.eyebrow')"
        :title="$t('landing.understand.title')"
        :lede="$t('landing.understand.lede')"
      />

      <div class="mt-16 grid gap-5 md:mt-20 md:grid-cols-2">
        <div data-reveal class="b-panel p-6 md:p-8">
          <p class="b-eyebrow">{{ $t('landing.understand.panelLabel') }}</p>
          <dl class="mt-7 divide-y divide-[var(--b-line-soft)]">
            <div v-for="field in fields" :key="field.key" class="flex items-baseline justify-between gap-6 py-4 first:pt-0 last:pb-0">
              <dt class="text-[14px] text-[var(--b-stone)]">{{ field.label }}</dt>
              <dd class="text-right text-[14.5px] font-medium tracking-[-.01em]">{{ field.value }}</dd>
            </div>
          </dl>
        </div>

        <div data-reveal class="b-panel p-6 md:p-8" style="--reveal-delay:110ms">
          <p class="b-eyebrow">{{ $t('landing.understand.learnedLabel') }}</p>
          <ul class="mt-7 space-y-4">
            <li v-for="(signal, index) in learned" :key="signal.key" class="flex items-baseline gap-3.5" :style="{ '--reveal-delay': `${180 + index * 110}ms` }" data-reveal>
              <span
                class="mt-[6px] h-1.5 w-1.5 shrink-0 rounded-full"
                :class="signal.fromMoment ? 'bg-[var(--b-signature)]' : 'bg-[#c9c2b4]'"
                aria-hidden="true"
              />
              <p class="text-[14.5px] leading-[1.5]">
                {{ signal.text }}
                <span class="text-[var(--b-stone)]"> — {{ signal.source }}</span>
              </p>
            </li>
          </ul>
        </div>
      </div>

      <!-- The correction exchange. Understanding you is a conversation, not a
           one-off import, so the section ends with the user talking back. -->
      <div data-reveal class="mt-5 grid gap-4 md:grid-cols-2 md:gap-5">
        <div class="flex flex-col gap-3">
          <p class="b-eyebrow">{{ $t('landing.understand.correctionLabel') }}</p>
          <p class="rounded-[16px] border border-[var(--b-line)] bg-[#f2efe8] px-5 py-4 text-[14.5px] leading-[1.5]">
            {{ $t('landing.understand.correction') }}
          </p>
        </div>
        <p class="flex items-start gap-3 self-end rounded-[16px] px-5 py-4 text-[14.5px] leading-[1.5] text-[var(--b-stone)]">
          <PersonalMark :size="15" class="mt-[3px] shrink-0 text-[var(--b-black)]" />
          {{ $t('landing.understand.correctionReply') }}
        </p>
      </div>
    </div>
  </section>
</template>
