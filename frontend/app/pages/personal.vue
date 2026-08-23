<script setup lang="ts">
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
const instagram = ref<{
  username: string
  profile_picture_url: string | null
  media_count: number | null
} | null>(null)
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
const analysisStatus = computed(() => profile.value?.creator_dna?.analysis_status)
const analysisMessage = computed(() => {
  if (analysisStatus.value === 'insufficient_evidence') return t('personal.analysis.insufficient')
  if (analysisStatus.value === 'partial') return t('personal.analysis.partial')
  return null
})

function beginEdit() {
  if (!profile.value) return
  Object.assign(draft, {
    niche: profile.value.niche,
    audience_description: profile.value.audience_description,
    positioning: profile.value.positioning,
    topics: [...(profile.value.topics ?? [])],
    tone: [...(profile.value.tone ?? [])],
    current_projects: [...(profile.value.current_projects ?? [])],
    goals: [...(profile.value.goals ?? [])],
    content_strengths: [...(profile.value.content_strengths ?? [])]
  })
  editing.value = true
}

async function saveProfile() {
  saving.value = true
  try {
    const response = await apiFetch<{ profile: PersonalProfile }>('/api/me/profile', { method: 'PATCH', body: draft })
    profile.value = response.profile
    editing.value = false
    toast.success(t('personal.saved'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('personal.saveError')))
  } finally { saving.value = false }
}

onMounted(async () => {
  try {
    const response = await apiFetch<{ profile: PersonalProfile, instagram: typeof instagram.value }>('/api/me/profile')
    profile.value = response.profile
    instagram.value = response.instagram
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('personal.loadError')))
  }
})
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <header class="flex flex-col gap-4 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:flex-row md:items-center md:justify-between">
      <div class="flex items-start gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[12px] bg-[var(--accent-soft)] text-[var(--accent-ink)]"><AppIcon name="user" :size="19" /></span>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]">{{ $t('personal.eyebrow') }}</p>
          <p class="mt-2 max-w-xl text-[15px] leading-6 text-[var(--muted)]">{{ $t('personal.subtitle') }}</p>
        </div>
      </div>
      <button v-if="profile && !editing" type="button" class="inline-flex h-11 w-fit shrink-0 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition" @click="beginEdit">{{ $t('personal.editMemory') }}</button>
    </header>

    <div v-if="profile" class="mt-5 overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]">
      <div v-if="instagram" class="flex items-center gap-3 border-b border-[var(--line)] px-6 py-5">
        <img v-if="instagram.profile_picture_url" :src="instagram.profile_picture_url" alt="" class="h-10 w-10 rounded-full object-cover">
        <div v-else class="grid h-10 w-10 place-items-center rounded-full bg-[var(--paper)] text-xs">IG</div>
        <div>
          <p class="text-sm font-medium">@{{ instagram.username }}</p>
          <p class="text-xs text-[var(--faint)]">{{ $t('personal.liveContext', { count: instagram.media_count || 0 }) }}</p>
          <p v-if="analysisMessage" class="mt-1 text-xs text-[var(--accent-ink)]">{{ analysisMessage }}</p>
        </div>
        <span class="ml-auto text-xs text-[var(--positive)]">{{ $t('personal.connected') }}</span>
      </div>

      <div class="grid md:grid-cols-2">
        <section class="border-b border-[var(--line-soft)] p-6 md:border-r"><p class="memory-label">{{ $t('personal.positioning') }}</p><textarea v-if="editing" v-model="draft.positioning" class="memory-input min-h-24" /><p v-else class="memory-copy" :class="{ 'text-[var(--faint)]': !profile.positioning }">{{ profile.positioning || $t('personal.notProvided') }}</p></section>
        <section class="border-b border-[var(--line-soft)] p-6"><p class="memory-label">{{ $t('personal.audience') }}</p><textarea v-if="editing" v-model="draft.audience_description" class="memory-input min-h-24" /><p v-else class="memory-copy" :class="{ 'text-[var(--faint)]': !profile.audience_description }">{{ profile.audience_description || $t('personal.notProvided') }}</p></section>
        <section class="border-b border-[var(--line-soft)] p-6 md:col-span-2"><p class="memory-label">{{ $t('personal.yourNiche') }}</p><input v-if="editing" v-model="draft.niche" class="memory-input"><p v-else class="memory-copy" :class="{ 'text-[var(--faint)]': !profile.niche }">{{ profile.niche || $t(analysisStatus === 'insufficient_evidence' ? 'personal.nicheInsufficient' : 'personal.notProvided') }}</p></section>
        <section v-for="(key, index) in sections" :key="key" class="border-b border-[var(--line-soft)] p-6" :class="index % 2 === 0 ? 'md:border-r' : ''">
          <p class="memory-label">{{ $t('personal.sections.' + key) }}</p>
          <input v-if="editing" :value="(draft[key] || []).join(', ')" class="memory-input" @input="draft[key] = ($event.target as HTMLInputElement).value.split(',').map((v: string) => v.trim()).filter(Boolean)">
          <div v-else-if="profile[key]?.length" class="mt-4 flex flex-wrap gap-2"><span v-for="item in profile[key]" :key="item" class="rounded-full border border-[var(--line)] bg-[var(--paper)] px-3 py-1.5 text-sm">{{ item }}</span></div>
          <p v-else class="mt-3 text-sm text-[var(--faint)]">{{ $t('personal.notProvided') }}</p>
        </section>
      </div>
      <div v-if="editing" class="flex justify-end gap-3 p-5">
        <button class="rounded-full px-5 py-2.5 text-sm text-[var(--muted)] transition hover:text-[var(--ink)]" @click="editing = false">{{ $t('personal.cancel') }}</button>
        <button class="inline-flex h-11 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:opacity-60" :disabled="saving" @click="saveProfile">{{ saving ? $t('personal.saving') : $t('personal.saveMemory') }}</button>
      </div>
    </div>
  </main>
</template>

<style scoped>
.memory-label { @apply text-[10px] font-semibold uppercase tracking-[.16em] text-[var(--faint)]; }
.memory-copy { @apply mt-3 text-[17px] leading-7 text-[var(--copy)]; }
.memory-input { @apply mt-3 w-full rounded-[12px] border border-[var(--line)] bg-[var(--paper)] px-3 py-2.5 text-[15px] outline-none transition focus:border-[var(--muted)]; }
</style>
