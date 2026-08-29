<script setup lang="ts">
definePageMeta({ layout: false })

const { user, loadUser, resendVerification, logout } = useAuth()
const { t } = useI18n()
const route = useRoute()
const toast = useToast()

const status = computed(() => route.query.status as string | undefined)
const verified = computed(() => status.value === 'verified' || !!user.value?.email_verified_at)

const sending = ref(false)

onMounted(async () => {
  // A creator who lands here already verified has nothing to do — send them in.
  if (!user.value) await loadUser().catch(() => {})
  if (verified.value) return
})

async function resend() {
  sending.value = true
  try {
    await resendVerification()
    toast.success(t('verifyEmail.sent'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('verifyEmail.error')))
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-[var(--paper)] text-[var(--ink)]">
    <header class="flex h-20 items-center justify-between px-6 md:px-10">
      <NuxtLink to="/" class="b-focus w-fit">
        <PersonalLogo :size="22" />
      </NuxtLink>
      <div class="flex items-center gap-4">
        <span class="hidden text-xs tracking-wide text-[var(--faint)] sm:inline">{{ $t('brand.privateIntelligence') }}</span>
        <LanguageSwitcher />
      </div>
    </header>

    <section class="mx-auto flex min-h-[calc(100dvh-5rem)] max-w-md flex-col justify-center px-6 py-14">
      <template v-if="verified">
        <h1 class="font-serif text-[44px] leading-[1.04] tracking-[-0.035em]">{{ $t('verifyEmail.verifiedTitle') }}</h1>
        <p class="mt-5 text-[16px] leading-7 text-[var(--muted)]">{{ $t('verifyEmail.verifiedCopy') }}</p>
        <NuxtLink to="/feed" class="mt-9 inline-flex h-[52px] items-center justify-center rounded-full b-btn-red px-7 text-[15px] font-medium transition">
          {{ $t('verifyEmail.continue') }}
        </NuxtLink>
      </template>

      <template v-else>
        <h1 class="font-serif text-[44px] leading-[1.04] tracking-[-0.035em]">{{ $t('verifyEmail.pendingTitle') }}</h1>
        <p class="mt-5 text-[16px] leading-7 text-[var(--muted)]">
          {{ status === 'invalid' ? $t('verifyEmail.invalidCopy') : $t('verifyEmail.pendingCopy') }}
          <span v-if="user?.email" class="font-medium text-[var(--ink)]"> {{ user.email }}</span>
        </p>

        <button
          type="button"
          class="mt-9 inline-flex h-[52px] items-center justify-center rounded-full b-btn-red px-7 text-[15px] font-medium transition disabled:cursor-wait disabled:opacity-60"
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
