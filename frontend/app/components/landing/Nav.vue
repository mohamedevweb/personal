<script setup lang="ts">
const links = [
  { hash: '#how', key: 'landing.nav.how' },
  { hash: '#moments', key: 'landing.nav.moments' },
  { hash: '#faq', key: 'landing.nav.faq' }
]

// The bar floats rather than spanning the page: it is a pill sitting on the
// composition, so the hero reads edge to edge underneath it. Off the top of the
// page it is glass and hairline; at the very top it carries nothing at all, so
// the headline is the first thing drawn.
const lifted = ref(false)
function onScroll() { lifted.value = window.scrollY > 12 }

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
  <header class="pointer-events-none sticky top-0 z-50 px-4 pt-3 md:px-8 md:pt-5">
    <div
      class="pointer-events-auto mx-auto flex h-[62px] max-w-[1160px] items-center justify-between rounded-full pl-5 pr-2 transition-all duration-500 md:pl-7"
      :class="lifted
        ? 'border border-[var(--b-line)] bg-[color-mix(in_srgb,var(--b-ivory)_86%,transparent)] shadow-[0_18px_44px_-32px_rgba(23,23,21,.55)] backdrop-blur-xl'
        : 'border border-transparent bg-transparent'"
    >
      <NuxtLink to="/" class="b-focus -m-2 shrink-0 p-2" :aria-label="$t('landing.nav.home')">
        <PersonalLogo :size="25" />
      </NuxtLink>

      <nav class="absolute left-1/2 hidden -translate-x-1/2 items-center gap-8 md:flex" :aria-label="$t('landing.nav.label')">
        <a
          v-for="link in links"
          :key="link.hash"
          :href="link.hash"
          class="b-focus relative text-[13.5px] text-[var(--b-stone)] transition-colors hover:text-[var(--b-black)]"
        >{{ $t(link.key) }}</a>
      </nav>

      <div class="flex shrink-0 items-center gap-1.5">
        <LanguageSwitcher class="mr-1 hidden sm:inline-flex" />
        <NuxtLink to="/login" class="b-focus hidden px-3 py-2 text-[13.5px] text-[var(--b-stone)] transition-colors hover:text-[var(--b-black)] sm:inline-flex">
          {{ $t('landing.nav.signIn') }}
        </NuxtLink>
        <LandingButtonLink to="/login">
          {{ $t('landing.nav.getAccess') }}
        </LandingButtonLink>
      </div>
    </div>
  </header>
</template>
