<script setup lang="ts">
const route = useRoute()
const { user, loadUser, logout } = useAuth()
const { mailto: supportMailto } = useSupportEmail()
const { collapsed, toggle: toggleSidebar } = useSidebar()
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
      { label: 'nav.drafts', to: '/drafts', icon: 'draft' },
      { label: 'nav.personal', to: '/personal', icon: 'user' }
    ]
  }
]

// The mobile bar keeps a single flat row; the grouped rail is a desktop shape.
const mobileNav = [
  ...groups.flatMap(group => group.items),
  { label: 'nav.settings', to: '/settings', icon: 'settings' }
]

// Folded, a row is a centred icon with its label read out by the tooltip and
// the aria-label instead of standing next to it.
const rowClass = computed(() => collapsed.value
  ? 'flex h-10 w-10 items-center justify-center rounded-[10px] transition'
  : 'flex items-center gap-3 rounded-[10px] px-3 py-2 text-[13.5px] transition')

function rowTone(active: boolean) {
  return active
    ? 'bg-[var(--line-soft)] font-medium text-[var(--ink)]'
    : 'text-[var(--muted)] hover:bg-[var(--sand-soft)] hover:text-[var(--ink)]'
}

const titles: Record<string, string> = {
  '/feed': 'nav.forYou',
  '/drafts': 'nav.drafts',
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
    <aside
      class="fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-[var(--line)] bg-[var(--rail)] py-5 pl-[max(.75rem,env(safe-area-inset-left))] pr-3 transition-[width] duration-200 md:flex"
      :class="collapsed ? 'w-[76px]' : 'w-[264px]'"
    >
      <div class="flex items-center" :class="collapsed ? 'flex-col gap-2' : 'justify-between'">
        <NuxtLink to="/feed" class="b-focus block w-fit px-2 py-1">
          <PersonalMark v-if="collapsed" :size="22" tone="signature" />
          <PersonalLogo v-else :size="22" />
        </NuxtLink>

        <button
          type="button"
          class="b-focus grid h-9 w-9 place-items-center rounded-[10px] text-[var(--faint)] transition hover:bg-[var(--sand-soft)] hover:text-[var(--ink)]"
          :aria-label="$t(collapsed ? 'nav.expand' : 'nav.collapse')"
          :title="$t(collapsed ? 'nav.expand' : 'nav.collapse')"
          :aria-expanded="!collapsed"
          @click="toggleSidebar"
        >
          <AppIcon name="sidebar" :size="17" />
        </button>
      </div>

      <nav class="mt-9 space-y-7">
        <div v-for="(group, index) in groups" :key="group.label">
          <p v-if="!collapsed" class="px-3 text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t(group.label) }}</p>
          <!-- Folded, the group headings go: a short rule keeps the blocks apart. -->
          <div v-else-if="index > 0" class="mx-auto h-px w-5 bg-[var(--line)]" />
          <div class="mt-2 space-y-0.5" :class="collapsed ? 'flex flex-col items-center' : ''">
            <NuxtLink
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              :aria-label="collapsed ? $t(item.label) : undefined"
              :title="collapsed ? $t(item.label) : undefined"
              :class="[rowClass, rowTone(route.path === item.to)]"
            >
              <AppIcon :name="item.icon" :size="17" :class="route.path === item.to ? 'text-[var(--accent)]' : ''" />
              <span v-if="!collapsed">{{ $t(item.label) }}</span>
            </NuxtLink>
          </div>
        </div>
      </nav>

      <div class="mt-auto" :class="collapsed ? 'flex flex-col items-center gap-0.5' : ''">
        <a
          :href="supportMailto"
          :aria-label="collapsed ? $t('support.nav') : undefined"
          :title="collapsed ? $t('support.nav') : undefined"
          :class="[rowClass, 'text-[var(--muted)] hover:bg-[var(--sand-soft)] hover:text-[var(--ink)]']"
        >
          <AppIcon name="chat" :size="17" />
          <span v-if="!collapsed">{{ $t('support.nav') }}</span>
        </a>

        <NuxtLink
          to="/settings"
          :aria-label="collapsed ? $t('nav.settings') : undefined"
          :title="collapsed ? $t('nav.settings') : undefined"
          :class="[rowClass, rowTone(route.path === '/settings')]"
        >
          <AppIcon name="settings" :size="17" :class="route.path === '/settings' ? 'text-[var(--accent)]' : ''" />
          <span v-if="!collapsed">{{ $t('nav.settings') }}</span>
        </NuxtLink>

        <!-- Folded, the account block loses its name and the sign-out link
             becomes an icon of its own under the avatar. -->
        <div class="mt-1 flex items-center gap-3 rounded-[14px] px-2 py-2" :class="collapsed ? 'flex-col gap-1 px-0' : ''">
          <NuxtLink to="/personal" class="b-focus shrink-0 rounded-full" :aria-label="$t('nav.personal')" :title="collapsed ? accountLabel : undefined">
            <img v-if="user?.avatar_url && !avatarFailed" :src="user.avatar_url" :alt="accountLabel" class="h-9 w-9 rounded-full object-cover" @error="avatarFailed = true">
            <div v-else class="grid h-9 w-9 place-items-center rounded-full bg-[var(--ink)] text-[var(--paper)]"><AppIcon name="user" :size="16" /></div>
          </NuxtLink>
          <div v-if="!collapsed" class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium">{{ accountLabel }}</p>
            <button class="text-[11px] text-[var(--faint)] transition hover:text-[var(--ink)]" @click="logout">{{ $t('common.signOut') }}</button>
          </div>
          <button
            v-else
            class="b-focus grid h-9 w-9 place-items-center rounded-[10px] text-[var(--faint)] transition hover:bg-[var(--sand-soft)] hover:text-[var(--ink)]"
            :aria-label="$t('common.signOut')"
            :title="$t('common.signOut')"
            @click="logout"
          >
            <AppIcon name="logout" :size="17" />
          </button>
        </div>
      </div>
    </aside>

    <div class="transition-[margin] duration-200" :class="collapsed ? 'md:ml-[76px]' : 'md:ml-[264px]'">
      <!-- The bar sits under the status bar rather than beside it: its own
           background runs up into the inset, and the row keeps its height. -->
      <header class="sticky top-0 z-20 border-b border-[var(--line)] bg-[var(--paper)]/95 pt-[env(safe-area-inset-top)] backdrop-blur md:hidden">
        <div class="flex h-16 items-center px-5">
          <NuxtLink to="/feed" class="b-focus -mx-2 flex h-11 items-center gap-2.5 px-2">
            <PersonalMark :size="19" tone="signature" />
            <span class="font-serif text-[19px] tracking-[-.02em]">{{ $t(pageTitle) }}</span>
          </NuxtLink>
        </div>
      </header>

      <!-- The tab bar and the home indicator both stand in front of the page, so
           the scroll has to end above the two of them. -->
      <div class="pb-[calc(6rem+env(safe-area-inset-bottom))] md:pb-0 md:pt-8"><slot /></div>
    </div>

    <!-- The bar floats over the page as a pill rather than sitting on the edge:
         icons only, with a short rule under the active one doing the work the
         labels used to. Each tab is still a 44pt target. -->
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-30 flex justify-center px-4 pb-[calc(.75rem+env(safe-area-inset-bottom))] md:hidden">
      <nav class="pointer-events-auto flex items-center gap-0.5 rounded-full border border-[var(--line)] bg-[var(--surface)] px-2 py-1.5 shadow-[0_2px_6px_rgba(23,23,26,.06),0_14px_34px_rgba(23,23,26,.14)]">
        <NuxtLink
          v-for="item in mobileNav"
          :key="item.to"
          :to="item.to"
          :aria-label="$t(item.label)"
          :aria-current="route.path === item.to ? 'page' : undefined"
          class="b-focus relative grid h-11 w-[54px] place-items-center rounded-full transition"
          :class="route.path === item.to ? 'text-[var(--accent)]' : 'text-[var(--faint)]'"
        >
          <AppIcon :name="item.icon" :size="21" />
          <span
            class="absolute bottom-1 h-[2px] w-4 rounded-full bg-[var(--accent)] transition-opacity"
            :class="route.path === item.to ? 'opacity-100' : 'opacity-0'"
          />
        </NuxtLink>
      </nav>
    </div>

  </div>
</template>
