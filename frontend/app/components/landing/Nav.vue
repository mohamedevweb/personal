<script setup lang="ts">
const links = [
  { hash: '#how', key: 'landing.nav.how' },
  { hash: '#moments', key: 'landing.nav.moments' },
  { hash: '#faq', key: 'landing.nav.faq' }
]

// The bar is invisible over the hero and only draws itself once the page has
// moved, so nothing competes with the headline on first paint. It goes fully
// opaque rather than translucent because it passes over two near-black
// sections, and a tinted bar would swallow its own links there.
const lifted = ref(false)
function onScroll() { lifted.value = window.scrollY > 12 }

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
  <header
    class="sticky top-0 z-50 border-b px-6 transition-colors duration-500 md:px-10"
    :class="lifted
      ? 'border-[var(--b-line)] bg-[var(--b-ivory)]'
      : 'border-transparent bg-transparent'"
  >
    <!-- Padding sits outside the max-width box, exactly like every section
         below, so the logo and the CTA land on the same two rails as the
         content instead of 40px inside them. -->
    <div class="mx-auto flex h-[76px] max-w-[1200px] items-center justify-between">
      <NuxtLink to="/" class="b-focus -m-2 p-2" :aria-label="$t('landing.nav.home')">
        <PersonalLogo :size="26" />
      </NuxtLink>

      <nav class="hidden items-center gap-9 md:flex" :aria-label="$t('landing.nav.label')">
        <a
          v-for="link in links"
          :key="link.hash"
          :href="link.hash"
          class="b-focus text-[14px] text-[var(--b-stone)] transition-colors hover:text-[var(--b-black)]"
        >{{ $t(link.key) }}</a>
      </nav>

      <div class="flex items-center gap-2">
        <LanguageSwitcher class="mr-1 hidden sm:inline-flex" />
        <NuxtLink to="/login" class="b-focus hidden px-3 py-2 text-[14px] text-[var(--b-stone)] transition-colors hover:text-[var(--b-black)] sm:inline-flex">
          {{ $t('landing.nav.signIn') }}
        </NuxtLink>
        <LandingButtonLink to="/login">{{ $t('landing.nav.getAccess') }}</LandingButtonLink>
      </div>
    </div>
  </header>
</template>
