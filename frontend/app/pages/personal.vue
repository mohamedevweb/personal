<script setup lang="ts">
import type { InstagramAccount } from '~/types/instagram'
import type { PersonalProfile } from '~/types/product'

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
const { t } = useI18n()
const toast = useToast()
const profile = ref<PersonalProfile | null>(null)
const instagram = ref<Pick<InstagramAccount, 'username' | 'display_name' | 'profile_picture_url' | 'media_count'> | null>(null)
const instagramAvatarFailed = ref(false)
const editing = ref(false)
const saving = ref(false)
const voicePrompt = ref('')
const voicePromptLoading = ref(true)
const voicePromptError = ref(false)
const importingVoice = ref(false)
const showingVoicePrompt = ref(false)
const voiceFileInput = ref<HTMLInputElement | null>(null)
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
const voiceProviders = [
  { name: 'ChatGPT', url: 'https://chatgpt.com/', promptParameter: 'q', icon: 'chatgpt' },
  { name: 'Claude', url: 'https://claude.ai/new', promptParameter: 'q', icon: 'claude' },
  { name: 'Gemini', url: 'https://gemini.google.com/app', promptParameter: 'q', icon: 'gemini' },
  { name: 'Perplexity', url: 'https://www.perplexity.ai/search', promptParameter: 'q', icon: 'perplexity' },
  { name: 'Grok', url: 'https://grok.com/', promptParameter: 'q', icon: 'grok' }
] as const
const analysisStatus = computed(() => profile.value?.creator_dna?.analysis_status)
const hasVoiceProfile = computed(() => Boolean(profile.value?.voice_profile?.trim()))
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
    const response = await apiFetch<{ profile: PersonalProfile, instagram: typeof instagram.value }>('/api/me/profile')
    profile.value = response.profile
    instagram.value = response.instagram
    instagramAvatarFailed.value = false
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('personal.loadError')))
  }
}

async function loadVoicePrompt() {
  voicePromptLoading.value = true
  voicePromptError.value = false
  try {
    const response = await apiFetch<{ prompt: string, filename: string }>('/api/me/voice-prompt')
    voicePrompt.value = response.prompt
  } catch {
    voicePromptError.value = true
  } finally {
    voicePromptLoading.value = false
  }
}

async function writeVoicePrompt() {
  if (!import.meta.client || !voicePrompt.value) return false

  try {
    await navigator.clipboard.writeText(voicePrompt.value)
    return true
  } catch {
    showingVoicePrompt.value = true
    return false
  }
}

async function copyVoicePrompt() {
  if (await writeVoicePrompt()) toast.success(t('personal.voice.promptCopied'))
  else toast.error(t('personal.voice.copyError'))
}

function voiceProviderUrl(provider: typeof voiceProviders[number]) {
  if (!voicePrompt.value) return provider.url

  const url = new URL(provider.url)
  url.searchParams.set(provider.promptParameter, voicePrompt.value)
  return url.toString()
}

async function prepareVoiceProvider(event: MouseEvent, provider: typeof voiceProviders[number]) {
  if (!import.meta.client || !voicePrompt.value) {
    event.preventDefault()
    return
  }

  if (await writeVoicePrompt()) toast.success(t('personal.voice.providerOpened', { provider: provider.name }))
  else toast.error(t('personal.voice.copyError'))
}

function chooseVoiceFile() {
  voiceFileInput.value?.click()
}

async function importVoiceFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  if (file.name.toLowerCase() !== 'voice.md') {
    toast.error(t('personal.voice.invalidFile'))
    return
  }

  if (file.size > 50000) {
    toast.error(t('personal.voice.fileTooLarge'))
    return
  }

  importingVoice.value = true
  try {
    const contents = (await file.text()).trim()
    if (!contents) {
      toast.error(t('personal.voice.emptyFile'))
      return
    }
    if (contents.length > 12000) {
      toast.error(t('personal.voice.fileTooLong'))
      return
    }

    const response = await apiFetch<{ profile: PersonalProfile }>('/api/me/profile', {
      method: 'PATCH',
      body: { voice_profile: contents }
    })
    profile.value = response.profile
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('personal.voice.importError')))
  } finally {
    importingVoice.value = false
  }
}

