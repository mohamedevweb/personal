<script setup lang="ts">
import type { AdminCatalogCreator, AdminCatalogImport, AdminCatalogPost, CatalogVertical } from '~/types/product'
import { compactNumber } from '~/types/product'

const { apiFetch } = usePersonalApi()
const { locale, t } = useI18n()

const verticals: CatalogVertical[] = [
  'sport-fitness', 'food-cooking', 'personal-branding', 'tech-ai', 'wellness', 'events',
  'languages', 'lifestyle', 'local-culture', 'travel', 'startup', 'business'
]
const countries = ['FR', 'GB', 'US'] as const

type CatalogView = 'manage' | 'imports'
type CatalogEntity = 'creators' | 'posts'

const view = ref<CatalogView>('manage')
const entity = ref<CatalogEntity>('creators')
const type = ref<'creator' | 'post'>('creator')
const url = ref('')
const vertical = ref<CatalogVertical>('personal-branding')
const countryCode = ref<typeof countries[number]>('FR')
const creatorId = ref<number | null>(null)
const creatorSearch = ref('')
const creators = ref<AdminCatalogCreator[]>([])
const posts = ref<AdminCatalogPost[]>([])
const managementLoading = ref(true)
const managementError = ref('')
const managementNotice = ref('')
const managedCreatorSearch = ref('')
const managedPostSearch = ref('')
const creatorVerticalFilter = ref<CatalogVertical | '' | null>('')
const postVerticalFilter = ref<CatalogVertical | '' | null>('')
const savingCreatorId = ref<number | null>(null)
const savingPostId = ref<number | null>(null)
const removingPostId = ref<number | null>(null)
const imports = ref<AdminCatalogImport[]>([])
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const notice = ref('')
let pollTimer: number | undefined

const filteredCreators = computed(() => {
  const needle = creatorSearch.value.trim().toLowerCase()

  if (!needle) return creators.value

  return creators.value.filter(creator => `${creator.username} ${creator.display_name}`.toLowerCase().includes(needle))
})

const selectedCreator = computed(() => creators.value.find(creator => creator.id === creatorId.value) ?? null)

const filteredManagedCreators = computed(() => {
  const needle = managedCreatorSearch.value.trim().toLowerCase()

  return creators.value.filter((creator) => {
    const matchesSearch = !needle || `${creator.username} ${creator.display_name}`.toLowerCase().includes(needle)
    const matchesVertical = creatorVerticalFilter.value === '' || creator.vertical === creatorVerticalFilter.value

    return matchesSearch && matchesVertical
  })
})

const filteredManagedPosts = computed(() => {
  const needle = managedPostSearch.value.trim().toLowerCase()

  return posts.value.filter((post) => {
    const matchesSearch = !needle || `${post.hook} ${post.creator.username} ${post.creator.display_name}`.toLowerCase().includes(needle)
    const matchesVertical = postVerticalFilter.value === '' || post.vertical === postVerticalFilter.value

    return matchesSearch && matchesVertical
  })
})

const uncategorizedPosts = computed(() => posts.value.filter(post => !post.vertical).length)

