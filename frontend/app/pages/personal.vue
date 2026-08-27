<script setup lang="ts">
import type { InstagramAccount } from '~/types/instagram'
import type { ContentPost, PersonalProfile } from '~/types/product'

type PersonalProfileDraft = Pick<PersonalProfile,
  | 'niche'
  | 'audience_description'
  | 'positioning'
  | 'topics'
  | 'tone'
  | 'current_projects'
  | 'goals'
  | 'content_strengths'
>

const { apiFetch } = usePersonalApi()
const {
  status: instagramStatus,
  analysisRunning,
  loadStatus: loadInstagramStatus,
  startPolling: startInstagramPolling
} = useInstagram()
const { t } = useI18n()
const toast = useToast()
const profile = ref<PersonalProfile | null>(null)
const instagram = ref<Pick<InstagramAccount, 'username' | 'display_name' | 'profile_picture_url' | 'media_count'> | null>(null)
const instagramAvatarFailed = ref(false)
const avatarFailed = ref(false)
/* The picture the creator recognises themselves by: the connected account's when
   there is one, otherwise the one read off the public profile behind their
   handle. Both arrive already proxied, since the Instagram CDN refuses to be
   embedded from another origin. */
const avatarUrl = computed(() => profile.value?.avatar_url || null)
const posts = ref<ContentPost[]>([])
const postsLoading = ref(true)
const editing = ref(false)
const saving = ref(false)
const draft = reactive<PersonalProfileDraft>({
  niche: null,
  audience_description: null,
  positioning: null,
  topics: [],
  tone: [],
  current_projects: [],
  goals: [],
  content_strengths: []
})

const sections = ['topics', 'tone', 'current_projects', 'goals', 'content_strengths'] as const
/* These fields hold lists but are typed as one line. The text is kept as text
   while it is being edited: parsing it back into an array on every keystroke
   would swallow the separator the moment you type it, so a second item could
   never be added. It becomes a list again on save. */
const sectionText = reactive<Record<(typeof sections)[number], string>>({
  topics: '',
  tone: '',
  current_projects: '',
  goals: '',
  content_strengths: ''
})

function toList(value: string): string[] {
  return value.split(',').map(item => item.trim()).filter(Boolean)
}

const analysisStatus = computed(() => profile.value?.creator_dna?.analysis_status)
const analysisMessage = computed(() => {
  if (analysisStatus.value === 'insufficient_evidence') return t('personal.analysis.insufficient')
  if (analysisStatus.value === 'partial') return t('personal.analysis.partial')
  return null
})

/* Without a connected account the handle is the only thing telling Personal
   who this creator is, so it has to be changeable from inside the app. Saving
   it starts a fresh read of the public profile on the server. */
const editingHandle = ref(false)
const handleDraft = ref('')
const handleSaving = ref(false)
const handle = computed(() => profile.value?.instagram_username || null)

function beginHandleEdit() {
  handleDraft.value = handle.value || ''
  editingHandle.value = true
}

async function saveHandle() {
  const username = handleDraft.value.trim().replace(/^@/, '')
  if (!username || handleSaving.value) return
  handleSaving.value = true
  try {
    const response = await apiFetch<{ instagram_username: string }>('/api/integrations/instagram/handle', {
      method: 'PUT',
      body: { username }
    })
    if (profile.value) profile.value.instagram_username = response.instagram_username
    editingHandle.value = false
    await loadProfile()
    toast.success(t('personal.handle.saved'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('personal.handle.error')))
  } finally {
    handleSaving.value = false
  }
}

function beginEdit() {
  if (!profile.value) return
  Object.assign(draft, {
    niche: profile.value.niche,
    audience_description: profile.value.audience_description,
    positioning: profile.value.positioning
  })
  for (const key of sections) sectionText[key] = (profile.value[key] ?? []).join(', ')
  editing.value = true
}

function applyPositioningTemplate() {
  draft.positioning ||= t('personal.template.positioningValue')
  draft.audience_description ||= t('personal.template.audienceValue')
  sectionText.topics ||= t('personal.template.topicsValue')
  sectionText.tone ||= t('personal.template.toneValue')
  sectionText.current_projects ||= t('personal.template.projectsValue')
  sectionText.goals ||= t('personal.template.goalsValue')
  sectionText.content_strengths ||= t('personal.template.strengthsValue')
}

async function saveProfile() {
  saving.value = true
  for (const key of sections) draft[key] = toList(sectionText[key])
  try {
    const response = await apiFetch<{ profile: PersonalProfile }>('/api/me/profile', { method: 'PATCH', body: draft })
    profile.value = response.profile
    editing.value = false
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('personal.saveError')))
  } finally { saving.value = false }
}

