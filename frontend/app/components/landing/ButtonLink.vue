<script setup lang="ts">
/** The only button shape on the launch page: a pill, in three weights. */
withDefaults(defineProps<{
  to: string
  variant?: 'primary' | 'ghost' | 'light'
  size?: 'md' | 'lg'
}>(), { variant: 'primary', size: 'md' })

const VARIANTS = {
  primary: 'bg-[var(--b-black)] text-[var(--b-ivory)] hover:bg-black',
  ghost: 'border border-[var(--b-line)] text-[var(--b-black)] hover:border-[#d6cfc0] hover:bg-[var(--b-surface)]',
  light: 'bg-[var(--b-ivory)] text-[var(--b-black)] hover:bg-white'
}

const SIZES = {
  md: 'h-11 px-5 text-[14px]',
  lg: 'h-[54px] px-7 text-[15px]'
}
</script>

<template>
  <NuxtLink
    :to="to"
    class="b-focus inline-flex items-center justify-center gap-2 rounded-full font-medium transition-colors duration-300"
    :class="[VARIANTS[variant], SIZES[size]]"
  >
    <slot />
  </NuxtLink>
</template>
