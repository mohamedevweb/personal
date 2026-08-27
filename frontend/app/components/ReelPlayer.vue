<script setup lang="ts">
// A reel is watched the way it is watched on Instagram: it fills its frame,
// starts on its own without sound once it is on screen, loops, and carries no
// browser chrome. Tapping toggles playback, the speaker toggles the sound.
const props = withDefaults(defineProps<{
  src: string
  poster?: string | null
  label?: string
  // A feed card crops the reel the way Instagram crops it; a page that exists to
  // study the reel shows all of it, over a blur of its own frame instead of the
  // black bars a bare <video> would leave.
  fit?: 'cover' | 'contain'
}>(), { poster: null, label: '', fit: 'cover' })

// Instagram never plays two soundtracks at once: unmuting a reel silences the
// one that had the sound before it.
const soundOwner = useState<symbol | null>('reel-sound-owner', () => null)
const id = Symbol('reel')

const root = ref<HTMLElement | null>(null)
const video = ref<HTMLVideoElement | null>(null)
// The file is only fetched once the card comes near the viewport, so a feed of
// twenty reels does not download twenty videos.
const isLoaded = ref(false)
const isPlaying = ref(false)
const isMuted = ref(true)
const hasStarted = ref(false)
const progress = ref(0)
const flashed = ref<'play' | 'pause' | null>(null)

let observer: IntersectionObserver | null = null
let flashTimer: ReturnType<typeof setTimeout> | undefined
// A reel the reader paused stays paused while it scrolls in and out of view.
let pausedByReader = false

function play() {
  const el = video.value
  if (!el) return
  el.play().then(() => { hasStarted.value = true }).catch(() => {})
}

function flash(kind: 'play' | 'pause') {
  flashed.value = kind
  clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { flashed.value = null }, 480)
}

function togglePlayback() {
  const el = video.value
  if (!el) return
  if (el.paused) {
    pausedByReader = false
    play()
    flash('play')
  }
  else {
    pausedByReader = true
    el.pause()
    flash('pause')
  }
}

function toggleSound() {
  const el = video.value
  if (!el) return
  isMuted.value = !isMuted.value
  el.muted = isMuted.value
  soundOwner.value = isMuted.value ? null : id
  // Turning the sound on is also a request to watch: a paused reel resumes.
  if (!isMuted.value && el.paused) {
    pausedByReader = false
    play()
  }
}

function trackProgress() {
  const el = video.value
  if (!el || !el.duration) return
  progress.value = Math.min(100, (el.currentTime / el.duration) * 100)
}

watch(soundOwner, (owner) => {
  if (owner === id || isMuted.value) return
  isMuted.value = true
  if (video.value) video.value.muted = true
})

onMounted(() => {
  if (!root.value || typeof IntersectionObserver === 'undefined') {
    isLoaded.value = true
    return
  }

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (entry.intersectionRatio > 0) isLoaded.value = true
      const el = video.value
      if (!el) continue

      if (entry.intersectionRatio >= 0.55) {
        if (!reducedMotion && !pausedByReader && el.paused) play()
      }
      else if (!el.paused) {
        el.pause()
        // The reel leaving the screen gives its sound back rather than keeping
        // it hostage.
        if (soundOwner.value === id) soundOwner.value = null
      }
    }
  }, { threshold: [0, 0.55], rootMargin: '200px 0px' })

  observer.observe(root.value)
})

onBeforeUnmount(() => {
  observer?.disconnect()
  clearTimeout(flashTimer)
  if (soundOwner.value === id) soundOwner.value = null
})
</script>

<template>
  <div ref="root" class="group/reel absolute inset-0 bg-black">
    <img
      v-if="props.poster && props.fit === 'contain'"
      :src="props.poster"
      alt=""
      class="absolute inset-0 h-full w-full scale-110 object-cover opacity-60 blur-2xl"
    >
    <img
      v-if="props.poster && !hasStarted"
      :src="props.poster"
      alt=""
      class="absolute inset-0 h-full w-full"
      :class="props.fit === 'contain' ? 'object-contain' : 'object-cover'"
    >
    <video
      ref="video"
      :src="isLoaded ? props.src : undefined"
      :poster="props.poster || undefined"
      muted
      loop
      playsinline
      webkit-playsinline
      disablepictureinpicture
      preload="none"
      class="absolute inset-0 h-full w-full"
      :class="props.fit === 'contain' ? 'object-contain' : 'object-cover'"
      @playing="isPlaying = true; hasStarted = true"
      @pause="isPlaying = false"
      @timeupdate="trackProgress"
    />

    <!-- The whole frame is the play/pause target, the way the video itself is on
         Instagram. -->
    <button
      type="button"
      class="absolute inset-0 h-full w-full cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-white"
      :aria-label="props.label || $t('reel.play')"
      @click.stop.prevent="togglePlayback"
    />

    <!-- Instagram flashes the state you just asked for, then gets out of the way. -->
    <span
      class="pointer-events-none absolute inset-0 flex items-center justify-center transition duration-300"
      :class="flashed ? 'scale-100 opacity-100' : 'scale-125 opacity-0'"
    >
      <span class="flex h-14 w-14 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm">
        <AppIcon :name="flashed === 'pause' ? 'pause' : 'play'" :size="26" filled />
      </span>
    </span>

    <!-- Before the first frame plays there is nothing to look at but the poster,
         so the play hint stays until playback starts. -->
    <span
      v-if="!isPlaying && !flashed"
      class="pointer-events-none absolute inset-0 flex items-center justify-center"
    >
      <span class="flex h-12 w-12 items-center justify-center rounded-full bg-black/35 text-white backdrop-blur-sm">
        <AppIcon name="play" :size="22" filled />
      </span>
    </span>

    <button
      type="button"
      class="absolute bottom-3 right-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/65 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
      :aria-label="isMuted ? $t('reel.unmute') : $t('reel.mute')"
      @click.stop.prevent="toggleSound"
    >
      <AppIcon :name="isMuted ? 'sound-off' : 'sound-on'" :size="15" />
    </button>

    <span class="pointer-events-none absolute inset-x-0 bottom-0 h-[3px] bg-white/25">
      <i class="block h-full bg-white/90 transition-[width] duration-150 ease-linear" :style="{ width: `${progress}%` }" />
    </span>
  </div>
</template>