function dateLabel(value: string | null): string {
  if (!value) return ''

  return new Date(value).toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

function numberLabel(value: number | null | undefined): string {
  return value === null || value === undefined ? '0' : compactNumber(value)
}

function statusLabel(status: AdminCatalogImport['status']): string {
  return t(`catalog.${status}`)
}

function statusClass(status: AdminCatalogImport['status']): string {
  return {
    queued: 'border-[var(--line)] bg-[var(--paper)] text-[var(--muted)]',
    running: 'border-[var(--accent)]/30 bg-[var(--accent-soft)] text-[#8a6413]',
    completed: 'border-[var(--positive)]/30 bg-[var(--positive-soft)] text-[var(--positive)]',
    failed: 'border-[var(--danger-line)] bg-[var(--danger-soft)] text-[var(--danger)]'
  }[status]
}

async function loadCreators() {
  const response = await apiFetch<{ items: AdminCatalogCreator[] }>('/api/admin/catalog/creators?limit=500')
  creators.value = response.items
}

async function loadPosts() {
  const response = await apiFetch<{ items: AdminCatalogPost[] }>('/api/admin/catalog/posts?limit=500')
  posts.value = response.items
}

async function loadManagement(showLoading = false) {
  if (showLoading) managementLoading.value = true

  try {
    await Promise.all([loadCreators(), loadPosts()])
    managementError.value = ''
  } catch (exception: unknown) {
    managementError.value = apiErrorMessage(exception, t('catalog.management.loadError'))
  } finally {
    managementLoading.value = false
  }
}

async function loadHistory(showLoading = false) {
  if (showLoading) loading.value = true

  try {
    const response = await apiFetch<{ items: AdminCatalogImport[] }>('/api/admin/catalog/imports?limit=50')
    imports.value = response.items
    error.value = ''
  } catch (exception: unknown) {
    error.value = apiErrorMessage(exception, t('catalog.loadError'))
  } finally {
    loading.value = false
  }
}

async function submit() {
  submitting.value = true
  error.value = ''
  notice.value = ''

  try {
    const body: {
      type: 'creator' | 'post'
      url: string
      vertical: CatalogVertical
      country_code: typeof countries[number]
      creator_id?: number
    } = {
      type: type.value,
      url: url.value,
      vertical: vertical.value,
      country_code: countryCode.value
    }

    if (type.value === 'post' && creatorId.value) body.creator_id = creatorId.value

    const response = await apiFetch<{ import: AdminCatalogImport }>('/api/admin/catalog/imports', {
      method: 'POST',
      body
    })
    imports.value = [response.import, ...imports.value]
    url.value = ''
    notice.value = t('catalog.submitted')
  } catch (exception: unknown) {
    error.value = apiErrorMessage(exception, t('catalog.submitError'))
  } finally {
    submitting.value = false
  }
}

async function saveCreatorVertical(creator: AdminCatalogCreator) {
  savingCreatorId.value = creator.id
  managementError.value = ''
  managementNotice.value = ''

  try {
    const response = await apiFetch<{ creator: AdminCatalogCreator }>(`/api/admin/catalog/creators/${creator.id}`, {
      method: 'PATCH',
      body: { vertical: creator.vertical ?? null }
    })
    const index = creators.value.findIndex(item => item.id === creator.id)
    if (index !== -1) creators.value[index] = response.creator
    managementNotice.value = t('catalog.management.creatorSaved')
  } catch (exception: unknown) {
    managementError.value = apiErrorMessage(exception, t('catalog.management.saveError'))
  } finally {
    savingCreatorId.value = null
  }
}

async function savePostVertical(post: AdminCatalogPost) {
  if (!post.vertical) return

  savingPostId.value = post.id
  managementError.value = ''
  managementNotice.value = ''

  try {
    const response = await apiFetch<{ post: AdminCatalogPost }>(`/api/admin/catalog/posts/${post.id}`, {
      method: 'PATCH',
      body: { vertical: post.vertical }
    })
    const index = posts.value.findIndex(item => item.id === post.id)
    if (index !== -1) posts.value[index] = response.post
    managementNotice.value = t('catalog.management.postSaved')
  } catch (exception: unknown) {
    managementError.value = apiErrorMessage(exception, t('catalog.management.saveError'))
  } finally {
    savingPostId.value = null
  }
}

async function removePost(post: AdminCatalogPost) {
  if (!import.meta.client || !window.confirm(t('catalog.management.deleteConfirm'))) return

  removingPostId.value = post.id
  managementError.value = ''
  managementNotice.value = ''

  try {
    await apiFetch(`/api/admin/catalog/posts/${post.id}`, { method: 'DELETE' })
    posts.value = posts.value.filter(item => item.id !== post.id)
    await loadHistory()
    managementNotice.value = t('catalog.management.postDeleted')
  } catch (exception: unknown) {
    managementError.value = apiErrorMessage(exception, t('catalog.management.deleteError'))
  } finally {
    removingPostId.value = null
  }
}

async function refreshCurrentView() {
  if (view.value === 'manage') {
    await loadManagement()
    return
  }

  await loadHistory()
}

watch(type, () => {
  creatorId.value = null
  creatorSearch.value = ''
})

onMounted(async () => {
  try {
    await Promise.all([loadManagement(true), loadHistory(true)])
  } catch (exception: unknown) {
    managementError.value = apiErrorMessage(exception, t('catalog.management.loadError'))
    loading.value = false
  }

  pollTimer = window.setInterval(() => {
    if (imports.value.some(item => item.status === 'queued' || item.status === 'running')) void loadHistory()
  }, 4000)
})

onBeforeUnmount(() => {
  if (pollTimer) window.clearInterval(pollTimer)
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        <p class="b-eyebrow">{{ $t('catalog.eyebrow') }}</p>
        <h1 class="mt-2 font-serif text-[34px] tracking-[-.03em]">{{ $t('catalog.title') }}</h1>
        <p class="mt-2 max-w-[620px] text-sm leading-6 text-[var(--muted)]">{{ $t('catalog.copy') }}</p>
      </div>
      <button type="button" class="b-focus inline-flex h-10 items-center justify-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-4 text-sm font-medium transition hover:bg-[var(--paper)]" @click="refreshCurrentView">
        <AppIcon name="refresh" :size="15" />{{ $t('queues.refresh') }}
      </button>
    </div>

    <div class="mt-8 inline-flex rounded-full border border-[var(--line)] bg-[var(--surface)] p-1">
      <button type="button" class="rounded-full px-4 py-2 text-sm font-medium transition" :class="view === 'manage' ? 'bg-[var(--ink)] text-white' : 'text-[var(--muted)] hover:text-[var(--ink)]'" @click="view = 'manage'">{{ $t('catalog.management.title') }}</button>
      <button type="button" class="rounded-full px-4 py-2 text-sm font-medium transition" :class="view === 'imports' ? 'bg-[var(--ink)] text-white' : 'text-[var(--muted)] hover:text-[var(--ink)]'" @click="view = 'imports'">{{ $t('catalog.importsView') }}</button>
    </div>

    <template v-if="view === 'manage'">
      <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <article class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)]">
          <p class="b-eyebrow">{{ $t('catalog.management.creators') }}</p>
          <p class="mt-3 font-serif text-[34px] tracking-[-.03em]">{{ creators.length }}</p>
        </article>
        <article class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)]">
          <p class="b-eyebrow">{{ $t('catalog.management.posts') }}</p>
          <p class="mt-3 font-serif text-[34px] tracking-[-.03em]">{{ posts.length }}</p>
        </article>
        <article class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)]">
          <p class="b-eyebrow">{{ $t('catalog.management.uncategorized') }}</p>
          <p class="mt-3 font-serif text-[34px] tracking-[-.03em]" :class="uncategorizedPosts ? 'text-[var(--accent)]' : ''">{{ uncategorizedPosts }}</p>
        </article>
      </div>

      <section class="mt-5 rounded-[20px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_1px_2px_rgba(23,23,26,.04)]">
        <div class="flex flex-col gap-4 border-b border-[var(--line)] px-5 py-4 md:flex-row md:items-center md:justify-between md:px-6">
          <div>
            <h2 class="font-serif text-[28px] tracking-[-.025em]">{{ entity === 'creators' ? $t('catalog.management.creators') : $t('catalog.management.posts') }}</h2>
            <p class="mt-1 text-xs text-[var(--muted)]">{{ $t('catalog.management.copy') }}</p>
          </div>
          <div class="inline-flex w-fit rounded-full border border-[var(--line)] bg-[var(--paper)] p-1">
            <button type="button" class="rounded-full px-3 py-1.5 text-xs font-medium transition" :class="entity === 'creators' ? 'bg-[var(--surface)] text-[var(--ink)] shadow-sm' : 'text-[var(--muted)]'" @click="entity = 'creators'">{{ $t('catalog.management.creators') }}</button>
            <button type="button" class="rounded-full px-3 py-1.5 text-xs font-medium transition" :class="entity === 'posts' ? 'bg-[var(--surface)] text-[var(--ink)] shadow-sm' : 'text-[var(--muted)]'" @click="entity = 'posts'">{{ $t('catalog.management.posts') }}</button>
          </div>
        </div>

        <div class="border-b border-[var(--line-soft)] px-5 py-4 md:px-6">
          <div v-if="entity === 'creators'" class="grid gap-3 md:grid-cols-[1fr_220px]">
            <input v-model="managedCreatorSearch" type="search" :placeholder="$t('catalog.management.searchCreators')" class="h-11 rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition placeholder:text-[var(--faint)] focus:border-[var(--accent)]">
            <select v-model="creatorVerticalFilter" class="h-11 rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition focus:border-[var(--accent)]">
              <option value="">{{ $t('catalog.management.allVerticals') }}</option>
              <option :value="null">{{ $t('catalog.management.uncategorized') }}</option>
              <option v-for="option in verticals" :key="option" :value="option">{{ $t('catalog.verticals.' + option) }}</option>
            </select>
          </div>
          <div v-else class="grid gap-3 md:grid-cols-[1fr_220px]">
            <input v-model="managedPostSearch" type="search" :placeholder="$t('catalog.management.searchPosts')" class="h-11 rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition placeholder:text-[var(--faint)] focus:border-[var(--accent)]">
            <select v-model="postVerticalFilter" class="h-11 rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition focus:border-[var(--accent)]">
              <option value="">{{ $t('catalog.management.allVerticals') }}</option>
              <option :value="null">{{ $t('catalog.management.uncategorized') }}</option>
              <option v-for="option in verticals" :key="option" :value="option">{{ $t('catalog.verticals.' + option) }}</option>
            </select>
          </div>
        </div>

        <div v-if="managementNotice" role="status" class="px-5 pt-4 text-sm text-[var(--positive)] md:px-6">{{ managementNotice }}</div>
        <div v-if="managementError" role="alert" class="mx-5 mt-4 rounded-[12px] border border-[var(--danger-line)] bg-[var(--danger-soft)] px-3 py-3 text-sm text-[var(--danger)] md:mx-6">{{ managementError }}</div>

        <div v-if="managementLoading" class="space-y-3 p-5 md:p-6">
          <div v-for="index in 5" :key="index" class="h-24 animate-pulse rounded-[16px] bg-[var(--sand-soft)]" />
        </div>
        <div v-else-if="entity === 'creators' && !filteredManagedCreators.length" class="px-6 py-14 text-center text-sm text-[var(--muted)]">{{ $t('catalog.management.emptyCreators') }}</div>
        <div v-else-if="entity === 'posts' && !filteredManagedPosts.length" class="px-6 py-14 text-center text-sm text-[var(--muted)]">{{ $t('catalog.management.emptyPosts') }}</div>

        <div v-else-if="entity === 'creators'" class="divide-y divide-[var(--line-soft)]">
          <article v-for="creator in filteredManagedCreators" :key="creator.id" class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-center md:justify-between md:px-6">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium">@{{ creator.username }}</p>
              <p class="mt-1 truncate text-xs text-[var(--muted)]">{{ creator.display_name }}</p>
              <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[var(--faint)]">
                <span>{{ numberLabel(creator.followers) }} {{ $t('catalog.metrics.followers').toLowerCase() }}</span>
                <span>{{ creator.posts_count || 0 }} {{ $t('catalog.management.posts').toLowerCase() }}</span>
                <span v-if="creator.country_code">{{ $t('catalog.countries.' + creator.country_code) }}</span>
              </div>
            </div>
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center md:w-auto">
              <select v-model="creator.vertical" :aria-label="$t('catalog.management.creatorVerticalAria', { username: creator.username })" class="h-10 min-w-0 rounded-[10px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none focus:border-[var(--accent)] sm:w-52">
                <option :value="null">{{ $t('catalog.management.uncategorized') }}</option>
                <option v-for="option in verticals" :key="option" :value="option">{{ $t('catalog.verticals.' + option) }}</option>
              </select>
              <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-4 text-xs font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60" :disabled="savingCreatorId === creator.id" @click="saveCreatorVertical(creator)">
                <AppIcon name="check" :size="14" />{{ savingCreatorId === creator.id ? $t('catalog.management.saving') : $t('catalog.management.save') }}
              </button>
            </div>
          </article>
        </div>

        <div v-else class="divide-y divide-[var(--line-soft)]">
          <article v-for="post in filteredManagedPosts" :key="post.id" class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-center md:px-6">
            <div class="flex min-w-0 flex-1 gap-3">
              <div class="h-16 w-16 shrink-0 overflow-hidden rounded-[12px] bg-[var(--paper)]">
                <img v-if="post.thumbnail_url" :src="post.thumbnail_url" :alt="$t('catalog.management.postThumbnailAlt', { creator: post.creator.username })" class="h-full w-full object-cover">
                <div v-else class="grid h-full w-full place-items-center text-[var(--faint)]"><AppIcon name="image" :size="20" /></div>
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ post.hook }}</p>
                <p class="mt-1 truncate text-xs text-[var(--muted)]">@{{ post.creator.username }} · {{ post.format }} · {{ dateLabel(post.published_at) }}</p>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[var(--faint)]">
                  <span>{{ $t('catalog.metrics.views') }} {{ numberLabel(post.views) }}</span>
                  <span>{{ $t('catalog.metrics.likes') }} {{ numberLabel(post.likes) }}</span>
                  <span>{{ post.engagement_rate.toFixed(2) }}%</span>
                  <a v-if="post.source_url" :href="post.source_url" target="_blank" rel="noreferrer" class="font-medium text-[var(--ink)] hover:underline">{{ $t('catalog.openInstagram') }}</a>
                </div>
              </div>
            </div>
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center md:w-auto">
              <select v-model="post.vertical" :aria-label="$t('catalog.management.postVerticalAria', { id: post.id })" class="h-10 min-w-0 rounded-[10px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none focus:border-[var(--accent)] sm:w-52">
                <option v-for="option in verticals" :key="option" :value="option">{{ $t('catalog.verticals.' + option) }}</option>
              </select>
              <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-4 text-xs font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60" :disabled="savingPostId === post.id || !post.vertical" @click="savePostVertical(post)">
                <AppIcon name="check" :size="14" />{{ savingPostId === post.id ? $t('catalog.management.saving') : $t('catalog.management.save') }}
              </button>
              <button type="button" class="b-focus inline-flex h-10 items-center justify-center gap-2 rounded-full border border-[var(--danger-line)] px-4 text-xs font-medium text-[var(--danger)] transition hover:bg-[var(--danger-soft)] disabled:cursor-not-allowed disabled:opacity-60" :disabled="removingPostId === post.id" @click="removePost(post)">
                <AppIcon name="trash" :size="14" />{{ removingPostId === post.id ? $t('catalog.management.deleting') : $t('catalog.management.delete') }}
              </button>
            </div>
          </article>
        </div>
      </section>
    </template>

    <template v-else>
      <section class="mt-6 rounded-[20px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_1px_2px_rgba(23,23,26,.04)] md:p-6">
        <div class="grid gap-5 md:grid-cols-2">
          <div>
            <label class="b-eyebrow" for="catalog-type">{{ $t('catalog.kind') }}</label>
            <select id="catalog-type" v-model="type" class="mt-2 h-12 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition focus:border-[var(--accent)]">
              <option value="creator">{{ $t('catalog.creator') }}</option>
              <option value="post">{{ $t('catalog.post') }}</option>
            </select>
          </div>
          <div>
            <label class="b-eyebrow" for="catalog-url">{{ $t('catalog.url') }}</label>
            <input id="catalog-url" v-model="url" type="url" required :placeholder="$t('catalog.urlPlaceholder')" class="mt-2 h-12 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition placeholder:text-[var(--faint)] focus:border-[var(--accent)]">
          </div>
          <div v-if="type === 'post'" class="md:col-span-2">
            <label class="b-eyebrow" for="catalog-creator">{{ $t('catalog.creatorSelect') }}</label>
            <input id="catalog-creator" v-model="creatorSearch" type="search" :placeholder="$t('catalog.creatorPlaceholder')" class="mt-2 h-12 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition placeholder:text-[var(--faint)] focus:border-[var(--accent)]">
            <div class="mt-2 max-h-44 overflow-y-auto rounded-[12px] border border-[var(--line)] bg-[var(--paper)] p-1">
              <button v-for="creator in filteredCreators" :key="creator.id" type="button" class="flex w-full items-center justify-between rounded-[9px] px-3 py-2 text-left text-sm transition hover:bg-[var(--surface)]" :class="creatorId === creator.id ? 'bg-[var(--surface)] font-medium' : ''" @click="creatorId = creator.id">
                <span><span class="font-medium">@{{ creator.username }}</span><span class="ml-2 text-[var(--muted)]">{{ creator.display_name }}</span></span>
                <span class="text-xs text-[var(--faint)]">{{ numberLabel(creator.followers) }}</span>
              </button>
              <p v-if="!filteredCreators.length" class="px-3 py-3 text-sm text-[var(--muted)]">{{ $t('catalog.noCreator') }}</p>
            </div>
            <p v-if="selectedCreator" class="mt-2 text-xs text-[var(--muted)]">@{{ selectedCreator.username }}</p>
          </div>
          <div>
            <label class="b-eyebrow" for="catalog-vertical">{{ $t('catalog.vertical') }}</label>
            <select id="catalog-vertical" v-model="vertical" class="mt-2 h-12 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition focus:border-[var(--accent)]">
              <option v-for="option in verticals" :key="option" :value="option">{{ $t('catalog.verticals.' + option) }}</option>
            </select>
          </div>
          <div>
            <label class="b-eyebrow" for="catalog-country">{{ $t('catalog.country') }}</label>
            <select id="catalog-country" v-model="countryCode" class="mt-2 h-12 w-full rounded-[12px] border border-[var(--line)] bg-[var(--surface)] px-3 text-sm outline-none transition focus:border-[var(--accent)]">
              <option v-for="option in countries" :key="option" :value="option">{{ $t('catalog.countries.' + option) }}</option>
            </select>
          </div>
        </div>
        <p v-if="notice" role="status" class="mt-4 text-sm text-[var(--positive)]">{{ notice }}</p>
        <p v-if="error" role="alert" class="mt-4 rounded-[12px] border border-[var(--danger-line)] bg-[var(--danger-soft)] px-3 py-3 text-sm text-[var(--danger)]">{{ error }}</p>
        <button type="button" class="mt-5 inline-flex h-11 items-center justify-center gap-2 rounded-full bg-[var(--ink)] px-5 text-sm font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting || !url || (type === 'post' && !creatorId)" @click="submit">
          <AppIcon name="plus" :size="15" />{{ submitting ? $t('catalog.submitting') : $t('catalog.submit') }}
        </button>
      </section>

      <section class="mt-8">
        <div class="flex items-end justify-between gap-4 border-b border-[var(--line)] pb-4">
          <h2 class="font-serif text-[28px] tracking-[-.025em]">{{ $t('catalog.history') }}</h2>
          <span class="text-xs text-[var(--faint)]">{{ imports.length }}</span>
        </div>
        <div v-if="loading" class="mt-5 space-y-3">
          <div v-for="index in 4" :key="index" class="h-28 animate-pulse rounded-[18px] bg-[var(--sand-soft)]" />
        </div>
        <div v-else-if="!imports.length" class="mt-5 rounded-[18px] border border-dashed border-[var(--line)] bg-[var(--surface)] px-6 py-14 text-center text-sm text-[var(--muted)]">{{ $t('catalog.empty') }}</div>
        <div v-else class="mt-5 space-y-3">
          <article v-for="item in imports" :key="item.id" class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-4 shadow-[0_1px_2px_rgba(23,23,26,.04)] md:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="rounded-full border px-2.5 py-1 text-[11px] font-medium" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
                  <span class="text-[10px] font-semibold uppercase tracking-[.14em] text-[var(--faint)]">{{ item.type === 'creator' ? $t('catalog.creator') : $t('catalog.post') }}</span>
                  <span class="text-xs text-[var(--faint)]">{{ dateLabel(item.created_at) }}</span>
                </div>
                <a :href="item.url" target="_blank" rel="noreferrer" class="mt-2 block truncate text-sm font-medium text-[var(--ink)] hover:underline">{{ item.creator_username ? '@' + item.creator_username : item.url }}</a>
                <p v-if="item.status === 'queued' || item.status === 'running'" class="mt-1 text-xs text-[var(--muted)]">{{ $t('catalog.statusPending') }}</p>
                <p v-if="item.error" role="alert" class="mt-1 text-xs text-[var(--danger)]">{{ item.error }}</p>
              </div>
              <div class="flex shrink-0 gap-2 text-xs text-[var(--muted)]">
                <span>{{ $t('catalog.verticals.' + item.vertical) }}</span><span>·</span><span>{{ $t('catalog.countries.' + item.country_code) }}</span>
              </div>
            </div>
            <div v-if="item.creator" class="mt-4 grid grid-cols-2 gap-3 border-t border-[var(--line-soft)] pt-4 sm:grid-cols-4">
              <div><p class="text-[10px] uppercase tracking-[.12em] text-[var(--faint)]">{{ $t('catalog.metrics.followers') }}</p><p class="mt-1 text-sm font-medium">{{ numberLabel(item.creator.followers) }}</p></div>
              <div><p class="text-[10px] uppercase tracking-[.12em] text-[var(--faint)]">{{ $t('catalog.metrics.averageViews') }}</p><p class="mt-1 text-sm font-medium">{{ numberLabel(item.creator.average_views) }}</p></div>
              <div v-if="item.content_post"><p class="text-[10px] uppercase tracking-[.12em] text-[var(--faint)]">{{ $t('catalog.metrics.views') }}</p><p class="mt-1 text-sm font-medium">{{ numberLabel(item.content_post.views) }}</p></div>
              <div v-if="item.content_post"><p class="text-[10px] uppercase tracking-[.12em] text-[var(--faint)]">{{ $t('catalog.metrics.outlier') }}</p><p class="mt-1 text-sm font-medium text-[var(--accent)]">{{ item.content_post.outlier_score.toFixed(1) }}×</p></div>
            </div>
            <div v-if="item.content_post" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--muted)]">
              <span>{{ $t('catalog.metrics.likes') }} {{ numberLabel(item.content_post.likes) }}</span><span>{{ $t('catalog.metrics.comments') }} {{ numberLabel(item.content_post.comments) }}</span><span>{{ $t('catalog.metrics.engagement') }} {{ item.content_post.engagement_rate.toFixed(2) }}%</span>
              <a v-if="item.content_post.source_url" :href="item.content_post.source_url" target="_blank" rel="noreferrer" class="font-medium text-[var(--ink)] hover:underline">{{ $t('catalog.openInstagram') }}</a>
            </div>
          </article>
        </div>
      </section>
    </template>
  </main>
</template>
