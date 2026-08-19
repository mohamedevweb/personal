<script setup lang="ts">
/**
 * The heading block every numbered section shares: a step marker on a hairline,
 * the display headline, and an optional lede. Keeping it in one place is what
 * makes the four sections read as one sequence rather than four features.
 */
withDefaults(defineProps<{
  step?: string
  eyebrow: string
  title: string
  lede?: string
  align?: 'left' | 'center'
}>(), { align: 'left' })
</script>

<template>
  <div :class="align === 'center' ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'">
    <p class="flex items-center gap-4" :class="align === 'center' ? 'justify-center' : ''">
      <span v-if="step" class="b-eyebrow tabular-nums text-[var(--b-signature)]">{{ step }}</span>
      <span v-if="step" class="h-px w-10 bg-[var(--b-line)]" aria-hidden="true" />
      <span class="b-eyebrow">{{ eyebrow }}</span>
    </p>

    <!-- The italic emphasis lives in the translation, so each language can put
         the turn of the sentence in the right place. -->
    <h2
      class="mt-7 font-display text-[34px] leading-[1.06] tracking-[-.025em] sm:text-[44px] md:text-[54px]"
      v-html="title"
    />

    <p v-if="lede" class="mt-6 text-[16px] leading-[1.7] text-[var(--b-stone)] md:text-[17px]" :class="align === 'center' ? 'mx-auto max-w-xl' : 'max-w-xl'">
      {{ lede }}
    </p>
  </div>
</template>