function downloadVoiceProfile() {
  if (!import.meta.client || !profile.value?.voice_profile) return

  const url = URL.createObjectURL(new Blob([profile.value.voice_profile], { type: 'text/markdown;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'voice.md'
  link.click()
  URL.revokeObjectURL(url)
}

onMounted(async () => {
  await Promise.all([loadProfile(), loadVoicePrompt()])
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <section v-if="profile" class="overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]">
      <div class="p-6 md:p-8">
        <div class="flex flex-wrap items-center gap-3">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[12px] bg-[var(--paper)] text-[var(--ai)]"><AppIcon name="sparkles" :size="19" /></span>
          <div>
            <h2 class="font-serif text-[26px] tracking-[-.03em]">{{ $t('personal.voice.title') }}</h2>
            <p class="mt-1 text-sm text-[var(--muted)]">{{ $t('personal.voice.description') }}</p>
          </div>
          <span class="ml-auto inline-flex items-center gap-1.5 rounded-full border border-[var(--line)] bg-[var(--paper)] px-3 py-1.5 text-xs" :class="hasVoiceProfile ? 'text-[var(--positive)]' : 'text-[var(--muted)]'">
            <AppIcon :name="hasVoiceProfile ? 'check' : 'draft'" :size="13" />
            {{ $t(hasVoiceProfile ? 'personal.voice.ready' : 'personal.voice.notReady') }}
          </span>
        </div>

        <div class="mt-6 grid gap-6 border-t border-[var(--line-soft)] pt-6 md:grid-cols-[minmax(0,1fr)_minmax(260px,320px)] md:gap-8">
          <div>
            <p class="memory-label">{{ $t('personal.voice.chooseProvider') }}</p>
            <div v-if="voicePromptError" role="alert" class="mt-4 rounded-[12px] border border-[var(--line)] bg-[var(--surface)] p-4 text-sm text-[var(--muted)]">
              <p>{{ $t('personal.voice.promptError') }}</p>
              <button type="button" class="mt-3 font-medium text-[var(--ink)] underline underline-offset-4" @click="loadVoicePrompt">{{ $t('personal.voice.retry') }}</button>
            </div>
            <div v-else class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5">
              <a v-for="provider in voiceProviders" :key="provider.name" :href="voiceProviderUrl(provider)" target="_blank" rel="noopener noreferrer" class="group flex min-w-0 flex-col items-center gap-2 rounded-[13px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ai)]" :class="voicePromptLoading || !voicePrompt ? 'pointer-events-none opacity-50' : ''" :aria-disabled="voicePromptLoading || !voicePrompt" :tabindex="voicePromptLoading || !voicePrompt ? -1 : undefined" @click="prepareVoiceProvider($event, provider)">
                <span class="grid h-12 w-12 place-items-center rounded-[13px] border border-[var(--line)] bg-[var(--paper)] text-[var(--ink)] transition group-hover:border-[var(--muted)]">
                  <AppIcon :name="provider.icon" :size="23" :stroke-width="1.5" />
                </span>
                <span class="max-w-full truncate text-[11px] font-medium text-[var(--muted)] transition group-hover:text-[var(--ink)]">{{ provider.name }}</span>
              </a>
            </div>
          </div>

          <div class="md:border-l md:border-[var(--line-soft)] md:pl-8">
            <input ref="voiceFileInput" type="file" accept=".md,text/markdown,text/plain" class="sr-only" @change="importVoiceFile">
            <div class="flex flex-wrap gap-2">
              <button type="button" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:cursor-not-allowed disabled:opacity-60" :disabled="importingVoice" @click="chooseVoiceFile">
                <AppIcon name="draft" :size="16" />
                {{ importingVoice ? $t('personal.voice.importing') : $t(hasVoiceProfile ? 'personal.voice.replace' : 'personal.voice.import') }}
              </button>
              <button v-if="hasVoiceProfile" type="button" class="inline-flex h-11 items-center justify-center rounded-full border border-[var(--line)] bg-[var(--surface)] px-4 text-sm font-medium transition hover:border-[var(--muted)]" @click="downloadVoiceProfile">{{ $t('personal.voice.download') }}</button>
            </div>
            <p class="mt-3 text-xs leading-5 text-[var(--faint)]">{{ $t('personal.voice.privacy') }}</p>
            <button v-if="voicePrompt" type="button" class="mt-2 text-xs text-[var(--muted)] underline underline-offset-4 transition hover:text-[var(--ink)]" @click="showingVoicePrompt = !showingVoicePrompt">{{ $t(showingVoicePrompt ? 'personal.voice.hidePrompt' : 'personal.voice.showPrompt') }}</button>
          </div>
        </div>
      </div>

      <div v-if="showingVoicePrompt && voicePrompt" class="border-t border-[var(--line)] bg-[var(--paper)] p-6">
        <div class="flex items-center justify-between gap-4">
          <p class="memory-label">{{ $t('personal.voice.promptLabel') }}</p>
          <button type="button" class="inline-flex items-center gap-2 text-xs font-medium text-[var(--muted)] transition hover:text-[var(--ink)]" @click="copyVoicePrompt"><AppIcon name="copy" :size="14" />{{ $t('personal.voice.copyPrompt') }}</button>
        </div>
        <textarea :value="voicePrompt" readonly :aria-label="$t('personal.voice.promptLabel')" class="mt-4 min-h-64 w-full resize-y rounded-[12px] border border-[var(--line)] bg-[var(--surface)] p-4 font-mono text-xs leading-5 text-[var(--muted)] outline-none focus:border-[var(--muted)]" />
      </div>
    </section>

    <form v-if="profile" class="mt-5 overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]" @submit.prevent="saveProfile">
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
          <span class="hidden text-xs text-[var(--positive)] sm:inline">{{ $t('personal.connected') }}</span>
        </template>

        <template v-else>
          <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[var(--paper)] text-xs">IG</div>
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
  </main>
</template>

<style scoped>
.memory-label { @apply text-[10px] font-semibold uppercase tracking-[.16em] text-[var(--faint)]; }
.memory-copy { @apply mt-3 text-[17px] leading-7 text-[var(--copy)]; }
.memory-input { @apply mt-3 w-full rounded-[12px] border border-[var(--line)] bg-[var(--paper)] px-3 py-2.5 text-[15px] outline-none transition focus:border-[var(--muted)]; }
</style>
