<script setup lang="ts">
import type { Remix } from '~/types/product'
import { creatorProfileUrl } from '~/types/product'

/* Instagram's own ceilings, so the counters on this screen mean something once
   the draft leaves it. */
const CAPTION_LIMIT = 2200
const CAPTION_FOLD = 125
const MAX_SLIDES = 20
/* A normal read-aloud pace. The reel timecodes are an estimate from the words
   actually written, not a promise about the edit. */
const WORDS_PER_SECOND = 2.6

const route = useRoute()
const { apiFetch } = usePersonalApi()
const { waitForRemix } = useRemixOpening()
const { t } = useI18n()
const toast = useToast()
const { user, loadUser } = useAuth()

const remix = ref<Remix | null>(null)
const loading = ref(true)
const saving = ref(false)
const copied = ref(false)
const retrying = ref(false)
const deleting = ref(false)
const regeneratingBlock = ref<string | null>(null)
/** A rewrite discards the draft, so the button asks once before it fires. */
const confirmingRedraft = ref(false)
/** Deleting has no undo, so it asks the same way. */
const confirmingDelete = ref(false)
const sourceAvatarFailed = ref(false)
/** The creator's own picture, at the top of the preview. */
const authorAvatarFailed = ref(false)
/** Which slide the preview is showing; the deck is swiped, not scrolled. */
const activeSlide = ref(0)
/** The last payload the server acknowledged, used to tell edited from saved. */
const pristine = ref('')
let confirmTimer: ReturnType<typeof setTimeout> | undefined
let deleteTimer: ReturnType<typeof setTimeout> | undefined

const slideInputs = ref<HTMLTextAreaElement[]>([])

const slides = computed(() => remix.value?.generated_content.slides ?? [])
const caption = computed(() => remix.value?.generated_content.caption ?? '')
const isReady = computed(() => remix.value?.status === 'ready')
const sourceCreatorInitial = computed(() => remix.value?.source_content?.creator.username.charAt(0).toUpperCase() || '')

/* --- The preview is the editor, so it needs who is posting ---------------- */

const authorHandle = computed(() => user.value?.instagram_username?.replace(/^@/, '') || '')
const authorInitial = computed(() => (authorHandle.value || user.value?.name || '?').charAt(0).toUpperCase())
const slide = computed(() => slides.value[activeSlide.value] || null)

/** The slide of the original this one was written against, shown as it is read. */
const inspiration = computed(() => {
  const position = slide.value?.source_position
  if (!position) return null
  const source = remix.value?.source_content
  return {
    position,
    text: source?.carousel_slides?.find(item => item.position === position)?.text || '',
    image: source?.media_urls?.[position - 1] || null
  }
})

function showSlide(index: number) {
  activeSlide.value = Math.min(Math.max(index, 0), Math.max(slides.value.length - 1, 0))
}
const dirty = computed(() => {
  if (!remix.value || !['draft', 'ready'].includes(remix.value.status)) return false
  return JSON.stringify(remix.value.generated_content) !== pristine.value
})

/* --- Reel: the draft measured as time ------------------------------------ */

function spokenSeconds(text?: string | null): number {
  return (text || '').trim().split(/\s+/).filter(Boolean).length / WORDS_PER_SECOND
}

