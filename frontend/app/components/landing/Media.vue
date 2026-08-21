<script setup lang="ts">
/**
 * A product clip in a frame. The mock passed in the slot is the poster: it
 * renders on the server, holds the layout, and stays visible whenever the clip
 * is missing, still loading, or unwanted because the visitor asked for reduced
 * motion.
 *
 * Clips only run while they are on screen. Five autoplaying loops on one page
 * is a battery bill nobody agreed to.
 */
const props = defineProps<{
  src?: string | null
  label: string
}>()

const frame = ref<HTMLElement | null>(null)
const video = ref<HTMLVideoElement | null>(null)
const playable = ref(false)

let observer: IntersectionObserver | null = null

watch(() => props.src, () => { playable.value = false })

onMounted(() => {
  if (!frame.value || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const el = video.value
      if (!el) return
      if (entry.isIntersecting) void el.play().catch(() => {})
      else el.pause()
    })
  }, { threshold: 0.25 })

  observer.observe(frame.value)
})

onUnmounted(() => observer?.disconnect())
</script>

<template>
  <figure ref="frame" class="b-panel b-lift relative overflow-hidden">
    <figcaption class="sr-only">{{ label }}</figcaption>

    <div class="transition-opacity duration-700" :class="playable ? 'opacity-0' : 'opacity-100'">
      <slot />
    </div>

    <video
      v-if="src"
      ref="video"
      :key="src"
      :src="src"
      muted
      loop
      playsinline
      preload="metadata"
      aria-hidden="true"
      class="absolute inset-0 h-full w-full object-cover transition-opacity duration-700"
      :class="playable ? 'opacity-100' : 'opacity-0'"
      @canplay="playable = true"
      @error="playable = false"
    />
  </figure>
</template>
