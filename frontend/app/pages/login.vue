<script setup lang="ts">
definePageMeta({ layout: false })

const { login, register } = useAuth()
const { t } = useI18n()
const mode = ref<'login' | 'register'>('login')
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const error = ref<string | null>(null)
const loading = ref(false)

const heading = computed(() => mode.value === 'login' ? t('login.headingLogin') : t('login.headingRegister'))

async function submit() {
  loading.value = true
  error.value = null
  try {
    if (mode.value === 'login') {
      await login({ email: form.email, password: form.password })
      // The onboarding gate routes users who haven't connected Instagram yet.
      await navigateTo('/feed')
    } else {
      await register({ ...form })
      // A brand-new account always starts with Instagram onboarding.
      await navigateTo('/onboarding')
    }
  } catch (exception: any) {
    const errors = exception?.data?.errors as Record<string, string[]> | undefined
    error.value = errors ? Object.values(errors).flat()[0]! : (exception?.data?.message || t('login.genericError'))
  } finally {
    loading.value = false
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

    <section class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-[460px] flex-col justify-center px-6 pb-16">
      <div class="rounded-[24px] border border-[var(--line)] bg-[var(--surface)] p-7 shadow-[0_2px_14px_rgba(23,23,26,.05)] md:p-9">
        <h1 class="font-serif text-[40px] leading-[1.06] tracking-[-0.035em]">{{ heading }}</h1>
        <p class="mt-4 text-[15px] leading-7 text-[var(--muted)]">
          {{ mode === 'login' ? $t('login.subLogin') : $t('login.subRegister') }}
        </p>

        <form class="mt-8 space-y-4" @submit.prevent="submit">
          <label v-if="mode === 'register'" class="block">
            <span class="text-[11px] font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('login.name') }}</span>
            <input v-model="form.name" type="text" autocomplete="name" required class="auth-input">
          </label>

          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('login.email') }}</span>
            <input v-model="form.email" type="email" autocomplete="email" required class="auth-input">
          </label>

          <label class="block">
            <span class="text-[11px] font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('login.password') }}</span>
            <input v-model="form.password" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" required class="auth-input">
          </label>

          <label v-if="mode === 'register'" class="block">
            <span class="text-[11px] font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('login.confirmPassword') }}</span>
            <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="auth-input">
          </label>

          <p v-if="error" role="alert" class="rounded-[16px] border border-[#e6cfc7] bg-[#fbf1ee] px-4 py-3 text-sm leading-6 text-[#8a3d2a]">{{ error }}</p>

          <button
            type="submit"
            class="inline-flex h-12 w-full items-center justify-center rounded-full bg-[var(--ink)] px-7 text-sm font-medium text-white transition hover:bg-black disabled:cursor-wait disabled:opacity-60"
            :disabled="loading"
          >
            {{ loading ? $t('login.loading') : (mode === 'login' ? $t('login.submitLogin') : $t('login.submitRegister')) }}
          </button>
        </form>
      </div>

      <button class="mt-6 self-center text-sm text-[var(--muted)] underline underline-offset-4 transition hover:text-[var(--ink)]" @click="mode = mode === 'login' ? 'register' : 'login'">
        {{ mode === 'login' ? $t('login.toggleToRegister') : $t('login.toggleToLogin') }}
      </button>
    </section>
  </main>
</template>

<style scoped>
.auth-input { @apply mt-2 w-full rounded-[14px] border border-[var(--line)] bg-[var(--paper)] px-4 py-3 text-[15px] outline-none transition focus:border-[var(--ink)] focus:bg-[var(--surface)]; }
</style>
