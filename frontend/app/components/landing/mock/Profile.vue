<script setup lang="ts">
/** 01 — what Personal understood about you, in its own words. */
const FIELDS = ['niche', 'audience', 'tone', 'positioning'] as const

// How sure Personal is of each read, after 40 posts. Shown because a profile
// you cannot argue with is a profile you cannot trust.
const CONFIDENCE: Record<(typeof FIELDS)[number], number> = {
  niche: 96,
  audience: 88,
  tone: 92,
  positioning: 74
}
</script>

<template>
  <div class="p-6 md:p-8">
    <div class="flex items-start justify-between gap-6">
      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.profile.label') }}</p>
      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.profile.source') }}</p>
    </div>

    <dl class="mt-6 divide-y divide-[var(--b-line-soft)] border-t border-[var(--b-line-soft)]">
      <div v-for="field in FIELDS" :key="field" class="py-4">
        <div class="flex items-baseline justify-between gap-6">
          <dt class="shrink-0 text-[13px] text-[var(--b-stone)]">{{ $t(`landing.how.profile.${field}`) }}</dt>
          <dd class="text-right text-[14.5px] leading-[1.5] tracking-[-.01em]">{{ $t(`landing.how.profile.${field}Value`) }}</dd>
        </div>

        <!-- The confidence rail. Ink as far as Personal is sure, hairline for
             the rest of the way. -->
        <div class="mt-3 flex items-center gap-3">
          <div class="h-[3px] flex-1 overflow-hidden rounded-full bg-[var(--b-line-soft)]">
            <div
              class="h-full rounded-full bg-[#a9a294]"
              :style="{ width: `${CONFIDENCE[field]}%` }"
            />
          </div>
          <span class="b-mono w-8 text-right text-[var(--b-stone)]">{{ CONFIDENCE[field] }}</span>
        </div>
      </div>
    </dl>

    <p class="mt-5 text-[13px] leading-[1.6] text-[var(--b-stone)]">{{ $t('landing.how.profile.correct') }}</p>
  </div>
</template>
