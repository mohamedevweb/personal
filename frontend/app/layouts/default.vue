<script setup lang="ts">
const route = useRoute()
const { user, loadUser, logout } = useAuth()
const { launch } = useRemixLaunch()
const { t } = useI18n()
const avatarFailed = ref(false)

onMounted(() => { loadUser().catch(() => {}) })

watch(() => user.value?.avatar_url, () => { avatarFailed.value = false })

const accountLabel = computed(() => {
  const instagramUsername = user.value?.instagram_username?.replace(/^@/, '')

  if (instagramUsername) return `@${instagramUsername}`

  return user.value?.name || t('common.yourWorkspace')
})

const groups = [
  {
    label: 'nav.groups.discover',
    items: [
      { label: 'nav.forYou', to: '/feed', icon: 'sparkles' },
      { label: 'nav.saved', to: '/saved', icon: 'bookmark' }
    ]
  },
  {
    label: 'nav.groups.studio',
    items: [
      { label: 'nav.create', to: '/create', icon: 'plus' },
      { label: 'nav.drafts', to: '/drafts', icon: 'draft' },
      { label: 'nav.moments', to: '/moments', icon: 'moments' },
      { label: 'nav.personal', to: '/personal', icon: 'user' }
    ]
  }
]

// The mobile bar keeps a single flat row; the grouped rail is a desktop shape.
const mobileNav = groups.flatMap(group => group.items)

const titles: Record<string, string> = {
  '/feed': 'nav.forYou',
  '/create': 'nav.create',
  '/drafts': 'nav.drafts',
  '/moments': 'nav.moments',
  '/personal': 'nav.personal',
  '/saved': 'nav.saved',
  '/settings': 'nav.settings'
}

const pageTitle = computed(() => {
  if (route.path.startsWith('/content/')) return 'nav.pattern'
  if (route.path.startsWith('/remix/')) return 'nav.remix'
  return titles[route.path] || 'brand.name'
})
</script>

<template>
  <div class="min-h-screen bg-[var(--paper)] text-[var(--ink)]">
    <RemixGenerationStage
      v-if="launch && !route.path.startsWith('/remix/')"
      :format="launch.format"
      :source-hook="launch.sourceHook"
      :moment="launch.moment"
      :started-at="launch.startedAt"
      overlay
    />

    <aside class="fixed inset-y-0 left-0 z-30 hidden w-[264px] flex-col border-r border-[var(--line)] bg-[var(--rail)] px-3 py-5 md:flex">
      <NuxtLink to="/feed" class="b-focus block w-fit px-2 py-1">
        <PersonalLogo :size="22" />
      </NuxtLink>

      <nav class="mt-9 space-y-7">
        <div v-for="group in groups" :key="group.label">
          <p class="px-3 text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t(group.label) }}</p>
          <div class="mt-2 space-y-0.5">
            <NuxtLink
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-3 rounded-[10px] px-3 py-2 text-[13.5px] transition"
              :class="route.path === item.to ? 'bg-[var(--line-soft)] font-medium text-[var(--ink)]' : 'text-[var(--muted)] hover:bg-[var(--sand-soft)] hover:text-[var(--ink)]'"
            >
              <AppIcon :name="item.icon" :size="17" :class="route.path === item.to ? 'text-[var(--accent)]' : ''" />
              {{ $t(item.label) }}
            </NuxtLink>
          </div>
        </div>
      </nav>

      <div class="mt-auto">
        <div class="flex items-center gap-3 rounded-[14px] px-2 py-2">
          <img v-if="user?.avatar_url && !avatarFailed" :src="user.avatar_url" :alt="accountLabel" class="h-9 w-9 rounded-full object-cover" @error="avatarFailed = true">
          <div v-else class="grid h-9 w-9 place-items-center rounded-full bg-[var(--ink)] text-[var(--paper)]"><AppIcon name="user" :size="16" /></div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium">{{ accountLabel }}</p>
            <button class="text-[11px] text-[var(--faint)] transition hover:text-[var(--ink)]" @click="logout">{{ $t('common.signOut') }}</button>
          </div>
        </div>
      </div>
    </aside>

    <div class="md:ml-[264px]">
      <header class="sticky top-0 z-20 hidden h-[74px] bg-[var(--paper)] md:block">
        <div class="page-shell flex h-full items-center justify-between">
          <h1 class="font-serif text-[30px] leading-none tracking-[-.02em]">{{ $t(pageTitle) }}</h1>
          <div class="flex items-center gap-2">
            <NuxtLink to="/settings" class="grid h-10 w-10 place-items-center rounded-full border border-[var(--line)] bg-[var(--surface)] text-[var(--muted)] transition hover:text-[var(--ink)]" :aria-label="$t('nav.settings')">
              <AppIcon name="settings" :size="17" />
            </NuxtLink>
            <NuxtLink to="/personal" class="grid h-10 w-10 place-items-center overflow-hidden rounded-full border border-[var(--line)] bg-[var(--surface)] text-[var(--muted)] transition hover:text-[var(--ink)]" :aria-label="$t('nav.personal')">
              <img v-if="user?.avatar_url && !avatarFailed" :src="user.avatar_url" :alt="accountLabel" class="h-full w-full object-cover" @error="avatarFailed = true">
              <AppIcon v-else name="user" :size="17" />
            </NuxtLink>
            <NuxtLink to="/create" class="grid h-10 w-10 place-items-center rounded-full b-btn-red transition" :aria-label="$t('nav.create')">
              <AppIcon name="plus" :size="18" />
            </NuxtLink>
          </div>
        </div>
      </header>

      <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-[var(--line)] bg-[var(--paper)]/95 px-5 backdrop-blur md:hidden">
        <NuxtLink to="/feed" class="b-focus flex items-center gap-2.5">
          <PersonalMark :size="19" tone="signature" />
          <span class="font-serif text-[19px] tracking-[-.02em]">{{ $t(pageTitle) }}</span>
        </NuxtLink>
        <NuxtLink to="/create" class="grid h-9 w-9 place-items-center rounded-full b-btn-red" :aria-label="$t('nav.create')"><AppIcon name="plus" /></NuxtLink>
      </header>

      <div class="pb-20 md:pb-0"><slot /></div>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-30 flex h-16 items-center justify-around border-t border-[var(--line)] bg-[var(--surface)]/95 px-2 backdrop-blur md:hidden">
      <NuxtLink v-for="item in mobileNav" :key="item.to" :to="item.to" class="flex min-w-12 flex-col items-center gap-1 text-[10px]" :class="route.path === item.to ? 'text-[var(--ink)]' : 'text-[var(--faint)]'">
        <AppIcon :name="item.icon" :size="18" :class="route.path === item.to ? 'text-[var(--accent)]' : ''" />{{ $t(item.label) }}
      </NuxtLink>
      <NuxtLink to="/settings" class="flex min-w-12 flex-col items-center gap-1 text-[10px]" :class="route.path === '/settings' ? 'text-[var(--ink)]' : 'text-[var(--faint)]'">
        <AppIcon name="settings" :size="18" :class="route.path === '/settings' ? 'text-[var(--accent)]' : ''" />{{ $t('nav.settings') }}
      </NuxtLink>
    </nav>

  </div>
</template>
