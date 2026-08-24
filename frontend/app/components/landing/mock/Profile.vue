<script setup lang="ts">
/**
 * 01 — connect once, and Personal reads you.
 *
 * One object: the field you hand over your account in. It fills itself in, the
 * button presses, and what came back arrives as three words. Three words is
 * the whole claim — naming your niche, your tone and your audience back to you
 * is the proof that something was read, and a paragraph saying so would be
 * weaker than the words themselves.
 */
const { t } = useI18n()

const live = useScreenLive()

const READS = ['niche', 'tone', 'audience'] as const
const handle = computed(() => t('personal.handle.placeholder'))

// The finished state, so the server and a visitor without JS get a field that
// is filled and a read that came back. The typing is an embellishment the
// client adds by rewinding to the start.
const typed = ref(handle.value)
const pressed = ref(true)
const posts = useCountUp(40, live, 1500)

let timer: ReturnType<typeof setInterval> | null = null
let press: ReturnType<typeof setTimeout> | null = null

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

  typed.value = ''
  pressed.value = false

  watch(live, (on) => {
    if (!on || typed.value) return

    let at = 0
    timer = setInterval(() => {
      at += 1
      typed.value = handle.value.slice(0, at)

      if (at < handle.value.length) return
      if (timer) clearInterval(timer)
      press = setTimeout(() => { pressed.value = true }, 420)
    }, 55)
  }, { immediate: true })
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (press) clearTimeout(press)
})
</script>

<template>
  <LandingMockStage :note="$t('landing.how.profile.source', { count: posts })">
    <!-- The field, at the size it is actually typed into. -->
    <div class="flex items-center gap-2 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] p-1.5 pl-5 shadow-[0_12px_30px_-22px_rgba(23,23,21,.6)]">
      <span class="min-w-0 flex-1 truncate text-[15px] tracking-[-.01em]">
        {{ typed }}<span v-if="!pressed" class="b-caret" aria-hidden="true" />
      </span>

      <LandingMockAction class="b-btn-red inline-flex h-9 shrink-0 items-center rounded-full px-4 text-[13px] font-medium">
        {{ $t('landing.how.profile.connect') }}
      </LandingMockAction>
    </div>

    <!-- What came back. Three words, in the order the app writes them. -->
    <div class="mt-7 flex flex-wrap justify-center gap-2">
      <span
        v-for="(read, index) in READS"
        :key="read"
        class="b-chip transition-all duration-500"
        :class="pressed ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'"
        :style="{ transitionDelay: `${index * 130}ms` }"
      >
        <AppIcon name="check" :size="12" class="text-[var(--b-red-600)]" />
        {{ $t(`landing.how.profile.reads.${read}`) }}
      </span>
    </div>
  </LandingMockStage>
</template>
