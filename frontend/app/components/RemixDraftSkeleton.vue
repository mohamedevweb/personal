<script setup lang="ts">
/* The draft, greyed out, in the shape it will actually take. It replaces the
   full-screen generation stage: two creators told us the animation said nothing
   they needed, so what is coming is drawn instead of described. */
withDefaults(defineProps<{
  format?: 'reel' | 'carousel' | 'caption'
  generating?: boolean
}>(), {
  format: 'carousel',
  generating: false
})
</script>

<template>
  <div class="page-shell pt-8">
    <div class="flex flex-wrap items-center gap-3">
      <div class="h-7 w-24 animate-pulse rounded-full bg-[var(--sand-soft)]" />
      <p v-if="generating" class="text-[12px] text-[var(--muted)]">{{ $t('remix.generatingEyebrow') }}</p>
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_312px] lg:gap-10">
      <div>
        <!-- The format tabs, so the page does not jump when the draft lands. -->
        <div class="flex gap-2">
          <div v-for="tab in 3" :key="tab" class="h-9 w-28 animate-pulse rounded-full bg-[var(--sand-soft)]" />
        </div>

        <div v-if="format === 'carousel'" class="mt-8 flex gap-4 overflow-hidden">
          <div v-for="slide in 3" :key="slide" class="h-64 w-48 shrink-0 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
        </div>

        <div v-else-if="format === 'reel'" class="mt-8 space-y-4">
          <div v-for="beat in 3" :key="beat" class="rounded-[18px] border border-[var(--line-soft)] p-5">
            <div class="h-4 w-20 animate-pulse rounded-full bg-[var(--sand-soft)]" />
            <div class="mt-4 space-y-2.5">
              <div class="h-3.5 w-full animate-pulse rounded bg-[var(--sand-soft)]" />
              <div class="h-3.5 w-11/12 animate-pulse rounded bg-[var(--sand-soft)]" />
              <div class="h-3.5 w-2/3 animate-pulse rounded bg-[var(--sand-soft)]" />
            </div>
          </div>
        </div>

        <div v-else class="mt-8 rounded-[18px] border border-[var(--line-soft)] p-5">
          <div class="space-y-2.5">
            <div v-for="(width, line) in ['w-full', 'w-11/12', 'w-4/5', 'w-full', 'w-5/6', 'w-2/3']" :key="line" class="h-3.5 animate-pulse rounded bg-[var(--sand-soft)]" :class="width" />
          </div>
        </div>
      </div>

      <div class="h-72 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
    </div>

    <!-- The one thing worth saying while waiting: nothing is lost by leaving. -->
    <p v-if="generating" role="status" class="mt-8 text-[12.5px] leading-5 text-[var(--faint)]">
      {{ $t('remix.generatingLeave') }}
    </p>
  </div>
</template>
