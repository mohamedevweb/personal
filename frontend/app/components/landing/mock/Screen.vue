<script setup lang="ts">
/**
 * The app's own chrome, at two-thirds size.
 *
 * Every step in "how it works" is a real screen of Personal rather than a
 * diagram of one, so they all sit under the header the product actually uses:
 * the mark, the page title in the display serif, and the three controls on the
 * right with the create action in the signature. Four different screens, one
 * unmistakable product.
 */
defineProps<{ title: string }>()
</script>

<template>
  <div class="flex h-full flex-col bg-[#f4f1ea]">
    <header class="flex shrink-0 items-center gap-2.5 border-b border-[var(--b-line)] bg-[var(--b-surface)] px-4 py-3 md:px-5">
      <PersonalMark :size="14" tone="signature" class="shrink-0" />
      <p class="truncate font-display text-[17px] leading-none tracking-[-.02em]">{{ title }}</p>

      <!-- The control cluster, reproduced rather than suggested: it is the one
           part of the app that never changes from screen to screen. -->
      <div class="ml-auto flex shrink-0 items-center gap-1.5" aria-hidden="true">
        <span class="grid h-6 w-6 place-items-center rounded-full border border-[var(--b-line)] text-[var(--b-stone)]">
          <AppIcon name="settings" :size="12" />
        </span>
        <span class="grid h-6 w-6 place-items-center rounded-full border border-[var(--b-line)] text-[var(--b-stone)]">
          <AppIcon name="user" :size="12" />
        </span>
        <span class="b-btn-red grid h-6 w-6 place-items-center rounded-full">
          <AppIcon name="plus" :size="13" />
        </span>
      </div>
    </header>

    <div class="min-h-0 flex-1 p-4 md:p-5">
      <slot />
    </div>
  </div>
</template>
