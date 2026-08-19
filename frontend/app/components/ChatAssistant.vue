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

const greeting = computed(() => t('chat.greeting', { name: (user.value?.name || '').split(' ')[0] || '' }).replace('  ', ' '))

function scrollToBottom() {
  nextTick(() => {
    if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight
  })
}

function toggle() {
  open.value = !open.value
  if (open.value) {
    nextTick(() => inputEl.value?.focus())
    scrollToBottom()
  }
}

function clearConversation() {
  messages.value = []
  error.value = null
}

async function send() {
  const content = draft.value.trim()
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
  }
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    send()
  }
}

// ⌘/ (ctrl+/ elsewhere) opens the assistant from anywhere, which is what the
// launcher advertises.
function onShortcut(event: KeyboardEvent) {
  if (event.key === '/' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    toggle()
  }
}

onMounted(() => { if (import.meta.client) window.addEventListener('keydown', onShortcut) })
onUnmounted(() => { if (import.meta.client) window.removeEventListener('keydown', onShortcut) })
</script>

<template>
  <div>
    <!-- Launcher: floats above the mobile bottom nav, and sits at the foot of
         the desktop rail where the assistant belongs. -->
    <button
      class="fixed bottom-20 right-5 z-40 grid h-14 w-14 place-items-center rounded-full bg-[var(--ink)] text-white shadow-[0_8px_24px_rgba(23,23,26,.24)] transition hover:scale-105 md:hidden"
      :aria-label="open ? $t('chat.close') : $t('chat.open')"
      @click="toggle"
    >
      <AppIcon :name="open ? 'close' : 'chat'" :size="22" />
    </button>

    <button
      class="fixed bottom-5 left-3 z-40 hidden w-[240px] items-center gap-2.5 rounded-[14px] border border-[var(--line)] bg-[var(--surface)] px-3.5 py-3 text-[13px] shadow-[0_2px_10px_rgba(23,23,26,.05)] transition hover:shadow-[0_4px_16px_rgba(23,23,26,.09)] md:flex"
      :aria-label="open ? $t('chat.close') : $t('chat.open')"
      @click="toggle"
    >
      <AppIcon :name="open ? 'close' : 'sparkles'" :size="16" class="text-[var(--ai)]" />
      <span class="font-medium">{{ $t('chat.ask') }}</span>
      <span class="ml-auto text-[11px] text-[var(--faint)]">⌘/</span>
    </button>

    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-3 opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="translate-y-3 opacity-0"
    >
      <section
        v-if="open"
        class="fixed inset-x-4 bottom-36 z-40 flex max-h-[70vh] flex-col overflow-hidden rounded-[24px] border border-[var(--line)] bg-[var(--surface)] shadow-[0_20px_60px_rgba(23,23,26,.2)] md:inset-x-auto md:bottom-[86px] md:left-3 md:w-[364px]"
        role="dialog"
        :aria-label="$t('chat.title')"
      >
        <header class="flex items-center justify-between border-b border-[var(--line)] bg-[var(--paper)] px-5 py-4">
          <div>
            <p class="text-[14px] font-semibold tracking-[-0.02em]">{{ $t('chat.title') }}</p>
            <p class="text-[11px] text-[var(--faint)]">{{ $t('chat.subtitle') }}</p>
          </div>
          <button
            v-if="messages.length"
            class="text-[11px] text-[var(--faint)] underline underline-offset-2 hover:text-[var(--ink)]"
            @click="clearConversation"
          >{{ $t('chat.clear') }}</button>
        </header>

        <div ref="scrollEl" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
          <div class="max-w-[85%] rounded-2xl rounded-tl-md bg-[var(--paper)] px-4 py-2.5 text-[13px] leading-6 text-[#3a3a3e]">
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
                ? 'rounded-tr-md bg-[var(--ink)] text-white'
                : 'rounded-tl-md bg-[var(--paper)] text-[#3a3a3e]'"
            >{{ message.content }}</div>
          </div>
          <div v-if="sending" class="flex justify-start">
            <div class="rounded-2xl rounded-tl-md bg-[var(--paper)] px-4 py-2.5 text-[13px] text-[var(--faint)]">{{ $t('chat.thinking') }}</div>
          </div>
          <p v-if="error" role="alert" class="rounded-[12px] border border-[#e6cfc7] bg-[#fbf1ee] px-3 py-2 text-[12px] text-[#8b402a]">{{ error }}</p>
        </div>

        <div class="border-t border-[var(--line)] bg-[var(--surface)] p-3">
          <div class="flex items-end gap-2 rounded-[16px] border border-[var(--line)] bg-[var(--paper)] px-3 py-2">
            <textarea
              ref="inputEl"
              v-model="draft"
              rows="1"
              :placeholder="$t('chat.placeholder')"
              class="max-h-28 flex-1 resize-none bg-transparent text-[13px] leading-6 text-[var(--ink)] outline-none placeholder:text-[var(--faint)]"
              @keydown="onKeydown"
            />
            <button
              class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--ink)] text-white transition disabled:opacity-40"
              :disabled="!draft.trim() || sending"
              :aria-label="$t('chat.send')"
              @click="send"
            >
              <AppIcon name="send" :size="16" />
            </button>
          </div>
        </div>
      </section>
    </Transition>
  </div>
</template>
