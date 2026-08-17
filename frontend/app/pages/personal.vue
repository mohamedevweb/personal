<script setup lang="ts">
import type { PersonalProfile } from '~/types/product'

const { apiFetch } = usePersonalApi()
const profile = ref<PersonalProfile | null>(null)
const instagram = ref<any>(null)
const editing = ref(false)
const saving = ref(false)
const draft = reactive<any>({})

const sections = [
  ['topics', 'Topics'], ['tone', 'Tone'], ['current_projects', 'Current projects'],
  ['goals', 'Goals'], ['content_strengths', 'Content strengths']
] as const

function beginEdit() {
  if (!profile.value) return
  Object.assign(draft, JSON.parse(JSON.stringify(profile.value)))
  editing.value = true
}

async function saveProfile() {
  saving.value = true
  try {
    const response = await apiFetch<{ profile: PersonalProfile }>('/api/me/profile', { method: 'PATCH', body: draft })
    profile.value = response.profile
    editing.value = false
  } finally { saving.value = false }
}

onMounted(async () => {
  const response = await apiFetch<{ profile: PersonalProfile, instagram: any }>('/api/me/profile')
  profile.value = response.profile
  instagram.value = response.instagram
})
</script>

<template>
  <main class="mx-auto max-w-5xl px-5 py-10 md:px-10 md:py-14">
    <header class="flex items-end justify-between gap-5">
      <div><p class="text-[11px] font-semibold uppercase tracking-[.17em] text-[#918d85]">Personal memory</p><h1 class="mt-4 font-serif text-4xl tracking-[-.04em] md:text-[54px]">What Personal knows about you</h1><p class="mt-4 max-w-2xl text-[16px] leading-7 text-[#716e67]">This context shapes every recommendation and remix. Make it feel unmistakably like you.</p></div>
      <button v-if="profile && !editing" class="rounded-full border border-[#d3cfc6] bg-white/60 px-5 py-2.5 text-sm" @click="beginEdit">Edit memory</button>
    </header>

    <div v-if="profile" class="mt-10 overflow-hidden rounded-[26px] border border-[var(--line)] bg-[#fbfaf7]">
      <div v-if="instagram" class="flex items-center gap-3 border-b border-[var(--line)] px-6 py-5"><img v-if="instagram.profile_picture_url" :src="instagram.profile_picture_url" class="h-10 w-10 rounded-full object-cover"><div v-else class="grid h-10 w-10 place-items-center rounded-full bg-[#e5e1d8] text-xs">IG</div><div><p class="text-sm font-medium">@{{ instagram.username }}</p><p class="text-xs text-[#88847c]">Live Instagram context · {{ instagram.media_count || 0 }} posts</p></div><span class="ml-auto text-xs text-[#4e785e]">Connected ✓</span></div>

      <div class="grid md:grid-cols-2">
        <section class="border-b border-[var(--line)] p-6 md:border-r"><p class="memory-label">Positioning</p><textarea v-if="editing" v-model="draft.positioning" class="memory-input min-h-24"/><p v-else class="memory-copy">{{ profile.positioning }}</p></section>
        <section class="border-b border-[var(--line)] p-6"><p class="memory-label">Audience</p><textarea v-if="editing" v-model="draft.audience_description" class="memory-input min-h-24"/><p v-else class="memory-copy">{{ profile.audience_description }}</p></section>
        <section class="border-b border-[var(--line)] p-6 md:col-span-2"><p class="memory-label">Your niche</p><input v-if="editing" v-model="draft.niche" class="memory-input"><p v-else class="memory-copy">{{ profile.niche }}</p></section>
        <section v-for="([key, label], index) in sections" :key="key" class="border-b border-[var(--line)] p-6" :class="index % 2 === 0 ? 'md:border-r' : ''">
          <p class="memory-label">{{ label }}</p>
          <input v-if="editing" :value="(draft[key] || []).join(', ')" class="memory-input" @input="draft[key] = ($event.target as HTMLInputElement).value.split(',').map((v: string) => v.trim()).filter(Boolean)">
          <div v-else class="mt-4 flex flex-wrap gap-2"><span v-for="item in profile[key]" :key="item" class="rounded-full border border-[#ddd8cf] bg-white px-3 py-1.5 text-sm">{{ item }}</span></div>
        </section>
      </div>
      <div v-if="editing" class="flex justify-end gap-3 p-5"><button class="rounded-full px-5 py-2.5 text-sm text-[#77736c]" @click="editing = false">Cancel</button><button class="rounded-full bg-[#1d1d1b] px-5 py-2.5 text-sm text-white" :disabled="saving" @click="saveProfile">{{ saving ? 'Saving…' : 'Save memory' }}</button></div>
    </div>
  </main>
</template>

<style scoped>
.memory-label { @apply text-[10px] font-semibold uppercase tracking-[.16em] text-[#918d85]; }
.memory-copy { @apply mt-3 text-[18px] leading-7 text-[#36342f]; }
.memory-input { @apply mt-3 w-full rounded-xl border border-[#d8d4cb] bg-white px-3 py-2.5 text-[15px] outline-none focus:border-[#77736c]; }
</style>
