<script setup lang="ts">
const route = useRoute()
const nav = [
  { label: 'For You', to: '/', icon: 'sparkles' },
  { label: 'Create', to: '/create', icon: 'plus' },
  { label: 'Moments', to: '/moments', icon: 'moments' },
  { label: 'Personal', to: '/personal', icon: 'user' },
  { label: 'Saved', to: '/saved', icon: 'bookmark' }
]
</script>

<template>
  <div class="min-h-screen bg-[var(--paper)] text-[var(--ink)]">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-[236px] flex-col border-r border-[var(--line)] bg-[#f2f0ea] px-4 py-6 md:flex">
      <NuxtLink to="/" class="px-3 text-[18px] font-semibold tracking-[-0.045em]">Personal</NuxtLink>
      <nav class="mt-12 space-y-1">
        <NuxtLink v-for="item in nav" :key="item.to" :to="item.to" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] transition" :class="route.path === item.to ? 'bg-white text-[#1c1c1a] shadow-[0_1px_4px_rgba(45,40,32,.06)]' : 'text-[#77736c] hover:bg-white/60 hover:text-[#1c1c1a]'">
          <AppIcon :name="item.icon" :size="18" />
          {{ item.label }}
        </NuxtLink>
      </nav>
      <div class="mt-auto space-y-2">
        <NuxtLink to="/settings" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-[#77736c] hover:bg-white/60"><AppIcon name="settings" :size="18" />Settings</NuxtLink>
        <div class="flex items-center gap-3 border-t border-[var(--line)] px-3 pt-5">
          <div class="grid h-9 w-9 place-items-center rounded-full bg-[#20201e] text-xs font-medium text-white">MC</div>
          <div><p class="text-sm font-medium">Mohamed</p><p class="text-[11px] text-[#97938a]">Personal workspace</p></div>
        </div>
      </div>
    </aside>

    <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-[var(--line)] bg-[var(--paper)]/95 px-5 backdrop-blur md:hidden">
      <NuxtLink to="/" class="font-semibold tracking-[-0.04em]">Personal</NuxtLink>
      <NuxtLink to="/create" class="grid h-9 w-9 place-items-center rounded-full bg-[#1d1d1b] text-white"><AppIcon name="plus" /></NuxtLink>
    </header>

    <div class="pb-20 md:ml-[236px] md:pb-0"><slot /></div>

    <nav class="fixed inset-x-0 bottom-0 z-30 flex h-16 items-center justify-around border-t border-[var(--line)] bg-[#f7f5f0]/95 px-2 backdrop-blur md:hidden">
      <NuxtLink v-for="item in nav" :key="item.to" :to="item.to" class="flex min-w-12 flex-col items-center gap-1 text-[10px]" :class="route.path === item.to ? 'text-[#1d1d1b]' : 'text-[#918d85]'">
        <AppIcon :name="item.icon" :size="18" />{{ item.label }}
      </NuxtLink>
    </nav>
  </div>
</template>
