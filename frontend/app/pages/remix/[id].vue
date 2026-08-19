<script setup lang="ts">
import type { Remix } from '~/types/product'

const route = useRoute()
const { apiFetch } = usePersonalApi()
const { t } = useI18n()
const remix = ref<Remix | null>(null)
const saving = ref(false)
const saved = ref(false)

function moveSlide(index: number, direction: -1 | 1) {
  const slides = remix.value?.generated_content.slides
  if (!slides || !slides[index + direction]) return
  ;[slides[index], slides[index + direction]] = [slides[index + direction], slides[index]]
}
function deleteSlide(index: number) { remix.value?.generated_content.slides?.splice(index, 1) }
function regenerateSlide(index: number) {
  const slide = remix.value?.generated_content.slides?.[index]
  if (slide) slide.text = `${slide.text.replace(/[.!]$/, '')}${t('remix.regenerateAppend')}`
}
async function saveDraft() {
  if (!remix.value) return
  saving.value = true
  try { await apiFetch(`/api/remixes/${remix.value.id}`, { method: 'PATCH', body: { generated_content: remix.value.generated_content, status: 'draft' } }); saved.value = true; setTimeout(() => { saved.value = false }, 1800) } finally { saving.value = false }
}
async function switchFormat(format: 'reel' | 'carousel' | 'caption') {
  if (!remix.value || remix.value.format === format) return
  const sourceId = remix.value.source_content?.id
  const response = await apiFetch<{ remix: { id: number } }>(`/api/content/${sourceId}/remix`, { method: 'POST', body: { format, life_moment_id: remix.value.life_moment?.id } })
  await navigateTo(`/remix/${response.remix.id}`)
}

onMounted(async () => { const response = await apiFetch<{ remix: Remix }>(`/api/remixes/${route.params.id}`); remix.value = response.remix })
</script>

<template>
  <main v-if="remix" class="mx-auto max-w-[1180px] px-5 pb-16 pt-2 md:px-8">
    <header class="flex flex-wrap items-center gap-4"><NuxtLink :to="`/content/${remix.source_content?.id}`" class="text-sm text-[var(--muted)]">{{ $t('remix.backToAnalysis') }}</NuxtLink><div class="ml-auto flex items-center gap-3"><span v-if="saved" class="text-xs text-[var(--positive)]">{{ $t('remix.saved') }}</span><button class="rounded-full border border-[var(--line)] px-4 py-2.5 text-xs" :disabled="saving" @click="saveDraft">{{ saving ? $t('remix.saving') : $t('remix.saveDraft') }}</button><button class="rounded-full bg-[var(--ink)] px-4 py-2.5 text-xs text-white">{{ $t('remix.markReady') }}</button></div></header>
    <div class="mt-9 grid gap-8 lg:grid-cols-[.75fr_1.25fr]">
      <aside class="space-y-4 lg:sticky lg:top-8 lg:self-start">
        <section class="rounded-[20px] border border-[var(--line)] bg-[var(--surface)] p-5"><p class="remix-label">{{ $t('remix.originalPattern') }}</p><p class="mt-3 font-serif text-[22px] leading-7">“{{ remix.generated_content.original_pattern }}”</p></section>
        <section class="rounded-[20px] border border-[var(--line)] bg-[var(--surface)] p-5"><p class="remix-label">{{ $t('remix.whyItWorks') }}</p><ul class="mt-3 space-y-2"><li v-for="reason in remix.generated_content.why_it_works" :key="reason" class="flex gap-2 text-sm leading-5 text-[var(--muted)]"><span class="text-[#8a6413]">•</span>{{ reason }}</li></ul></section>
        <section class="rounded-[20px] border border-[#eadfc4] bg-[var(--accent-soft)] p-5"><p class="remix-label">{{ $t('remix.yourContext') }}</p><p class="mt-3 text-sm leading-6 text-[#3a3a3e]">{{ remix.generated_content.your_context }}</p></section>
      </aside>

      <section>
        <p class="text-[11px] font-semibold uppercase tracking-[.17em] text-[var(--faint)]">{{ $t('remix.yourVersion') }}</p><h1 class="mt-3 font-serif text-4xl tracking-[-.04em]">{{ $t('remix.madeFromStory') }}</h1><p class="mt-3 text-sm leading-6 text-[var(--muted)]">{{ $t('remix.madeFromStoryCopy') }}</p>
        <div class="mt-7 inline-flex rounded-full border border-[var(--line)] bg-[var(--paper)] p-1"><button v-for="item in ['reel','carousel','caption'] as const" :key="item" class="rounded-full px-4 py-2 text-xs capitalize" :class="remix.format === item ? 'bg-[var(--surface)] text-[var(--ink)] shadow-sm' : 'text-[var(--muted)]'" @click="switchFormat(item)">{{ item === 'caption' ? $t('remix.captionOption') : item }}</button></div>

        <div v-if="remix.format === 'carousel'" class="mt-7 space-y-3">
          <article v-for="(slide, index) in remix.generated_content.slides" :key="slide.id" class="group grid grid-cols-[50px_1fr_auto] items-start gap-3 rounded-[20px] border border-[var(--line)] bg-[var(--surface)] p-4"><div class="grid aspect-square place-items-center rounded-xl bg-[var(--night)] font-serif text-lg text-white">{{ index + 1 }}</div><textarea v-model="slide.text" rows="3" class="w-full resize-none bg-transparent p-1 text-[17px] leading-6 outline-none"/><div class="flex flex-col gap-1 opacity-50 transition group-hover:opacity-100"><button class="editor-control" :title="$t('remix.moveUp')" @click="moveSlide(index, -1)">↑</button><button class="editor-control" :title="$t('remix.moveDown')" @click="moveSlide(index, 1)">↓</button><button class="editor-control" :title="$t('remix.regenerate')" @click="regenerateSlide(index)">↻</button><button class="editor-control text-[#a4442f]" :title="$t('remix.delete')" @click="deleteSlide(index)">×</button></div></article>
        </div>
        <div v-else-if="remix.format === 'reel'" class="mt-7 space-y-4"><label class="editor-block"><span class="remix-label">{{ $t('remix.hook') }}</span><textarea v-model="remix.generated_content.hook" rows="2" class="editor-textarea text-xl"/></label><label class="editor-block"><span class="remix-label">{{ $t('remix.script') }}</span><textarea v-model="remix.generated_content.script" rows="8" class="editor-textarea"/></label><label class="editor-block"><span class="remix-label">{{ $t('remix.shotIdea') }}</span><textarea v-model="remix.generated_content.visual" rows="3" class="editor-textarea"/></label><label class="editor-block"><span class="remix-label">{{ $t('remix.cta') }}</span><textarea v-model="remix.generated_content.cta" rows="2" class="editor-textarea"/></label></div>
        <div v-else class="mt-7"><label class="editor-block"><span class="remix-label">{{ $t('remix.captionPost') }}</span><textarea v-model="remix.generated_content.caption" rows="18" class="editor-textarea text-[16px] leading-7"/></label></div>
      </section>
    </div>
  </main>
</template>

<style scoped>
.remix-label { @apply text-[10px] font-semibold uppercase tracking-[.16em] text-[var(--faint)]; }
.editor-control { @apply grid h-7 w-7 place-items-center rounded-lg text-xs hover:bg-[var(--paper)]; }
.editor-block { @apply block rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-5; }
.editor-textarea { @apply mt-3 w-full resize-none bg-transparent text-[15px] leading-6 outline-none; }
</style>