function timecode(seconds: number): string {
  const total = Math.round(seconds)
  return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`
}

/** Each spoken beat starts where the previous one ended. */
const timing = computed(() => {
  const draft = remix.value?.generated_content
  let at = 0
  const ranges = {} as Record<'hook' | 'script' | 'ending' | 'cta', string>
  for (const key of ['hook', 'script', 'ending', 'cta'] as const) {
    const from = at
    at += spokenSeconds(draft?.[key])
    ranges[key] = `${timecode(from)}–${timecode(at)}`
  }
  return { ranges, runtime: timecode(at) }
})

/* --- Caption: what Instagram shows before it folds ------------------------ */

const captionFold = computed(() => caption.value.slice(0, CAPTION_FOLD).trimEnd())
const captionOverLimit = computed(() => caption.value.length > CAPTION_LIMIT)

/* --- Slides --------------------------------------------------------------- */

function moveSlide(index: number, direction: -1 | 1) {
  const list = remix.value?.generated_content.slides
  if (!list?.[index + direction]) return
  ;[list[index], list[index + direction]] = [list[index + direction]!, list[index]!]
}

function deleteSlide(index: number) {
  remix.value?.generated_content.slides?.splice(index, 1)
}

async function regenerateBlock(block: 'hook' | 'script' | 'visual' | 'ending' | 'cta' | 'caption' | 'slide', slideIndex?: number) {
  if (!remix.value || regeneratingBlock.value) return
  const identity = slideIndex === undefined ? block : `${block}:${slideIndex}`
  regeneratingBlock.value = identity
  try {
    if (dirty.value) {
      const saved = await save(remix.value.status === 'ready' ? 'ready' : 'draft')
      if (!saved) return
    }
    const response = await apiFetch<Pick<Remix, 'generated_content' | 'status'>>(
      `/api/remixes/${remix.value.id}/regenerate-block`,
      { method: 'POST', body: { block, slide_index: slideIndex } }
    )
    remix.value.generated_content = response.generated_content
    remix.value.status = response.status
    pristine.value = JSON.stringify(response.generated_content)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('remix.rewriteError')))
  } finally {
    regeneratingBlock.value = null
  }
}

async function addSlide() {
  const list = remix.value?.generated_content.slides
  if (!list || list.length >= MAX_SLIDES) return
  // A slide added by hand has no counterpart in the original.
  list.push({ id: Math.max(0, ...list.map(slide => slide.id)) + 1, text: '', image: '', source_position: null })
  showSlide(list.length - 1)
  await nextTick()
  slideInputs.value[0]?.focus()
}

/* --- Saving and copying --------------------------------------------------- */

async function save(status: 'draft' | 'ready' = 'draft'): Promise<boolean> {
  if (!remix.value || saving.value) return false
  saving.value = true
  const payload = JSON.stringify(remix.value.generated_content)
  try {
    await apiFetch(`/api/remixes/${remix.value.id}`, {
      method: 'PATCH',
      body: { generated_content: remix.value.generated_content, status }
    })
    remix.value.status = status
    pristine.value = payload
    return true
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('remix.saveError')))
    return false
  } finally {
    saving.value = false
  }
}

/** The draft as it would be pasted into Instagram. */
function plainText(): string {
  const draft = remix.value?.generated_content
  if (!draft) return ''
  if (remix.value?.format === 'carousel') {
    // The picture to shoot travels with the words it goes under.
    return (draft.slides || []).map((slide, index) => [
      `${index + 1}. ${slide.text}`,
      slide.image && `[${t('remix.yourImage')}] ${slide.image}`
    ].filter(Boolean).join('\n')).join('\n\n')
  }
  if (remix.value?.format === 'reel') {
    return [draft.hook, draft.script, draft.visual && `[${t('remix.shotIdea')}] ${draft.visual}`, draft.ending, draft.cta]
      .filter(Boolean).join('\n\n')
  }
  return draft.caption || ''
}

function fallbackCopy(text: string): boolean {
  if (!import.meta.client) return false
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.setAttribute('readonly', '')
  textarea.style.position = 'fixed'
  textarea.style.opacity = '0'
  document.body.appendChild(textarea)
  textarea.select()
  const copied = document.execCommand('copy')
  textarea.remove()
  return copied
}

async function copyDraft() {
  if (!remix.value) return
  const text = plainText()
  try {
    if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(text)
    else if (!fallbackCopy(text)) throw new Error('Clipboard unavailable')
    copied.value = true
    setTimeout(() => { copied.value = false }, 1800)
    void apiFetch(`/api/remixes/${remix.value.id}/copied`, { method: 'POST' }).catch(() => undefined)
  } catch {
    if (fallbackCopy(text)) {
      copied.value = true
      setTimeout(() => { copied.value = false }, 1800)
      void apiFetch(`/api/remixes/${remix.value.id}/copied`, { method: 'POST' }).catch(() => undefined)
      return
    }
    toast.error(t('remix.copyError'))
  }
}

/* A beat should be exactly as tall as the words in it, so the editor never
   shows a field of empty space under a short line. */
function fitToContent(element: HTMLTextAreaElement) {
  element.style.height = 'auto'
  element.style.height = `${element.scrollHeight}px`
}

const vAutosize = {
  mounted(element: HTMLTextAreaElement) {
    fitToContent(element)
    element.addEventListener('input', () => fitToContent(element))
  },
  updated: fitToContent
}

function guardUnload(event: BeforeUnloadEvent) {
  if (!dirty.value) return
  event.preventDefault()
  event.returnValue = ''
}

async function loadRemix() {
  try {
    const response = await apiFetch<{ remix: Remix }>(`/api/remixes/${route.params.id}`)
    if (response.remix.status === 'generating') {
      await navigateTo('/drafts')
      return
    }
    remix.value = response.remix
    sourceAvatarFailed.value = false
    if (response.remix.status !== 'failed') {
      pristine.value = JSON.stringify(response.remix.generated_content)
    }
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('remix.loadError')))
  } finally {
    loading.value = false
  }
}

async function retryGeneration() {
  if (!remix.value || retrying.value) return
  retrying.value = true
  try {
    const response = await apiFetch<{ remix: Remix }>(`/api/remixes/${remix.value.id}/retry`, { method: 'POST' })
    const generated = response.remix.status === 'generating'
      ? await waitForRemix(response.remix.id)
      : response.remix
    if (!generated) return
    remix.value = generated
    if (generated.status !== 'failed') pristine.value = JSON.stringify(generated.generated_content)
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('remix.retryError')))
  } finally {
    retrying.value = false
  }
}

/** First click arms the action, second one runs it. It disarms on its own so a
    stray click never sits there waiting to wipe the draft. */
function askRedraft() {
  if (!confirmingRedraft.value) {
    confirmingRedraft.value = true
    clearTimeout(confirmTimer)
    confirmTimer = setTimeout(() => { confirmingRedraft.value = false }, 4000)
    return
  }
  clearTimeout(confirmTimer)
  confirmingRedraft.value = false
  retryGeneration()
}

function askDelete() {
  if (!confirmingDelete.value) {
    confirmingDelete.value = true
    clearTimeout(deleteTimer)
    deleteTimer = setTimeout(() => { confirmingDelete.value = false }, 4000)
    return
  }
  clearTimeout(deleteTimer)
  confirmingDelete.value = false
  deleteDraft()
}

async function deleteDraft() {
  if (!remix.value || deleting.value) return
  deleting.value = true
  try {
    await apiFetch(`/api/remixes/${remix.value.id}`, { method: 'DELETE' })
    toast.success(t('remix.deleted'))
    await navigateTo('/drafts')
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('remix.deleteError')))
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  window.addEventListener('beforeunload', guardUnload)
  // The preview posts as the creator, so it needs their account.
  if (!user.value) void loadUser().catch(() => undefined)
  await loadRemix()
})

watch(() => route.params.id, async (id, previousId) => {
  if (id === previousId) return
  loading.value = true
  remix.value = null
  activeSlide.value = 0
  confirmingRedraft.value = false
  confirmingDelete.value = false
  await loadRemix()
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', guardUnload)
  clearTimeout(confirmTimer)
  clearTimeout(deleteTimer)
})
</script>

<template>
  <main class="pb-24">
    <div v-if="loading" />

    <section v-else-if="remix?.status === 'failed'" class="page-shell pt-16 text-center">
      <span class="mx-auto grid h-12 w-12 place-items-center rounded-[16px] bg-[var(--accent-soft)] text-[var(--accent-ink)]">
        <AppIcon name="sparkles" :size="21" />
      </span>
      <h1 class="mt-6 font-serif text-[34px] tracking-[-.03em]">{{ $t('remix.generationFailed') }}</h1>
      <p class="mx-auto mt-3 max-w-[42ch] text-sm leading-6 text-[var(--muted)]">{{ $t('remix.generationFailedCopy') }}</p>
      <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
        <button
          class="inline-flex h-11 items-center rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:opacity-60"
          :disabled="retrying || deleting"
          @click="retryGeneration"
        >
          {{ retrying ? $t('remix.retrying') : $t('remix.retry') }}
        </button>
        <button
          class="inline-flex h-11 items-center rounded-full border border-[var(--line)] bg-[var(--surface)] px-5 text-[14px] text-[var(--muted)] transition hover:text-[var(--danger)] disabled:opacity-60"
          :class="confirmingDelete ? 'text-[var(--danger)]' : ''"
          :disabled="retrying || deleting"
          @click="askDelete"
        >
          {{ confirmingDelete ? $t('remix.deleteConfirm') : $t('remix.delete') }}
        </button>
      </div>
    </section>

    <div v-else-if="remix" :inert="retrying || deleting" :aria-busy="retrying || deleting">
      <!-- Everything that changes the draft's state lives in one bar that stays
           in reach while the editor scrolls. -->
      <div class="sticky top-[calc(4rem+env(safe-area-inset-top))] z-10 border-b border-[var(--line)] bg-[var(--paper)]/92 backdrop-blur md:top-[74px]">
        <!-- Saving is manual, so the two buttons that write the draft are pinned
             outside the scroller: on a phone the rest of the bar slides under
             them rather than pushing them off the screen. -->
        <div class="page-shell flex h-16 items-center gap-3">
          <!-- A phone has no room for the state, the three tools and the two
               buttons that write, so the fade says the row carries on rather
               than leaving a chip looking accidentally cut. -->
          <div class="flex min-w-0 flex-1 items-center gap-3 overflow-x-auto pr-2 [mask-image:linear-gradient(to_right,#000_calc(100%-1.75rem),transparent)] [scrollbar-width:none] md:[mask-image:none] [&::-webkit-scrollbar]:hidden">
            <NuxtLink
              :to="`/content/${remix.source_content?.id}`"
              :aria-label="$t('remix.backToAnalysis')"
              class="b-focus -ml-1 flex shrink-0 items-center gap-1.5 rounded-full px-1 py-1 text-[13px] text-[var(--muted)] transition hover:text-[var(--ink)]"
            >
              <AppIcon name="chevron" :size="15" class="rotate-180" />
              <span class="hidden sm:inline">{{ $t('remix.backToAnalysis') }}</span>
            </NuxtLink>

            <span class="status-chip" :class="isReady ? 'status-ready' : 'status-draft'">
              <span class="status-dot" />{{ isReady ? $t('remix.statusReady') : $t('remix.statusDraft') }}
            </span>

            <span class="hidden text-[12px] text-[var(--faint)] md:inline">
              {{ saving ? $t('remix.saving') : dirty ? $t('remix.unsaved') : $t('remix.allSaved') }}
            </span>

            <!-- Wide enough and the row has slack, so the tools sit against the
                 write buttons; on a phone the auto margin is nothing and they
                 simply follow the state chip into the scroll. -->
            <div class="ml-auto flex shrink-0 items-center gap-2 pl-3">
              <button
                class="bar-button"
                :class="confirmingDelete ? 'text-[var(--danger)]' : 'hover:text-[var(--danger)]'"
                :disabled="deleting || saving"
                :aria-label="confirmingDelete ? $t('remix.deleteConfirm') : $t('remix.delete')"
                @click="askDelete"
              >
                <AppIcon name="trash" :size="15" />
                <!-- Armed, the question has to be readable on a phone too. -->
                <span :class="confirmingDelete ? '' : 'hidden sm:inline'">
                  {{ confirmingDelete ? $t('remix.deleteConfirm') : $t('remix.delete') }}
                </span>
              </button>
              <button class="bar-button" :aria-label="$t('remix.copy')" @click="copyDraft">
                <AppIcon :name="copied ? 'check' : 'copy'" :size="15" />
                <span class="hidden sm:inline">{{ copied ? $t('remix.copied') : $t('remix.copy') }}</span>
              </button>
              <button
                class="bar-button"
                :class="confirmingRedraft ? 'text-[var(--accent-ink)]' : ''"
                :disabled="retrying || saving"
                :aria-label="$t('remix.redraft')"
                @click="askRedraft"
              >
                <AppIcon name="sparkles" :size="15" />
                <!-- Armed, the question has to be readable on a phone too, where the
                     other labels stay hidden. -->
                <span :class="confirmingRedraft ? '' : 'hidden sm:inline'">
                  {{ retrying ? $t('remix.retrying') : confirmingRedraft ? $t('remix.redraftConfirm') : $t('remix.redraft') }}
                </span>
              </button>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <button class="bar-button" :disabled="saving || !dirty" @click="save(remix.status === 'ready' ? 'ready' : 'draft')">
              {{ $t('remix.saveDraft') }}
            </button>
            <!-- The chip already carries the state, so this slot only ever names
                 the action available from it. -->
            <button
              v-if="isReady"
              class="bar-button"
              :disabled="saving"
              @click="save('draft')"
            >
              {{ $t('remix.backToDraft') }}
            </button>
            <button
              v-else
              class="inline-flex h-9 items-center rounded-full b-btn-red px-4 text-[12.5px] font-medium transition disabled:opacity-60"
              :disabled="saving"
              @click="save('ready')"
            >
              {{ $t('remix.markReady') }}
            </button>
          </div>
        </div>
      </div>

      <div class="page-shell pt-8">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_312px] lg:gap-10">
          <!-- The draft comes first: on a phone you land on your own words, not
               on the post they were borrowed from. -->
          <section class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
              <span class="inline-flex h-9 items-center gap-2 rounded-full border border-[var(--line)] bg-[var(--surface)] px-4 text-[12.5px] font-medium">
                <AppIcon :name="remix.format === 'caption' ? 'text' : remix.format" :size="15" />
                {{ $t(`remix.formats.${remix.format}`) }}
              </span>
              <p class="text-[12px] leading-5 text-[var(--faint)]">{{ $t('remix.formatFollowsSource') }}</p>
            </div>

            <!-- Carousel: the draft is written where it will be read. The frame
                 is the post as Instagram shows it — the creator's own account at
                 the top, one slide at a time — so what is typed is what a reader
                 gets, and the picture to shoot is named on the slide it belongs
                 to rather than in a brief nobody opens. -->
            <div v-if="remix.format === 'carousel'" class="mt-8">
              <div class="flex items-baseline justify-between">
                <p class="remix-label">{{ $t('remix.slideDeck') }}</p>
                <p class="text-[12px] text-[var(--faint)]">{{ $t('remix.slideCount', { count: slides.length }) }}</p>
              </div>

              <div class="mx-auto mt-4 w-full max-w-[468px]">
                <template v-if="slide">
                  <article class="overflow-hidden rounded-[14px] border border-[var(--line)] bg-[var(--surface)]">
                    <header class="flex items-center gap-3 px-4 py-3">
                      <img
                        v-if="user?.avatar_url && !authorAvatarFailed"
                        :src="user.avatar_url"
                        alt=""
                        class="h-8 w-8 shrink-0 rounded-full bg-[var(--sand)] object-cover"
                        @error="authorAvatarFailed = true"
                      >
                      <span v-else class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--sand)] text-[11px] font-semibold uppercase text-[var(--muted)]">
                        {{ authorInitial }}
                      </span>
                      <span class="min-w-0 flex-1 truncate text-[13px] font-medium">{{ authorHandle ? `@${authorHandle}` : $t('remix.yourAccount') }}</span>
                      <AppIcon name="dots" :size="16" class="shrink-0 text-[var(--faint)]" />
                    </header>

                    <!-- The slide itself, at the ratio Instagram gives it. -->
                    <div
                      class="relative flex aspect-[4/5] flex-col gap-3 p-5"
                      :class="activeSlide === 0 ? 'b-night text-white' : 'bg-[var(--paper)]'"
                    >
                      <div class="flex items-center justify-between">
                        <span class="remix-label" :class="activeSlide === 0 && 'text-[var(--gold)]'">
                          {{ activeSlide === 0 ? $t('remix.cover') : $t('remix.slideOf', { index: activeSlide + 1, total: slides.length }) }}
                        </span>
                        <span class="text-[10px] tabular-nums" :class="activeSlide === 0 ? 'text-white/40' : 'text-[var(--faint)]'">{{ slide.text.length }}</span>
                      </div>

                      <!-- What to put behind the words. It is dashed because the
                           picture is not here yet: it is what the creator goes
                           and makes. -->
                      <div
                        class="rounded-[12px] border border-dashed p-3"
                        :class="activeSlide === 0 ? 'border-white/25 bg-white/5' : 'border-[var(--line)] bg-[var(--surface)]'"
                      >
                        <p class="remix-label" :class="activeSlide === 0 && 'text-white/45'">{{ $t('remix.yourImage') }}</p>
                        <!-- Not autosized: the band sits inside a frame with a
                             fixed ratio, and growing it would push the words it
                             belongs to out of the slide. -->
                        <textarea
                          v-model="slide.image"
                          rows="3"
                          :aria-label="$t('remix.yourImage')"
                          :placeholder="$t('remix.imagePlaceholder')"
                          class="slide-image-field"
                          :class="activeSlide === 0 ? 'caret-white placeholder:text-white/30' : 'text-[var(--copy)] placeholder:text-[var(--faint)]'"
                        />
                      </div>

                      <textarea
                        ref="slideInputs"
                        v-model="slide.text"
                        :placeholder="activeSlide === 0 ? $t('remix.coverPlaceholder') : $t('remix.slidePlaceholder')"
                        class="min-h-0 flex-1 resize-none bg-transparent font-serif text-[23px] leading-[1.22] tracking-[-.015em] outline-none"
                        :class="activeSlide === 0 ? 'caret-white placeholder:text-white/30' : 'placeholder:text-[var(--faint)]'"
                      />

                      <!-- Swiping is how a carousel is read, so the frame is
                           moved through rather than scrolled past. -->
                      <button
                        v-if="activeSlide > 0"
                        class="slide-arrow b-focus left-2"
                        :aria-label="$t('remix.previousSlide')"
                        @click="showSlide(activeSlide - 1)"
                      >
                        <AppIcon name="chevron" :size="15" class="rotate-180" />
                      </button>
                      <button
                        v-if="activeSlide < slides.length - 1"
                        class="slide-arrow b-focus right-2"
                        :aria-label="$t('remix.nextSlide')"
                        @click="showSlide(activeSlide + 1)"
                      >
                        <AppIcon name="chevron" :size="15" />
                      </button>
                    </div>

                    <div v-if="slides.length > 1" class="flex items-center justify-center gap-1.5 py-3">
                      <button
                        v-for="(item, index) in slides"
                        :key="item.id"
                        class="b-focus h-1.5 w-1.5 rounded-full transition"
                        :class="index === activeSlide ? 'bg-[var(--accent)]' : 'bg-[var(--line)]'"
                        :aria-label="$t('remix.slideOf', { index: index + 1, total: slides.length })"
                        :aria-current="index === activeSlide"
                        @click="showSlide(index)"
                      />
                    </div>

                    <!-- The row a reader sees under any post. It does nothing
                         here: it is there so the draft is judged in place. -->
                    <div class="flex items-center gap-4 border-t border-[var(--line-soft)] px-4 py-3 text-[var(--faint)]" aria-hidden="true">
                      <AppIcon name="heart" :size="19" />
                      <AppIcon name="chat" :size="19" />
                      <AppIcon name="paper-plane" :size="19" />
                      <AppIcon name="bookmark" :size="19" class="ml-auto" />
                    </div>
                  </article>

                  <!-- The tools for the slide on screen, under the frame rather
                       than over the words. -->
                  <div class="mt-3 flex flex-wrap items-center gap-1">
                    <button class="editor-control" :title="$t('remix.moveUp')" :aria-label="$t('remix.moveUp')" :disabled="activeSlide === 0" @click="moveSlide(activeSlide, -1); showSlide(activeSlide - 1)">
                      <AppIcon name="arrow-up" :size="14" class="-rotate-90" />
                    </button>
                    <button class="editor-control" :title="$t('remix.moveDown')" :aria-label="$t('remix.moveDown')" :disabled="activeSlide === slides.length - 1" @click="moveSlide(activeSlide, 1); showSlide(activeSlide + 1)">
                      <AppIcon name="arrow-up" :size="14" class="rotate-90" />
                    </button>
                    <button class="editor-control" :title="$t('remix.regenerate')" :aria-label="$t('remix.regenerate')" :disabled="!!regeneratingBlock" @click="regenerateBlock('slide', activeSlide)">
                      <AppIcon name="refresh" :size="14" :class="regeneratingBlock === `slide:${activeSlide}` && 'animate-spin'" />
                    </button>
                    <button class="editor-control text-[var(--danger)]" :title="$t('remix.delete')" :aria-label="$t('remix.delete')" @click="deleteSlide(activeSlide); showSlide(activeSlide)">
                      <AppIcon name="trash" :size="14" />
                    </button>
                    <button
                      v-if="slides.length < MAX_SLIDES"
                      class="ml-auto inline-flex h-8 items-center gap-1.5 rounded-full border border-dashed border-[var(--line)] px-3 text-[12px] text-[var(--muted)] transition hover:border-[var(--ink)] hover:text-[var(--ink)]"
                      @click="addSlide"
                    >
                      <AppIcon name="plus" :size="14" />{{ $t('remix.addSlide') }}
                    </button>
                    <p v-else class="ml-auto text-[12px] text-[var(--faint)]">{{ $t('remix.slideLimit', { max: MAX_SLIDES }) }}</p>
                  </div>

                  <!-- The slide of the original this one was written against.
                       Same position, so the deck can be read side by side. -->
                  <div v-if="inspiration" class="mt-4 flex gap-3 rounded-[14px] border border-[var(--line)] bg-[var(--surface)] p-3">
                    <img
                      v-if="inspiration.image"
                      :src="inspiration.image"
                      alt=""
                      class="h-16 w-16 shrink-0 rounded-[10px] bg-[var(--sand)] object-cover"
                    >
                    <span v-else class="grid h-16 w-16 shrink-0 place-items-center rounded-[10px] bg-[var(--sand-soft)] text-[var(--faint)]">
                      <AppIcon name="carousel" :size="18" />
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="remix-label block">{{ $t('remix.inspiration', { index: inspiration.position }) }}</span>
                      <span v-if="inspiration.text" class="mt-1.5 block text-[13px] leading-5 text-[var(--muted)]">{{ inspiration.text }}</span>
                      <span v-else class="mt-1.5 block text-[13px] leading-5 text-[var(--faint)]">{{ $t('remix.inspirationUnread') }}</span>
                    </span>
                  </div>
                </template>

                <button
                  v-else
                  class="flex aspect-[4/5] w-full flex-col items-center justify-center gap-2 rounded-[14px] border border-dashed border-[var(--line)] text-[var(--faint)] transition hover:border-[var(--ink)] hover:text-[var(--ink)]"
                  @click="addSlide"
                >
                  <AppIcon name="plus" :size="20" />
                  <span class="text-[12.5px]">{{ $t('remix.addSlide') }}</span>
                </button>
              </div>
            </div>

            <!-- Reel: a reel is time, so the draft is stamped with the seconds
                 its own words take at a normal speaking pace. -->
            <div v-else-if="remix.format === 'reel'" class="mt-8">
              <div class="flex items-baseline justify-between">
                <p class="remix-label">{{ $t('remix.script') }}</p>
                <p class="inline-flex items-center gap-1.5 text-[12px] text-[var(--muted)]">
                  <AppIcon name="clock" :size="13" />
                  <span class="tabular-nums">{{ timing.runtime }}</span>
                  <span class="text-[var(--faint)]">{{ $t('remix.estimated') }}</span>
                </p>
              </div>

              <div class="mt-4 overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]">
                <div class="beat">
                  <span class="beat-stamp">{{ timing.ranges.hook }}</span>
                  <span class="beat-body">
                    <span class="flex items-center justify-between gap-3"><span class="remix-label">{{ $t('remix.hook') }}</span><button type="button" class="block-rewrite" :disabled="!!regeneratingBlock" @click.prevent="regenerateBlock('hook')"><AppIcon name="refresh" :size="13" :class="regeneratingBlock === 'hook' && 'animate-spin'" />{{ $t('remix.rewriteBlock') }}</button></span>
                    <textarea v-model="remix.generated_content.hook" v-autosize rows="1" :aria-label="$t('remix.hook')" :placeholder="$t('remix.hookPlaceholder')" class="editor-hook" />
                  </span>
                </div>
                <div class="beat">
                  <span class="beat-stamp">{{ timing.ranges.script }}</span>
                  <span class="beat-body">
                    <span class="flex items-center justify-between gap-3"><span class="remix-label">{{ $t('remix.body') }}</span><button type="button" class="block-rewrite" :disabled="!!regeneratingBlock" @click.prevent="regenerateBlock('script')"><AppIcon name="refresh" :size="13" :class="regeneratingBlock === 'script' && 'animate-spin'" />{{ $t('remix.rewriteBlock') }}</button></span>
                    <textarea v-model="remix.generated_content.script" v-autosize rows="3" :aria-label="$t('remix.body')" :placeholder="$t('remix.scriptPlaceholder')" class="editor-textarea" />
                  </span>
                </div>
                <div class="beat">
                  <!-- Not spoken, so it carries no timecode. -->
                  <span class="beat-stamp text-[var(--faint)]"><AppIcon name="eye" :size="14" /></span>
                  <span class="beat-body">
                    <span class="flex items-center justify-between gap-3"><span class="remix-label">{{ $t('remix.onScreen') }}</span><button type="button" class="block-rewrite" :disabled="!!regeneratingBlock" @click.prevent="regenerateBlock('visual')"><AppIcon name="refresh" :size="13" :class="regeneratingBlock === 'visual' && 'animate-spin'" />{{ $t('remix.rewriteBlock') }}</button></span>
                    <textarea v-model="remix.generated_content.visual" v-autosize rows="2" :aria-label="$t('remix.onScreen')" :placeholder="$t('remix.visualPlaceholder')" class="editor-textarea text-[var(--copy)]" />
                  </span>
                </div>
                <div class="beat">
                  <span class="beat-stamp">{{ timing.ranges.ending }}</span>
                  <span class="beat-body">
                    <span class="flex items-center justify-between gap-3"><span class="remix-label">{{ $t('remix.ending') }}</span><button type="button" class="block-rewrite" :disabled="!!regeneratingBlock" @click.prevent="regenerateBlock('ending')"><AppIcon name="refresh" :size="13" :class="regeneratingBlock === 'ending' && 'animate-spin'" />{{ $t('remix.rewriteBlock') }}</button></span>
                    <textarea v-model="remix.generated_content.ending" v-autosize rows="1" :aria-label="$t('remix.ending')" :placeholder="$t('remix.endingPlaceholder')" class="editor-textarea" />
                  </span>
                </div>
                <div class="beat">
                  <span class="beat-stamp">{{ timing.ranges.cta }}</span>
                  <span class="beat-body">
                    <span class="flex items-center justify-between gap-3"><span class="remix-label">{{ $t('remix.cta') }}</span><button type="button" class="block-rewrite" :disabled="!!regeneratingBlock" @click.prevent="regenerateBlock('cta')"><AppIcon name="refresh" :size="13" :class="regeneratingBlock === 'cta' && 'animate-spin'" />{{ $t('remix.rewriteBlock') }}</button></span>
                    <textarea v-model="remix.generated_content.cta" v-autosize rows="1" :aria-label="$t('remix.cta')" :placeholder="$t('remix.ctaPlaceholder')" class="editor-textarea" />
                  </span>
                </div>
              </div>
            </div>

            <!-- Caption: one column, with the line Instagram folds drawn where
                 it actually falls. -->
            <div v-else class="mt-8">
              <div class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)]">
                <div class="border-b border-[var(--line-soft)] bg-[var(--paper)] px-6 py-5">
                  <p class="remix-label">{{ $t('remix.beforeMore') }}</p>
                  <p class="mt-2 text-[14px] leading-6 text-[var(--copy)]">
                    <span v-if="captionFold">{{ captionFold }}</span>
                    <span v-else class="text-[var(--faint)]">{{ $t('remix.beforeMoreEmpty') }}</span>
                    <span v-if="caption.length > CAPTION_FOLD" class="text-[var(--faint)]">… {{ $t('remix.more') }}</span>
                  </p>
                </div>
                <div class="flex items-center justify-between border-b border-[var(--line-soft)] px-6 py-3">
                  <span class="remix-label">{{ $t('remix.captionPost') }}</span>
                  <button type="button" class="block-rewrite" :disabled="!!regeneratingBlock" @click="regenerateBlock('caption')"><AppIcon name="refresh" :size="13" :class="regeneratingBlock === 'caption' && 'animate-spin'" />{{ $t('remix.rewriteBlock') }}</button>
                </div>
                <textarea
                  v-model="remix.generated_content.caption"
                  v-autosize
                  rows="12"
                  style="min-height: 20rem"
                  :placeholder="$t('remix.captionPlaceholder')"
                  class="w-full resize-none bg-transparent px-6 py-5 text-[16px] leading-7 outline-none placeholder:text-[var(--faint)]"
                />
                <div class="flex items-center justify-between border-t border-[var(--line-soft)] px-6 py-3">
                  <span class="remix-label">{{ $t('remix.captionPost') }}</span>
                  <span class="text-[12px] tabular-nums" :class="captionOverLimit ? 'text-[var(--danger)]' : 'text-[var(--faint)]'">
                    {{ caption.length }} / {{ CAPTION_LIMIT }}
                  </span>
                </div>
              </div>
            </div>
          </section>

          <!-- The margin: where the draft came from, kept beside it rather than
               in front of it. -->
          <aside class="min-w-0 space-y-3 lg:sticky lg:top-[162px] lg:self-start">
            <div class="overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]">
              <a
                v-if="remix.source_content?.creator"
                :href="creatorProfileUrl(remix.source_content.creator.username)"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center gap-3 border-b border-[var(--line-soft)] p-4 transition hover:bg-[var(--paper)]"
              >
                <img
                  v-if="remix.source_content.creator.avatar_url && !sourceAvatarFailed"
                  :src="remix.source_content.creator.avatar_url"
                  alt=""
                  class="h-10 w-10 shrink-0 rounded-full bg-[var(--sand)] object-cover"
                  @error="sourceAvatarFailed = true"
                >
                <span v-else class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[var(--sand)] text-xs font-semibold uppercase text-[var(--muted)]">
                  {{ sourceCreatorInitial }}
                </span>
                <span class="min-w-0 flex-1">
                  <span class="remix-label block">{{ $t('remix.borrowedFrom') }}</span>
                  <span class="mt-1 block truncate text-[13.5px]">@{{ remix.source_content.creator.username }}</span>
                </span>
                <AppIcon name="arrow" :size="15" class="shrink-0 -rotate-45 text-[var(--faint)]" />
              </a>

              <a
                v-if="remix.source_content?.source_url"
                :href="remix.source_content.source_url"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center justify-between border-b border-[var(--line-soft)] px-5 py-3 text-[12.5px] text-[var(--muted)] transition hover:bg-[var(--paper)] hover:text-[var(--ink)]"
              >
                {{ $t('remix.openSource') }}
                <AppIcon name="arrow" :size="14" class="-rotate-45" />
              </a>

              <div class="p-5">
                <p class="remix-label">{{ $t('remix.originalPattern') }}</p>
                <p class="mt-3 font-serif text-[21px] leading-[1.28] tracking-[-.01em]">“{{ remix.generated_content.original_pattern }}”</p>
              </div>

              <div v-if="remix.generated_content.why_it_works?.length" class="border-t border-[var(--line-soft)] px-5 py-4">
                <p class="remix-label">{{ $t('remix.whyItWorks') }}</p>
                <ul class="mt-1 divide-y divide-[var(--line-soft)]">
                  <li v-for="reason in remix.generated_content.why_it_works" :key="reason" class="flex gap-2.5 py-3 text-[13.5px] leading-5 text-[var(--muted)]">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--accent)]" />{{ reason }}
                  </li>
                </ul>
              </div>
            </div>

            <div class="overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)]">
              <div class="p-5">
                <p class="remix-label">{{ $t('remix.yourContext') }}</p>
                <p class="mt-2.5 flex gap-2.5 text-[14px] leading-6 text-[var(--copy)]">
                  <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--accent)]" />{{ remix.generated_content.your_context }}
                </p>
              </div>
              <NuxtLink
                v-if="remix.life_moment"
                to="/personal"
                class="flex items-center justify-between border-t border-[var(--line-soft)] px-5 py-3 text-[12.5px] text-[var(--muted)] transition hover:bg-[var(--paper)] hover:text-[var(--ink)]"
              >
                {{ $t('remix.editMoments') }}<AppIcon name="arrow" :size="14" />
              </NuxtLink>
            </div>
          </aside>
        </div>
      </div>
    </div>

    <div v-else class="page-shell pt-16 text-center">
      <p class="font-serif text-[30px] tracking-[-.02em]">{{ $t('remix.loadError') }}</p>
      <p class="mx-auto mt-3 max-w-[38ch] text-sm leading-6 text-[var(--muted)]">{{ $t('remix.loadErrorCopy') }}</p>
    </div>
  </main>
</template>

<style scoped>
.remix-label { @apply text-[10px] font-semibold uppercase tracking-[.16em] text-[var(--faint)]; }
.block-rewrite { @apply inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[11px] text-[var(--muted)] transition hover:bg-[var(--paper)] hover:text-[var(--ink)] disabled:cursor-wait disabled:opacity-50; }

.bar-button { @apply inline-flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border border-[var(--line)] bg-[var(--surface)] px-3.5 text-[12.5px] text-[var(--muted)] transition hover:text-[var(--ink)] disabled:opacity-50 sm:px-4; }

.status-chip { @apply inline-flex h-7 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 text-[11px] font-medium; }
.status-draft { @apply border-[var(--line)] bg-[var(--surface)] text-[var(--muted)]; }
.status-ready { @apply border-[var(--positive-line)] bg-[var(--positive-soft)] text-[var(--positive)]; }
.status-dot { @apply h-1.5 w-1.5 rounded-full bg-current; }

.slide-arrow { @apply absolute top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full bg-black/55 text-white backdrop-blur transition hover:bg-black/75; }

.editor-control { @apply relative grid h-7 w-7 place-items-center rounded-lg text-[var(--muted)] transition hover:bg-[var(--paper)] hover:text-[var(--ink)] disabled:pointer-events-none disabled:opacity-25; }
/* The controls sit in the corner of a slide, so they stay small and carry the
   thumb's target behind them instead of growing into the text. */
@media (pointer: coarse) {
  .editor-control::after { content: ""; position: absolute; inset: -8px; }
}
.control-dark { @apply text-white/55 hover:bg-white/10 hover:text-white; }

/* The reel spine: a stamped margin on the left, the words on the right. */
.beat { @apply grid gap-2 border-b border-[var(--line-soft)] px-5 py-5 last:border-0 sm:grid-cols-[76px_minmax(0,1fr)] sm:gap-5 sm:px-6; }
.beat-stamp { @apply text-[11.5px] tabular-nums text-[var(--muted)] sm:pt-1; }
.beat-body { @apply block; }
.editor-textarea { @apply mt-2 w-full resize-none bg-transparent text-[15.5px] leading-7 outline-none placeholder:text-[var(--faint)]; }
/* The direction for the picture, inside a frame that cannot grow: it scrolls
   rather than pushing the words it belongs to out of the slide. */
.slide-image-field { @apply mt-1.5 w-full resize-none overflow-y-auto bg-transparent text-[12.5px] leading-5 outline-none; }

/* iOS zooms the page in whenever a focused field is set under 16px, and never
   zooms back out, so on a touch pointer the field is lifted to the threshold.
   The size the design asks for is kept everywhere a pointer is doing the
   typing. */
@media (pointer: coarse) { .editor-textarea, .slide-image-field { font-size: 16px; } }
/* The hook is the only line in the draft that is set as display type. */
.editor-hook { @apply mt-2 w-full resize-none bg-transparent font-serif text-[27px] leading-[1.18] tracking-[-.025em] outline-none placeholder:text-[var(--faint)]; }
</style>