async function loadProfile() {
  try {
    const [response, postsResponse] = await Promise.all([
      apiFetch<{ profile: PersonalProfile, instagram: typeof instagram.value }>('/api/me/profile'),
      apiFetch<{ posts: ContentPost[] }>('/api/me/posts')
    ])
    profile.value = response.profile
    instagram.value = response.instagram
    posts.value = postsResponse.posts
    instagramAvatarFailed.value = false
    avatarFailed.value = false
    await loadInstagramStatus()
    if (analysisRunning.value) startInstagramPolling()
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('personal.loadError')))
  } finally {
    postsLoading.value = false
  }
}

async function toggleSaved(post: ContentPost) {
  try {
    const response = await apiFetch<{ saved: boolean }>(`/api/content/${post.id}/save`, { method: 'POST' })
    post.is_saved = response.saved
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('personal.posts.saveError')))
  }
}

watch(() => instagramStatus.value.analysis?.status, async (status, previousStatus) => {
  if (status !== previousStatus && (status === 'completed' || status === 'failed')) {
    await loadProfile()
  }
})

onMounted(loadProfile)
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <form v-if="profile" class="overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]" @submit.prevent="saveProfile">
      <div class="flex flex-wrap items-center gap-3 border-b border-[var(--line)] px-6 py-5">
        <template v-if="instagram">
          <img
            v-if="instagram.profile_picture_url && !instagramAvatarFailed"
            :src="instagram.profile_picture_url"
            :alt="instagram.display_name || `@${instagram.username}`"
            class="h-10 w-10 rounded-full object-cover"
            @error="instagramAvatarFailed = true"
          >
          <div v-else class="grid h-10 w-10 place-items-center rounded-full bg-[var(--paper)] text-xs">IG</div>
          <div>
            <p class="text-sm font-medium">{{ $t('personal.instagramAccount') }}</p>
            <p class="text-xs text-[var(--faint)]">{{ $t('personal.liveContext', { count: instagram.media_count || 0 }) }}</p>
            <p v-if="analysisMessage" class="mt-1 text-xs text-[var(--accent-ink)]">{{ analysisMessage }}</p>
          </div>
        </template>

        <template v-else>
          <img
            v-if="avatarUrl && !avatarFailed"
            :src="avatarUrl"
            :alt="handle ? `@${handle}` : ''"
            class="h-10 w-10 shrink-0 rounded-full object-cover"
            @error="avatarFailed = true"
          >
          <div v-else class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[var(--paper)] text-xs">IG</div>
          <div v-if="editingHandle" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <input
              v-model="handleDraft"
              class="memory-input mt-0 h-11 w-full max-w-56 flex-1"
              :placeholder="$t('personal.handle.placeholder')"
              :aria-label="$t('personal.handle.label')"
              @keydown.enter.prevent="saveHandle"
            >
            <button type="button" class="rounded-full px-3 py-2.5 text-sm text-[var(--muted)] transition hover:text-[var(--ink)]" @click="editingHandle = false">{{ $t('personal.cancel') }}</button>
            <button type="button" class="inline-flex h-11 items-center rounded-full border border-[var(--line)] bg-[var(--surface)] px-4 text-sm font-medium transition hover:border-[var(--muted)] disabled:opacity-60" :disabled="handleSaving" @click="saveHandle">
              {{ handleSaving ? $t('personal.saving') : $t('personal.handle.save') }}
            </button>
          </div>
          <div v-else class="min-w-0">
            <p class="truncate text-sm font-medium">{{ handle ? '@' + handle : $t('personal.handle.none') }}</p>
            <button type="button" class="mt-0.5 text-xs text-[var(--muted)] underline underline-offset-4 transition hover:text-[var(--ink)]" @click="beginHandleEdit">
              {{ $t(handle ? 'personal.handle.edit' : 'personal.handle.add') }}
            </button>
          </div>
        </template>

        <div class="ml-auto flex shrink-0 items-center justify-end gap-2">
          <button v-if="editing" type="button" class="rounded-full px-4 py-2.5 text-sm text-[var(--muted)] transition hover:text-[var(--ink)]" @click="editing = false">{{ $t('personal.cancel') }}</button>
          <button v-if="editing" type="submit" class="inline-flex h-11 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:opacity-60" :disabled="saving">{{ saving ? $t('personal.saving') : $t('personal.saveMemory') }}</button>
          <button v-else type="button" class="inline-flex h-11 w-fit items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition" @click="beginEdit">{{ $t('personal.editMemory') }}</button>
        </div>
      </div>

      <div v-if="editing" class="flex flex-col gap-3 border-b border-[var(--line)] bg-[var(--accent-soft)] px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm font-medium text-[var(--accent-ink)]">{{ $t('personal.template.title') }}</p>
          <p class="mt-1 text-xs leading-5 text-[var(--muted)]">{{ $t('personal.template.copy') }}</p>
        </div>
        <button type="button" class="shrink-0 rounded-full border border-[var(--accent-line)] bg-[var(--surface)] px-4 py-2.5 text-sm font-medium transition hover:border-[var(--accent)]" @click="applyPositioningTemplate">
          {{ $t('personal.template.action') }}
        </button>
      </div>

      <div class="grid md:grid-cols-2">
        <section class="border-b border-[var(--line-soft)] p-6 md:border-r"><p class="memory-label">{{ $t('personal.positioning') }}</p><textarea v-if="editing" v-model="draft.positioning" class="memory-input min-h-24" /><p v-else class="memory-copy" :class="{ 'text-[var(--faint)]': !profile.positioning }">{{ profile.positioning || $t('personal.notProvided') }}</p></section>
        <section class="border-b border-[var(--line-soft)] p-6"><p class="memory-label">{{ $t('personal.audience') }}</p><textarea v-if="editing" v-model="draft.audience_description" class="memory-input min-h-24" /><p v-else class="memory-copy" :class="{ 'text-[var(--faint)]': !profile.audience_description }">{{ profile.audience_description || $t('personal.notProvided') }}</p></section>
        <section class="border-b border-[var(--line-soft)] p-6 md:col-span-2"><p class="memory-label">{{ $t('personal.yourNiche') }}</p><input v-if="editing" v-model="draft.niche" class="memory-input"><p v-else class="memory-copy" :class="{ 'text-[var(--faint)]': !profile.niche }">{{ profile.niche || $t(analysisStatus === 'insufficient_evidence' ? 'personal.nicheInsufficient' : 'personal.notProvided') }}</p></section>
        <section v-for="(key, index) in sections" :key="key" class="border-b border-[var(--line-soft)] p-6" :class="index % 2 === 0 ? 'md:border-r' : ''">
          <p class="memory-label">{{ $t('personal.sections.' + key) }}</p>
          <input v-if="editing" v-model="sectionText[key]" class="memory-input" :placeholder="$t('personal.listHint')">
          <div v-else-if="profile[key]?.length" class="mt-4 flex flex-wrap gap-2"><span v-for="item in profile[key]" :key="item" class="rounded-full border border-[var(--line)] bg-[var(--paper)] px-3 py-1.5 text-sm">{{ item }}</span></div>
          <p v-else class="mt-3 text-sm text-[var(--faint)]">{{ $t('personal.notProvided') }}</p>
        </section>
      </div>
    </form>

    <section v-if="instagram" class="mt-8">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t('personal.posts.eyebrow') }}</p>
          <h2 class="mt-2 font-serif text-[30px] tracking-[-.03em]">{{ $t('personal.posts.title') }}</h2>
          <p class="mt-2 max-w-xl text-sm leading-6 text-[var(--muted)]">{{ $t('personal.posts.copy') }}</p>
        </div>
        <span v-if="posts.length" class="rounded-full border border-[var(--line)] bg-[var(--surface)] px-3 py-1.5 text-xs text-[var(--muted)]">{{ $t('personal.posts.count', { count: posts.length }) }}</span>
      </div>

      <div v-if="postsLoading" class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="index in 3" :key="index" class="h-[520px] animate-pulse rounded-[20px] bg-[var(--sand-soft)]" />
      </div>
      <div v-else-if="posts.length" class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <ContentCard v-for="post in posts" :key="post.id" :post="post" @save="toggleSaved" @remix="navigateTo(`/content/${post.id}`)" />
      </div>
      <div v-else class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 text-sm text-[var(--muted)]">
        {{ $t('personal.posts.empty') }}
      </div>
    </section>
  </main>
</template>

<style scoped>
.memory-label { @apply text-[10px] font-semibold uppercase tracking-[.16em] text-[var(--faint)]; }
.memory-copy { @apply mt-3 text-[17px] leading-7 text-[var(--copy)]; }
.memory-input { @apply mt-3 w-full rounded-[12px] border border-[var(--line)] bg-[var(--paper)] px-3 py-2.5 text-[15px] outline-none transition focus:border-[var(--muted)]; }
</style>
