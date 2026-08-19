<script setup lang="ts">
definePageMeta({ layout: false })

const { user, loadUser, resendVerification, logout } = useAuth()
const { t } = useI18n()
const route = useRoute()

const status = computed(() => route.query.status as string | undefined)
const verified = computed(() => status.value === 'verified' || !!user.value?.email_verified_at)

const sending = ref(false)
const sent = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  // A creator who lands here already verified has nothing to do — send them in.
  if (!user.value) await loadUser().catch(() => {})
  if (verified.value) return
})

async function resend() {
  sending.value = true
  error.value = null
  try {
    await resendVerification()
    sent.value = true
  } catch (exception: any) {
    error.value = exception?.data?.message || t('verifyEmail.error')
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-[var(--paper)] text-[var(--ink)]">
    <header class="flex h-20 items-center justify-between px-6 md:px-10">
      <div class="flex items-center gap-2.5">
        <span class="grid h-8 w-8 place-items-center rounded-[10px] bg-[var(--ink)] font-serif text-[15px] leading-none text-white">P</span>
        <span class="text-[16px] font-semibold tracking-[-0.03em]">{{ $t('brand.name') }}</span>
      </div>
      <div class="flex items-center gap-4">
        <span class="hidden text-xs tracking-wide text-[var(--faint)] sm:inline">{{ $t('brand.privateIntelligence') }}</span>
        <LanguageSwitcher />
      </div>
    </header>

    <section class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-md flex-col justify-center px-6 py-14">
      <template v-if="verified">
        <div class="grid h-14 w-14 place-items-center rounded-[16px] bg-[var(--ink)] text-white">
          <AppIcon name="sparkles" :size="24" />
        </div>
        <h1 class="mt-7 font-serif text-[44px] leading-[1.04] tracking-[-0.035em]">{{ $t('verifyEmail.verifiedTitle') }}</h1>
        <p class="mt-5 text-[16px] leading-7 text-[var(--muted)]">{{ $t('verifyEmail.verifiedCopy') }}</p>
        <NuxtLink to="/feed" class="mt-9 inline-flex h-13 items-center justify-center rounded-full bg-[var(--ink)] px-7 py-4 text-sm font-medium text-white transition hover:bg-black">
          {{ $t('verifyEmail.continue') }}
        </NuxtLink>
      </template>

      <template v-else>
        <h1 class="font-serif text-[44px] leading-[1.04] tracking-[-0.035em]">{{ $t('verifyEmail.pendingTitle') }}</h1>
        <p class="mt-5 text-[16px] leading-7 text-[var(--muted)]">
          {{ status === 'invalid' ? $t('verifyEmail.invalidCopy') : $t('verifyEmail.pendingCopy') }}
          <span v-if="user?.email" class="font-medium text-[var(--ink)]"> {{ user.email }}</span>
        </p>

        <p v-if="sent" role="status" class="mt-7 rounded-[16px] border border-[#cddcd1] bg-[#f1f6f2] px-4 py-3 text-sm leading-6 text-[var(--positive)]">{{ $t('verifyEmail.sent') }}</p>
        <p v-if="error" role="alert" class="mt-7 rounded-[16px] border border-[#e6cfc7] bg-[#fbf1ee] px-4 py-3 text-sm leading-6 text-[#8a3d2a]">{{ error }}</p>

        <button
          type="button"
          class="mt-9 inline-flex h-13 items-center justify-center rounded-full bg-[var(--ink)] px-7 py-4 text-sm font-medium text-white transition hover:bg-black disabled:cursor-wait disabled:opacity-60"
          :disabled="sending"
          @click="resend"
        >
          {{ sending ? $t('verifyEmail.sending') : $t('verifyEmail.resend') }}
        </button>

        <button class="mt-6 text-sm text-[var(--muted)] underline underline-offset-4" @click="logout">
          {{ $t('verifyEmail.signOut') }}
        </button>
      </template>
    </section>
  </main>
</template>
