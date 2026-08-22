<script setup lang="ts">
definePageMeta({ layout: false })

const { requestPasswordReset } = useAuth()
const { t } = useI18n()
const toast = useToast()

const email = ref('')
const sending = ref(false)
const sent = ref(false)

async function submit() {
  sending.value = true

  try {
    await requestPasswordReset(email.value)
    sent.value = true
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('passwordRecovery.requestError')))
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-[var(--b-ivory)] p-3 text-[var(--b-black)] md:p-4">
    <section class="b-panel relative mx-auto flex min-h-[calc(100vh-1.5rem)] max-w-[760px] flex-col justify-center rounded-[24px] px-6 py-14 md:min-h-[calc(100vh-2rem)] md:px-10">
      <LanguageSwitcher class="absolute right-5 top-5" />

      <div class="mx-auto w-full max-w-[380px]">
        <NuxtLink to="/" class="b-focus mx-auto block w-fit" :aria-label="$t('login.home')">
          <PersonalLogo :size="27" tone="signature" />
        </NuxtLink>

        <template v-if="sent">
          <div class="mx-auto mt-9 grid h-12 w-12 place-items-center rounded-[14px] bg-[var(--b-red-100)] text-[var(--b-signature)]" aria-hidden="true">
            <AppIcon name="mail" :size="21" />
          </div>
          <h1 class="mt-6 text-center font-display text-[42px] leading-[1.06] tracking-[-.03em]">
            {{ $t('passwordRecovery.sentTitle') }}
          </h1>
          <p role="status" class="mt-4 text-center text-[14.5px] leading-[1.6] text-[var(--b-stone)]">
            {{ $t('passwordRecovery.sentCopy') }}
          </p>
          <NuxtLink to="/login" class="b-focus b-btn-red mt-8 inline-flex h-[52px] w-full items-center justify-center rounded-full text-[15px] font-medium">
            {{ $t('passwordRecovery.backToLogin') }}
          </NuxtLink>
        </template>

        <template v-else>
          <h1 class="mt-9 text-center font-display text-[42px] leading-[1.06] tracking-[-.03em]">
            {{ $t('passwordRecovery.requestTitle') }}
          </h1>
          <p class="mt-4 text-center text-[14.5px] leading-[1.6] text-[var(--b-stone)]">
            {{ $t('passwordRecovery.requestCopy') }}
          </p>

          <form class="mt-9 space-y-4" @submit.prevent="submit">
            <label class="block">
              <span class="sr-only">{{ $t('login.email') }}</span>
              <input v-model="email" type="email" autocomplete="email" required class="auth-input" :placeholder="$t('login.emailPlaceholder')">
            </label>

            <button
              type="submit"
              class="b-focus b-btn-red inline-flex h-[52px] w-full items-center justify-center rounded-full text-[15px] font-medium disabled:cursor-wait disabled:opacity-60"
              :disabled="sending"
            >
              {{ sending ? $t('passwordRecovery.sending') : $t('passwordRecovery.sendLink') }}
            </button>
          </form>

          <p class="mt-7 text-center text-[13.5px] text-[var(--b-stone)]">
            <NuxtLink to="/login" class="b-focus underline underline-offset-4 transition-colors hover:text-[var(--b-black)]">
              {{ $t('passwordRecovery.backToLogin') }}
            </NuxtLink>
          </p>
        </template>
      </div>
    </section>
  </main>
</template>

<style scoped>
.auth-input {
  @apply h-[52px] w-full rounded-[14px] border border-[var(--b-line)] bg-[var(--b-surface)] px-4 text-[15px] outline-none transition-colors;
}

.auth-input::placeholder { color: var(--b-stone); }
.auth-input:focus {
  border-color: var(--b-red-300);
  box-shadow: 0 0 0 3px var(--b-glow-soft);
}
</style>
