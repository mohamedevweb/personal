<script setup lang="ts">
/**
 * 01 — what Personal understood about you, in its own words.
 *
 * This is the app's Personal screen: the memory card at the top, then the
 * field grid underneath it. In the product the values start as "à compléter"
 * and fill in as the account is read; here they are filled, because the step
 * being illustrated is the moment they arrive.
 */
const FIELDS = ['positioning', 'audience', 'niche', 'tone'] as const
</script>

<template>
  <LandingMockScreen :title="$t('landing.how.screens.personal')">
    <!-- The memory card. Same shape as the app: accent tile, eyebrow, the one
         line telling you the profile is yours to argue with. -->
    <div class="flex items-start gap-3.5 rounded-[14px] border border-[var(--b-line)] bg-[var(--b-surface)] p-4">
      <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[10px] bg-[var(--b-red-100)] text-[var(--b-red-700)]">
        <AppIcon name="user" :size="16" />
      </span>

      <div class="min-w-0 flex-1">
        <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.profile.label') }}</p>
        <p class="mt-2 text-[13px] leading-[1.5] text-[var(--b-stone)]">{{ $t('landing.how.profile.correct') }}</p>
      </div>

      <span class="hidden shrink-0 rounded-full border border-[var(--b-line)] bg-[var(--b-ivory)] px-3.5 py-2 text-[12.5px] sm:block">
        {{ $t('landing.how.profile.edit') }}
      </span>
    </div>

    <!-- The field grid, drawn the way the app draws it: one bordered card,
         hairlines between the cells, the label above the value. -->
    <dl class="mt-4 grid grid-cols-2 overflow-hidden rounded-[14px] border border-[var(--b-line)] bg-[var(--b-surface)]">
      <div
        v-for="(field, index) in FIELDS"
        :key="field"
        class="p-4"
        :class="[index % 2 === 0 ? 'border-r border-[var(--b-line-soft)]' : '', index < 2 ? 'border-b border-[var(--b-line-soft)]' : '']"
      >
        <dt class="b-mono text-[var(--b-stone)]">{{ $t(`landing.how.profile.${field}`) }}</dt>
        <dd class="mt-2.5 text-[14px] leading-[1.45] tracking-[-.01em]">{{ $t(`landing.how.profile.${field}Value`) }}</dd>
      </div>
    </dl>

    <p class="b-mono mt-4 flex items-center gap-2.5 text-[var(--b-stone)]">
      <span class="b-live" aria-hidden="true" />
      {{ $t('landing.how.profile.source') }}
    </p>
  </LandingMockScreen>
</template>
