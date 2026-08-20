<script setup lang="ts">
interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
}

const { t } = useI18n()
const { user } = useAuth()
const { apiFetch } = usePersonalApi()

const open = ref(false)
const draft = ref('')
const sending = ref(false)
const error = ref<string | null>(null)
const messages = ref<ChatMessage[]>([])

const inputEl = ref<HTMLTextAreaElement | null>(null)
const scrollEl = ref<HTMLDivElement | null>(null)

const firstName = computed(() => (user.value?.name || '').split(' ')[0] || '')
const greeting = computed(() => t('chat.greeting', { name: firstName.value }).replace('  ', ' '))
const emptyTitle = computed(() => t('chat.emptyTitle', { name: firstName.value }).replace(', ?', ' ?').replace('  ', ' '))
const starters = computed(() => [t('chat.starters.hooks'), t('chat.starters.repurpose'), t('chat.starters.caption')])

function scrollToBottom() {
  nextTick(() => {
    if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight
  })
}

function openChat() {
  open.value = true
  nextTick(() => inputEl.value?.focus())
  scrollToBottom()
}

function closeChat() {
  open.value = false
}

function toggle() {
  open.value ? closeChat() : openChat()
}

function clearConversation() {
  messages.value = []
  error.value = null
  nextTick(() => inputEl.value?.focus())
}

async function send(text?: string) {
  const content = (text ?? draft.value).trim()
  if (!content || sending.value) return

  messages.value.push({ role: 'user', content })
  draft.value = ''
  error.value = null
  sending.value = true
  scrollToBottom()

  try {
    const response = await apiFetch<{ reply: string }>('/api/chat', {
      method: 'POST',
      body: { messages: messages.value }
    })
    messages.value.push({ role: 'assistant', content: response.reply })
  } catch (exception: any) {
    error.value = exception?.data?.message || t('chat.error')
  } finally {
    sending.value = false
    scrollToBottom()
    nextTick(() => inputEl.value?.focus())
  }
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    send()
  }
}

// ⌘/ (ctrl+/ elsewhere) opens the assistant from anywhere; Escape closes it.
function onShortcut(event: KeyboardEvent) {
  if (event.key === '/' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    toggle()
  } else if (event.key === 'Escape' && open.value) {
    closeChat()
  }
}

// Lock body scroll while the centered modal owns the screen.
watch(open, (isOpen) => {
  if (!import.meta.client) return
  document.body.style.overflow = isOpen ? 'hidden' : ''
})

