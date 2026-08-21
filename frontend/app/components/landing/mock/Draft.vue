<script setup lang="ts">
/** 04 — the winning structure, rewritten as something only you could post. */

// Each beat owns a share of the forty seconds, so the scrubber underneath is the
// actual shape of the Reel rather than four equal blocks.
const BEATS = [
  { key: 'hook', share: 18 },
  { key: 'two', share: 30 },
  { key: 'three', share: 32 },
  { key: 'close', share: 20 }
] as const
</script>

<template>
  <div class="p-6 md:p-8">
    <div class="flex items-center justify-between gap-4">
      <p class="b-mono flex items-center gap-2.5 text-[var(--b-red-600)]">
        <AppIcon name="reel" :size="14" />
        {{ $t('landing.how.draft.label') }}
      </p>
      <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.draft.voice') }}</p>
    </div>

    <!-- The scrubber. The hook is red because it is the only beat that decides
         whether the rest gets watched. -->
    <div class="mt-5 flex h-1.5 gap-1 overflow-hidden rounded-full" aria-hidden="true">
      <span
        v-for="(beat, index) in BEATS"
        :key="beat.key"
        class="rounded-full"
        :class="index === 0 ? 'bg-[var(--b-red-500)]' : 'bg-[var(--b-red-200)]'"
        :style="{ width: `${beat.share}%` }"
      />
    </div>

    <ol class="mt-5 divide-y divide-[var(--b-line-soft)] border-t border-[var(--b-line-soft)]">
      <li v-for="(beat, index) in BEATS" :key="beat.key" class="flex gap-4 py-4">
        <span
          class="b-mono mt-[3px] w-8 shrink-0"
          :class="index === 0 ? 'text-[var(--b-red-600)]' : 'text-[var(--b-stone)]'"
        >{{ String(index + 1).padStart(2, '0') }}</span>

        <div class="min-w-0 flex-1">
          <p class="b-mono" :class="index === 0 ? 'text-[var(--b-red-600)]' : 'text-[var(--b-stone)]'">
            {{ $t(`landing.how.draft.beats.${beat.key}.label`) }}
          </p>
          <p class="mt-2 text-[14.5px] leading-[1.5] tracking-[-.01em]">
            {{ $t(`landing.how.draft.beats.${beat.key}.value`) }}
          </p>
        </div>
      </li>
    </ol>

    <div class="mt-6 flex items-center gap-2.5 rounded-[12px] border border-[var(--b-red-200)] bg-[var(--b-red-50)] px-4 py-3">
      <PersonalMark :size="13" class="shrink-0 text-[var(--b-red-500)]" />
      <p class="text-[13px] leading-[1.5] text-[var(--b-red-700)]">{{ $t('landing.how.draft.source') }}</p>
    </div>
  </div>
</template>
