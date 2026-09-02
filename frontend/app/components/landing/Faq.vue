<script setup lang="ts">
const KEYS = ['sound', 'personalization', 'niche', 'different', 'post', 'cost'] as const
type FaqKey = (typeof KEYS)[number]

const activeKey = ref<FaqKey | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)

function openAnswer(key: FaqKey) {
  activeKey.value = key
  nextTick(() => closeButton.value?.focus())
}

function closeAnswer() {
  activeKey.value = null
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && activeKey.value) closeAnswer()
}

watch(activeKey, (isOpen) => {
  if (!import.meta.client) return
  document.body.style.overflow = isOpen ? 'hidden' : ''
})

onMounted(() => {
  if (import.meta.client) window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  if (import.meta.client) {
    window.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <section id="faq" class="scroll-mt-24 border-t border-[var(--b-line)] px-5 py-24 md:px-10 md:py-36">
    <div class="mx-auto grid max-w-[1200px] gap-12 md:grid-cols-[minmax(0,340px)_1fr] md:gap-20">
      <div data-reveal class="md:sticky md:top-32 md:self-start">
        <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.faq.eyebrow') }}</p>
        <h2 class="mt-6 font-display text-[34px] leading-[1.06] tracking-[-.025em] md:text-[44px]">
          {{ $t('landing.faq.title') }}
        </h2>
        <p class="mt-6 max-w-xs text-[15px] leading-[1.65] text-[var(--b-stone)]">
          {{ $t('landing.faq.lede') }}
        </p>
      </div>

      <div data-reveal class="border-t border-[var(--b-line)]">
        <div v-for="key in KEYS" :key="key" class="border-b border-[var(--b-line)]">
          <button
            type="button"
            class="b-focus flex w-full items-start justify-between gap-8 py-6 text-left text-[17px] leading-[1.45] tracking-[-.01em] transition-colors hover:text-[var(--b-stone)] md:text-[18.5px]"
            aria-haspopup="dialog"
            @click="openAnswer(key)"
          >
            {{ $t(`landing.faq.items.${key}.q`) }}
            <span
              class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-[var(--b-line)] text-[var(--b-stone)] transition-all duration-300"
              aria-hidden="true"
            >
              <AppIcon name="plus" :size="15" />
            </span>
          </button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-if="activeKey"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4 backdrop-blur-[3px] sm:p-8"
          @click.self="closeAnswer"
        >
          <div class="absolute inset-0" aria-hidden="true" @click="closeAnswer" />

          <section
            class="relative max-h-[calc(100dvh-2rem)] w-full max-w-[1560px] overflow-y-auto bg-[var(--b-ivory)] text-[var(--b-black)] shadow-[0_30px_90px_-30px_rgba(23,23,21,.5)]"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="`faq-title-${activeKey}`"
          >
            <button
              ref="closeButton"
              type="button"
              class="b-focus absolute right-4 top-4 grid h-14 w-14 place-items-center rounded-full bg-[var(--b-black)] text-[var(--b-ivory)] transition-transform hover:scale-105 sm:right-5 sm:top-5"
              :aria-label="$t('common.close')"
              @click="closeAnswer"
            >
              <AppIcon name="close" :size="22" />
            </button>

            <div class="px-8 pb-14 pt-12 sm:px-12 sm:pb-20 sm:pt-14 md:px-0 md:pb-24 md:pt-14">
              <h2
                :id="`faq-title-${activeKey}`"
                class="max-w-[1100px] pr-16 text-[34px] leading-[1.12] tracking-[-.025em] text-[var(--b-stone)] sm:text-[42px] md:pl-0 md:text-[38px]"
              >
                {{ $t(`landing.faq.items.${activeKey}.q`) }}
              </h2>
              <p class="mt-12 max-w-[1410px] border-l border-[var(--b-line)] pl-5 text-[21px] leading-[1.55] text-[var(--b-stone)] sm:pl-10 sm:text-[25px] md:mt-14 md:text-[27px]">
                {{ $t(`landing.faq.items.${activeKey}.a`) }}
              </p>
              <div class="mt-14 border-b border-[var(--b-line)]" aria-hidden="true" />
            </div>
          </section>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>
