<script setup lang="ts">
const { consent, preferencesOpen, isConfigured, initialize, accept, deny } = useGoogleAnalytics()

const visible = computed(() => isConfigured.value && (consent.value === null || preferencesOpen.value))

onMounted(initialize)
</script>

<template>
  <aside
    v-if="visible"
    class="fixed inset-x-4 bottom-4 z-[80] mx-auto max-w-[760px] rounded-[20px] border border-[var(--line)] bg-[var(--surface)] p-5 shadow-[0_18px_60px_rgba(23,23,26,.18)] md:flex md:items-center md:gap-6 md:p-6"
    role="region"
    aria-labelledby="analytics-consent-title"
  >
    <div class="min-w-0 flex-1">
      <p id="analytics-consent-title" class="font-serif text-[22px] tracking-[-.02em]">{{ $t('analyticsConsent.title') }}</p>
      <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $t('analyticsConsent.body') }}</p>
      <NuxtLink to="/privacy#cookies" class="mt-2 inline-flex text-xs font-medium underline underline-offset-4 transition hover:text-[var(--accent)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]">
        {{ $t('analyticsConsent.privacy') }}
      </NuxtLink>
    </div>
    <div class="mt-5 flex shrink-0 gap-2 md:mt-0">
      <button
        type="button"
        class="inline-flex h-10 flex-1 items-center justify-center rounded-full border border-[var(--line)] bg-[var(--surface)] px-4 text-[13px] font-medium transition hover:bg-[var(--paper)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)] md:flex-none"
        @click="deny"
      >
        {{ $t('analyticsConsent.deny') }}
      </button>
      <button
        type="button"
        class="inline-flex h-10 flex-1 items-center justify-center rounded-full b-btn-red px-4 text-[13px] font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)] md:flex-none"
        @click="accept"
      >
        {{ $t('analyticsConsent.accept') }}
      </button>
    </div>
  </aside>
</template>
