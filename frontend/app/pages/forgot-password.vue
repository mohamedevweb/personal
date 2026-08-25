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
    <div class="mx-auto grid min-h-[calc(100vh-1.5rem)] max-w-[1500px] gap-3 md:min-h-[calc(100vh-2rem)] md:gap-4 lg:grid-cols-2">
      <section class="b-panel relative flex flex-col justify-center rounded-[24px] px-6 py-14 md:px-10">
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

      <section class="b-night relative hidden overflow-hidden rounded-[24px] px-10 py-16 text-[var(--b-ivory)] lg:flex lg:flex-col lg:items-center lg:justify-center">
        <PersonalMark
          :size="440"
          class="pointer-events-none absolute left-1/2 top-[38%] -translate-x-1/2 -translate-y-1/2 text-white/[.028]"
        />

        <div class="relative w-full max-w-[440px] text-center">
          <h2 class="font-display text-[38px] leading-[1.06] tracking-[-.025em] xl:text-[44px]">
            <span class="block">{{ $t('passwordRecovery.aside.titleLineOne') }}</span>
            <span class="block">{{ $t('passwordRecovery.aside.titleLineTwo') }}</span>
          </h2>

          <p class="mt-6 text-[12px] font-semibold uppercase tracking-[.18em] text-[#a8a196]">
            {{ $t('passwordRecovery.aside.proof') }}
          </p>

          <figure class="panel-night mt-12 rounded-[18px] px-6 py-6 text-left backdrop-blur-sm">
            <figcaption class="sr-only">{{ $t('passwordRecovery.aside.card.label') }}</figcaption>

            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 place-items-center rounded-[12px] bg-[rgba(224,79,54,.16)] text-[var(--b-red-lit)]" aria-hidden="true">
                  <PersonalMark :size="18" />
                </span>
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-[#a8a196]">
                    {{ $t('passwordRecovery.aside.card.eyebrow') }}
                  </p>
                  <p class="mt-1 text-[11.5px] text-[#7d776c]">{{ $t('passwordRecovery.aside.card.sender') }}</p>
                </div>
              </div>
              <span class="grid h-8 w-8 place-items-center rounded-full border border-white/[.08] text-[var(--b-red-lit)]" aria-hidden="true">
                <AppIcon name="shield" :size="16" />
              </span>
            </div>

            <div class="mt-7 rounded-[14px] border border-white/[.07] bg-black/20 px-5 py-5">
              <div class="flex items-center gap-3 text-[var(--b-red-lit)]" aria-hidden="true">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-[rgba(224,79,54,.12)]">
                  <AppIcon name="mail" :size="18" />
                </span>
                <span class="h-px flex-1 bg-gradient-to-r from-[var(--b-red-lit)]/45 to-transparent" />
              </div>

              <p class="mt-5 font-display text-[28px] leading-[1.08] tracking-[-.02em] text-white">
                {{ $t('passwordRecovery.aside.card.title') }}
              </p>
              <p class="mt-3 max-w-[20rem] text-[13px] leading-[1.55] text-[#a8a196]">
                {{ $t('passwordRecovery.aside.card.copy') }}
              </p>

              <div class="mt-6 inline-flex h-10 items-center rounded-full bg-[var(--b-red-500)] px-5 text-[12.5px] font-medium text-white" aria-hidden="true">
                {{ $t('passwordRecovery.aside.card.action') }}
              </div>
            </div>

            <div class="mt-4 flex items-center justify-between text-[10.5px] font-medium uppercase tracking-[.13em] text-[#7d776c]">
              <span>{{ $t('passwordRecovery.aside.card.singleUse') }}</span>
              <span>{{ $t('passwordRecovery.aside.card.duration') }}</span>
            </div>
          </figure>
        </div>
      </section>
    </div>
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
