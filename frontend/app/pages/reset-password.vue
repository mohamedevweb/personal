<script setup lang="ts">
definePageMeta({ layout: false })

const { resetPassword } = useAuth()
const { t } = useI18n()
const route = useRoute()
const toast = useToast()

function queryString(value: unknown): string {
  return typeof value === 'string' ? value : ''
}

const token = computed(() => queryString(route.query.token))
const email = computed(() => queryString(route.query.email))
const validLink = computed(() => !!token.value && !!email.value)
const form = reactive({ password: '', password_confirmation: '' })
const revealPassword = ref(false)
const resetting = ref(false)
const resetComplete = ref(false)

async function submit() {
  if (!validLink.value) return

  resetting.value = true

  try {
    await resetPassword({
      token: token.value,
      email: email.value,
      password: form.password,
      password_confirmation: form.password_confirmation
    })
    resetComplete.value = true
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('passwordRecovery.resetError')))
  } finally {
    resetting.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-[var(--b-ivory)] p-3 text-[var(--b-black)] md:p-4">
    <section class="b-panel relative mx-auto flex min-h-[calc(100dvh-1.5rem)] max-w-[760px] flex-col justify-center rounded-[24px] px-6 py-14 md:min-h-[calc(100dvh-2rem)] md:px-10">
      <LanguageSwitcher class="absolute right-5 top-5" />

      <div class="mx-auto w-full max-w-[380px]">
        <NuxtLink to="/" class="b-focus mx-auto block w-fit" :aria-label="$t('login.home')">
          <PersonalLogo :size="27" tone="signature" />
        </NuxtLink>

        <template v-if="resetComplete">
          <div class="mx-auto mt-9 grid h-12 w-12 place-items-center rounded-[14px] bg-[var(--positive-soft)] text-[var(--positive)]" aria-hidden="true">
            <AppIcon name="check" :size="21" />
          </div>
          <h1 class="mt-6 text-center font-display text-[42px] leading-[1.06] tracking-[-.03em]">
            {{ $t('passwordRecovery.completeTitle') }}
          </h1>
          <p role="status" class="mt-4 text-center text-[14.5px] leading-[1.6] text-[var(--b-stone)]">
            {{ $t('passwordRecovery.completeCopy') }}
          </p>
          <NuxtLink to="/login" class="b-focus b-btn-red mt-8 inline-flex h-[52px] w-full items-center justify-center rounded-full text-[15px] font-medium">
            {{ $t('passwordRecovery.signIn') }}
          </NuxtLink>
        </template>

        <template v-else-if="validLink">
          <h1 class="mt-9 text-center font-display text-[42px] leading-[1.06] tracking-[-.03em]">
            {{ $t('passwordRecovery.resetTitle') }}
          </h1>
          <p class="mt-4 text-center text-[14.5px] leading-[1.6] text-[var(--b-stone)]">
            {{ $t('passwordRecovery.resetCopy', { email }) }}
          </p>

          <form class="mt-9 space-y-3" @submit.prevent="submit">
            <label class="relative block">
              <span class="sr-only">{{ $t('passwordRecovery.newPassword') }}</span>
              <input
                v-model="form.password"
                :type="revealPassword ? 'text' : 'password'"
                autocomplete="new-password"
                required
                class="auth-input pr-12"
                :placeholder="$t('passwordRecovery.newPassword')"
              >
              <button
                type="button"
                class="b-focus absolute right-3.5 top-1/2 -translate-y-1/2 p-1 text-[var(--b-stone)] transition-colors hover:text-[var(--b-black)]"
                :aria-label="revealPassword ? $t('login.hidePassword') : $t('login.showPassword')"
                @click="revealPassword = !revealPassword"
              >
                <AppIcon :name="revealPassword ? 'eye-off' : 'eye'" :size="17" />
              </button>
            </label>

            <label class="block">
              <span class="sr-only">{{ $t('passwordRecovery.confirmPassword') }}</span>
              <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="auth-input" :placeholder="$t('passwordRecovery.confirmPassword')">
            </label>

            <button
              type="submit"
              class="b-focus b-btn-red inline-flex h-[52px] w-full items-center justify-center rounded-full text-[15px] font-medium disabled:cursor-wait disabled:opacity-60"
              :disabled="resetting"
            >
              {{ resetting ? $t('passwordRecovery.resetting') : $t('passwordRecovery.resetAction') }}
            </button>
          </form>
        </template>

        <template v-else>
          <h1 class="mt-9 text-center font-display text-[42px] leading-[1.06] tracking-[-.03em]">
            {{ $t('passwordRecovery.invalidTitle') }}
          </h1>
          <p role="alert" class="mt-4 text-center text-[14.5px] leading-[1.6] text-[var(--b-stone)]">
            {{ $t('passwordRecovery.invalidCopy') }}
          </p>
          <NuxtLink to="/forgot-password" class="b-focus b-btn-red mt-8 inline-flex h-[52px] w-full items-center justify-center rounded-full text-[15px] font-medium">
            {{ $t('passwordRecovery.requestAnother') }}
          </NuxtLink>
        </template>
      </div>
    </section>
  </main>
</template>

<style scoped>
.auth-input {
  @apply h-[52px] w-full rounded-[14px] border border-[var(--b-line)] bg-[var(--b-surface)] px-4 text-[15px] outline-none transition-colors;
}

/* iOS zooms the page in whenever a focused field is set under 16px, and never
   zooms back out, so on a touch pointer the field is lifted to the threshold.
   The size the design asks for is kept everywhere a pointer is doing the
   typing. */
@media (pointer: coarse) { .auth-input { font-size: 16px; } }

.auth-input::placeholder { color: var(--b-stone); }
.auth-input:focus {
  border-color: var(--b-red-300);
  box-shadow: 0 0 0 3px var(--b-glow-soft);
}
</style>
