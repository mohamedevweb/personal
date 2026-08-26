<script setup lang="ts">
definePageMeta({ layout: false })

const { login, register } = useAuth()
const { t } = useI18n()
const toast = useToast()
const route = useRoute()

const mode = ref<'login' | 'register'>(route.query.mode === 'register' ? 'register' : 'login')
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const revealPassword = ref(false)
const loading = ref(false)

const isLogin = computed(() => mode.value === 'login')
const heading = computed(() => isLogin.value ? t('login.headingLogin') : t('login.headingRegister'))

// The month curve on the aside card. Drawn from a fixed set of points rather
// than a hand-written path so the fill and the stroke can never drift apart.
const CURVE = 'M0 74 C 46 74, 62 70, 84 56 S 128 12, 168 10 L 300 10'

async function submit() {
  loading.value = true
  try {
    if (isLogin.value) {
      await login({ email: form.email, password: form.password })
      // The onboarding gate routes users who haven't connected Instagram yet.
      await navigateTo('/feed')
    } else {
      await register({ ...form })
      // A brand-new account always starts with Instagram onboarding.
      await navigateTo('/onboarding')
    }
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('login.genericError')))
  } finally {
    loading.value = false
  }
}

function toggleMode() {
  mode.value = isLogin.value ? 'register' : 'login'
}
</script>

