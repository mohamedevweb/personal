<script setup lang="ts">
const links = [
  { to: '/', key: 'landing.nav.homeLink' },
  { to: '/blog', key: 'landing.nav.blog' },
  { to: '/story', key: 'landing.nav.story' }
]
</script>

<template>
  <footer class="border-t border-[var(--b-line)] px-5 py-14 md:px-10">
    <div class="mx-auto flex max-w-[1200px] flex-col gap-10 md:flex-row md:items-end md:justify-between">
      <div>
        <PersonalLogo :size="24" />
        <p class="b-mono mt-5 text-[var(--b-stone)]">{{ $t('landing.footer.brandline') }}</p>
      </div>

      <div class="flex flex-col gap-6 md:items-end">
        <nav class="flex flex-wrap items-center gap-x-7 gap-y-3 text-[14px] text-[var(--b-stone)]">
          <NuxtLink v-for="link in links" :key="link.to" :to="link.to" class="b-focus transition-colors hover:text-[var(--b-black)]">{{ $t(link.key) }}</NuxtLink>
          <NuxtLink to="/login" class="b-focus transition-colors hover:text-[var(--b-black)]">{{ $t('landing.nav.signIn') }}</NuxtLink>
          <NuxtLink to="/terms" class="b-focus transition-colors hover:text-[var(--b-black)]">{{ $t('landing.nav.terms') }}</NuxtLink>
          <NuxtLink to="/privacy" class="b-focus transition-colors hover:text-[var(--b-black)]">{{ $t('landing.nav.privacy') }}</NuxtLink>
        </nav>
        <div class="flex items-center gap-5">
          <LanguageSwitcher />
          <p class="text-[13px] text-[var(--b-stone)]">{{ $t('landing.footer.copyright') }}</p>
        </div>
      </div>
    </div>
  </footer>
</template>