onMounted(() => { if (import.meta.client) window.addEventListener('keydown', onShortcut) })
onUnmounted(() => {
  if (import.meta.client) {
    window.removeEventListener('keydown', onShortcut)
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <div>
    <!-- Launcher: floats above the mobile bottom nav, and sits at the foot of
         the desktop rail where the assistant belongs. -->
    <button
      class="fixed bottom-20 right-5 z-40 grid h-14 w-14 place-items-center rounded-full bg-[var(--ink)] text-[var(--paper)] shadow-[0_8px_24px_rgba(23,23,26,.24)] transition hover:scale-105 md:hidden"
      :aria-label="$t('chat.open')"
      @click="openChat"
    >
      <AppIcon name="chat" :size="22" />
    </button>

    <button
      class="fixed bottom-5 left-3 z-40 hidden w-[240px] items-center gap-2.5 rounded-full border border-[var(--line)] bg-[var(--surface)] px-3.5 py-3 text-[13px] shadow-[0_2px_10px_rgba(23,23,26,.05)] transition hover:shadow-[0_4px_16px_rgba(23,23,26,.09)] md:flex"
      :aria-label="$t('chat.open')"
      @click="openChat"
    >
      <AppIcon name="sparkles" :size="16" class="text-[var(--ai)]" />
      <span class="font-medium">{{ $t('chat.ask') }}</span>
      <span class="ml-auto text-[11px] text-[var(--faint)]">⌘/</span>
    </button>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-if="open"
          class="fixed inset-0 z-50 flex items-start justify-center px-4 pb-4 pt-[12vh] md:pt-[14vh]"
          @click.self="closeChat"
        >
          <!-- Transparent, blurred scrim over the whole app -->
          <div class="absolute inset-0 bg-[var(--ink)]/20 backdrop-blur-[6px]" @click="closeChat" />

          <Transition
            enter-active-class="transition duration-200 ease-[cubic-bezier(.22,1,.36,1)]"
            enter-from-class="translate-y-3 scale-[.98] opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 scale-[.98] opacity-0"
            appear
          >
            <section
              v-if="open"
              class="relative flex max-h-[76vh] w-full max-w-[600px] flex-col overflow-hidden rounded-[26px] border border-white/60 bg-[var(--surface)]/80 shadow-[0_40px_120px_-24px_rgba(23,23,26,.5)] backdrop-blur-2xl"
              role="dialog"
              aria-modal="true"
              :aria-label="$t('chat.title')"
            >
              <button
                class="absolute right-4 top-4 z-10 grid h-8 w-8 place-items-center rounded-full text-[var(--faint)] transition hover:bg-[var(--paper)] hover:text-[var(--ink)]"
                :aria-label="$t('chat.close')"
                @click="closeChat"
              >
                <AppIcon name="close" :size="18" />
              </button>

              <div ref="scrollEl" class="flex-1 overflow-y-auto px-5 py-6 md:px-7">
                <!-- Empty state: centered greeting, in the command-bar spirit -->
                <div v-if="!messages.length" class="flex flex-col items-center px-2 py-6 text-center md:py-10">
                  <div class="grid h-12 w-12 place-items-center rounded-2xl bg-[var(--ink)] text-[var(--paper)] shadow-[0_8px_24px_rgba(23,23,26,.22)]">
                    <AppIcon name="sparkles" :size="24" />
                  </div>
                  <h2 class="mt-5 font-serif text-[30px] leading-tight tracking-[-.02em] md:text-[36px]">{{ emptyTitle }}</h2>
                  <p class="mt-2 max-w-sm text-[13px] leading-6 text-[var(--faint)]">{{ $t('chat.emptyCopy') }}</p>
                </div>

                <!-- Conversation -->
                <div v-else class="space-y-3">
                  <div class="max-w-[85%] rounded-2xl rounded-tl-md bg-[var(--paper)] px-4 py-2.5 text-[13px] leading-6 text-[var(--copy)]">
                    {{ greeting }}
                  </div>
                  <div
                    v-for="(message, index) in messages"
                    :key="index"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                  >
                    <div
                      class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-4 py-2.5 text-[13px] leading-6"
                      :class="message.role === 'user'
                        ? 'rounded-tr-md bg-[var(--ink)] text-[var(--paper)]'
                        : 'rounded-tl-md bg-[var(--paper)] text-[var(--copy)]'"
                    >{{ message.content }}</div>
                  </div>
                  <div v-if="sending" class="flex justify-start">
                    <div class="rounded-2xl rounded-tl-md bg-[var(--paper)] px-4 py-2.5 text-[13px] text-[var(--faint)]">{{ $t('chat.thinking') }}</div>
                  </div>
                </div>

                <p v-if="error" role="alert" class="mt-3 rounded-[12px] border border-[var(--danger-line)] bg-[var(--danger-soft)] px-3 py-2 text-[12px] text-[var(--danger)]">{{ error }}</p>
              </div>

              <div class="px-4 pb-4 md:px-5">
                <div class="flex items-end gap-2 rounded-[16px] border border-[var(--line)] bg-[var(--surface)] px-3.5 py-2.5 shadow-[0_1px_3px_rgba(23,23,26,.04)] focus-within:border-[var(--ink)]/30">
                  <textarea
                    ref="inputEl"
                    v-model="draft"
                    rows="1"
                    :placeholder="$t('chat.placeholder')"
                    class="max-h-32 flex-1 resize-none bg-transparent py-1 text-[14px] leading-6 text-[var(--ink)] outline-none placeholder:text-[var(--faint)]"
                    @keydown="onKeydown"
                  />
                  <div class="flex items-center gap-2">
                    <button
                      v-if="messages.length"
                      class="text-[11px] text-[var(--faint)] underline underline-offset-2 transition hover:text-[var(--ink)]"
                      @click="clearConversation"
                    >{{ $t('chat.clear') }}</button>
                    <button
                      class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--ink)] text-[var(--paper)] transition disabled:opacity-40"
                      :disabled="!draft.trim() || sending"
                      :aria-label="$t('chat.send')"
                      @click="send()"
                    >
                      <AppIcon name="send" :size="16" />
                    </button>
                  </div>
                </div>

                <!-- Starter prompts, shown until the conversation begins -->
                <div v-if="!messages.length" class="mt-3 flex flex-wrap justify-center gap-2">
                  <button
                    v-for="starter in starters"
                    :key="starter"
                    class="rounded-full border border-[var(--line)] bg-[var(--surface)] px-3.5 py-2 text-[12px] text-[var(--muted)] transition hover:border-[var(--ink)]/25 hover:text-[var(--ink)]"
                    @click="send(starter)"
                  >{{ starter }}</button>
                </div>
              </div>
            </section>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