<template>
  <main class="min-h-screen bg-[var(--b-ivory)] p-3 text-[var(--b-black)] md:p-4">
    <div class="mx-auto grid min-h-[calc(100vh-1.5rem)] max-w-[1500px] gap-3 md:min-h-[calc(100vh-2rem)] md:gap-4 lg:grid-cols-2">
      <!-- The form. It carries the whole page on small screens, so it keeps its
           own quiet margins rather than borrowing the aside's composition. -->
      <section class="b-panel relative flex flex-col justify-center rounded-[24px] px-6 py-14 md:px-10">
        <LanguageSwitcher class="absolute right-5 top-5" />

        <div class="mx-auto w-full max-w-[360px]">
          <NuxtLink to="/" class="b-focus mx-auto block w-fit" :aria-label="$t('login.home')">
            <PersonalMark :size="30" tone="signature" />
          </NuxtLink>

          <h1 class="mt-7 text-center font-display text-[44px] leading-[1.05] tracking-[-.03em]">
            {{ heading }}
          </h1>

          <p class="mx-auto mt-4 max-w-[20rem] text-center text-[14.5px] leading-[1.55] text-[var(--b-stone)]">
            {{ isLogin ? $t('login.subLogin') : $t('login.subRegister') }}
          </p>

          <form class="mt-9 space-y-3" @submit.prevent="submit">
            <label v-if="!isLogin" class="block">
              <span class="sr-only">{{ $t('login.name') }}</span>
              <input v-model="form.name" type="text" autocomplete="name" required class="auth-input" :placeholder="$t('login.namePlaceholder')">
            </label>

            <label class="block">
              <span class="sr-only">{{ $t('login.email') }}</span>
              <input v-model="form.email" type="email" autocomplete="email" required class="auth-input" :placeholder="$t('login.emailPlaceholder')">
            </label>

            <label class="relative block">
              <span class="sr-only">{{ $t('login.password') }}</span>
              <input
                v-model="form.password"
                :type="revealPassword ? 'text' : 'password'"
                :autocomplete="isLogin ? 'current-password' : 'new-password'"
                required
                class="auth-input pr-12"
                :placeholder="$t('login.passwordPlaceholder')"
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

            <div v-if="isLogin" class="text-right">
              <NuxtLink to="/forgot-password" class="b-focus text-[13px] text-[var(--b-stone)] underline underline-offset-4 transition-colors hover:text-[var(--b-black)]">
                {{ $t('login.forgotPassword') }}
              </NuxtLink>
            </div>

            <label v-if="!isLogin" class="block">
              <span class="sr-only">{{ $t('login.confirmPassword') }}</span>
              <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="auth-input" :placeholder="$t('login.confirmPasswordPlaceholder')">
            </label>

            <button
              type="submit"
              class="b-focus b-btn-red inline-flex h-[52px] w-full items-center justify-center rounded-full text-[15px] font-medium disabled:cursor-wait disabled:opacity-60"
              :disabled="loading"
            >
              {{ loading ? $t('login.loading') : (isLogin ? $t('login.submitLogin') : $t('login.submitRegister')) }}
            </button>
          </form>

          <p class="mt-7 text-center text-[13.5px] text-[var(--b-stone)]">
            {{ isLogin ? $t('login.noAccount') : $t('login.haveAccount') }}
            <button type="button" class="b-focus ml-1 text-[var(--b-black)] underline underline-offset-4 transition-colors hover:text-[var(--b-signature)]" @click="toggleMode">
              {{ isLogin ? $t('login.goToRegister') : $t('login.goToLogin') }}
            </button>
          </p>

          <p class="mt-5 text-center text-[12.5px] leading-[1.6] text-[#8b8375]">
            {{ $t('login.legal') }}
            <NuxtLink to="/terms" class="b-focus underline underline-offset-4 transition-colors hover:text-[var(--b-black)]">{{ $t('login.legalTermsLink') }}</NuxtLink>
            {{ $t('login.legalAnd') }}
            <NuxtLink to="/privacy" class="b-focus underline underline-offset-4 transition-colors hover:text-[var(--b-black)]">{{ $t('login.legalLink') }}</NuxtLink>
          </p>
        </div>
      </section>

      <!-- The aside: the one hero moment on the page. Warm light falling into
           the near-black card, with the product already working inside it. -->
      <section class="b-night relative hidden overflow-hidden rounded-[24px] px-10 py-16 text-[var(--b-ivory)] lg:flex lg:flex-col lg:items-center lg:justify-center">
        <PersonalMark
          :size="440"
          class="pointer-events-none absolute left-1/2 top-[38%] -translate-x-1/2 -translate-y-1/2 text-white/[.028]"
        />

        <div class="relative w-full max-w-[440px] text-center">
          <h2 class="font-display text-[38px] leading-[1.06] tracking-[-.025em] xl:text-[44px]">
            <span class="block">{{ $t('login.aside.titleLineOne') }}</span>
            <span class="block">{{ $t('login.aside.titleLineTwo') }}</span>
          </h2>

          <figure class="panel-night mt-12 rounded-[18px] px-5 pb-4 pt-5 text-left backdrop-blur-sm">
            <figcaption class="sr-only">{{ $t('login.aside.card.label') }}</figcaption>

            <div class="flex items-start justify-between">
              <p class="text-[11px] font-semibold uppercase tracking-[.16em] text-[#a8a196]">
                {{ $t('login.aside.card.eyebrow') }}
              </p>
              <span class="grid h-7 w-7 place-items-center rounded-full bg-[rgba(224,79,54,.16)] text-[var(--b-red-lit)]" aria-hidden="true">
                <AppIcon name="sparkles" :size="14" />
              </span>
            </div>

            <p class="mt-4 font-display text-[38px] leading-none tracking-[-.02em] text-[var(--b-red-lit)]">{{ $t('login.aside.card.metric') }}</p>
            <p class="mt-2 flex items-center gap-2 text-[12.5px] text-[#a8a196]">
              <span class="b-live" aria-hidden="true" />
              {{ $t('login.aside.card.period') }}
            </p>

            <svg viewBox="0 0 300 84" class="mt-5 w-full" preserveAspectRatio="none" aria-hidden="true">
              <defs>
                <linearGradient id="login-curve" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="var(--b-red-lit)" stop-opacity=".34" />
                  <stop offset="100%" stop-color="var(--b-red-lit)" stop-opacity="0" />
                </linearGradient>
              </defs>
              <path :d="`${CURVE} L300 84 L0 84 Z`" fill="url(#login-curve)" />
              <path :d="CURVE" fill="none" stroke="var(--b-red-lit)" stroke-width="1.6" vector-effect="non-scaling-stroke" />
            </svg>

            <div class="mt-3 flex justify-between text-[11px] tracking-[.14em] text-[#7d776c]" aria-hidden="true">
              <span v-for="day in ['01', '07', '14', '21', '28']" :key="day">{{ day }}</span>
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
