<script setup lang="ts">
// The bar floats rather than spanning the page: it is a pill sitting on the
// composition, so the hero reads edge to edge underneath it. Off the top of the
// page it is glass and hairline; at the very top it carries nothing at all, so
// the headline is the first thing drawn.
const lifted = ref(false)
function onScroll() { lifted.value = window.scrollY > 12 }

// The hero is the one dark ground on the page, so the bar has to invert while
// it is over it. It watches the section rather than guessing at a scroll
// distance, which means the two can never drift apart.
const onNight = ref(false)
let observer: IntersectionObserver | null = null

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })

  const ground = document.getElementById('hero')
  if (!ground) return

  observer = new IntersectionObserver(([entry]) => {
    onNight.value = Boolean(entry?.isIntersecting)
  }, { rootMargin: '-76px 0px 0px 0px', threshold: 0 })

  observer.observe(ground)
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
  observer?.disconnect()
})
</script>

<template>
  <!-- The header carries the same rail as every section: identical outer padding
       and the same 1200 column. The pill is then pulled outward by exactly its
       own padding plus its border, which makes the pill's *content* box the
       rail — so the wordmark lands on the same left edge as the section
       headings and the footer logo, with the pill hanging past it on both
       sides. -->
  <header class="pointer-events-none sticky top-0 z-50 px-5 pt-3 md:px-10 md:pt-5">
    <div class="mx-auto max-w-[1200px]">
      <div
        class="pointer-events-auto -mx-3 flex h-[62px] items-center justify-between rounded-full px-[11px] transition-all duration-500 md:-mx-7 md:px-[27px]"
        :class="[
          lifted
            ? (onNight
              ? 'border border-white/10 bg-[rgba(17,15,13,.72)] shadow-[0_18px_44px_-30px_rgba(0,0,0,.9)] backdrop-blur-xl'
              : 'border border-[var(--b-line)] bg-[color-mix(in_srgb,var(--b-ivory)_86%,transparent)] shadow-[0_18px_44px_-32px_rgba(23,23,21,.55)] backdrop-blur-xl')
            : 'border border-transparent bg-transparent',
          onNight ? 'text-[var(--b-ivory)]' : 'text-[var(--b-black)]'
        ]"
      >
        <NuxtLink to="/" class="b-focus -m-2 shrink-0 p-2" :aria-label="$t('landing.nav.home')">
          <PersonalLogo :size="25" :tone="onNight ? 'signature-lit' : 'signature'" />
        </NuxtLink>

        <div class="flex shrink-0 items-center gap-1.5">
          <LanguageSwitcher :variant="onNight ? 'dark' : 'light'" class="mr-1 hidden sm:inline-flex" />
          <NuxtLink
            to="/login"
            class="b-focus hidden px-3 py-2 text-[13.5px] transition-colors sm:inline-flex"
            :class="onNight ? 'text-white/55 hover:text-white' : 'text-[var(--b-stone)] hover:text-[var(--b-black)]'"
          >
            {{ $t('landing.nav.signIn') }}
          </NuxtLink>
          <LandingButtonLink to="/login?mode=register">
            {{ $t('landing.nav.getAccess') }}
          </LandingButtonLink>
        </div>
      </div>
    </div>
  </header>
</template>
