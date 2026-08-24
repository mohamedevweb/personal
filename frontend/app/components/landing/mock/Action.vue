<script setup lang="ts">
/**
 * Every control drawn inside a mock screen is a real one.
 *
 * The screens in "how it works" are the product, not pictures of it, so the
 * buttons on them behave: press any of them and you land on the door. One
 * component holds that rule, so no mock can quietly grow a control that goes
 * nowhere — and so the destination is changed in one place if it ever moves.
 *
 * The shape stays the caller's business: it passes the same classes the app
 * uses for that button, and this only adds the link, the focus ring and the
 * small press.
 */
withDefaults(defineProps<{ label?: string }>(), { label: undefined })
</script>

<template>
  <NuxtLink
    to="/login"
    :aria-label="label"
    class="b-focus cursor-pointer transition-transform duration-200 active:translate-y-px"
  >
    <slot />
  </NuxtLink>
</template>
