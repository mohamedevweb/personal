<script setup lang="ts">
/** The only button shape on the launch page: a pill, in four weights. */
withDefaults(defineProps<{
  to: string
  variant?: 'primary' | 'ghost' | 'light' | 'dark'
  size?: 'md' | 'lg'
}>(), { variant: 'primary', size: 'md' })

// Primary is the signature red, on both grounds. The page has exactly one kind
// of "yes", and it is the same colour whether it sits on ivory or on black.
const VARIANTS = {
  primary: 'b-btn-red',
  dark: 'bg-[var(--b-black)] text-[var(--b-ivory)] hover:bg-black',
  ghost: 'border border-[var(--b-line)] text-[var(--b-black)] hover:border-[var(--b-red-200)] hover:bg-[var(--b-red-50)] hover:text-[var(--b-red-700)]',
  light: 'border border-white/15 bg-white/[.06] text-[var(--b-ivory)] backdrop-blur-sm hover:border-white/25 hover:bg-white/[.1]'
}

const SIZES = {
  md: 'h-11 px-5 text-[14px]',
  lg: 'h-[54px] px-7 text-[15px]'
}
</script>

<template>
  <NuxtLink
    :to="to"
    class="b-focus group inline-flex items-center justify-center gap-2 rounded-full font-medium transition-colors duration-300"
    :class="[VARIANTS[variant], SIZES[size]]"
  >
    <slot />
  </NuxtLink>
</template>
