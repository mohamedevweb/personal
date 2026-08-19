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
    } else {
      await register({ ...form })
    }
    await navigateTo('/feed')
  } catch (exception: any) {
    const errors = exception?.data?.errors as Record<string, string[]> | undefined
    error.value = errors ? Object.values(errors).flat()[0]! : (exception?.data?.message || t('login.genericError'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-[#f7f5f0] text-[#1d1d1b]">
    <header class="flex h-20 items-center justify-between border-b border-[#dedbd3]/80 px-6 md:px-10">
      <span class="text-[17px] font-semibold tracking-[-0.04em]">{{ $t('brand.name') }}</span>
      <div class="flex items-center gap-4">
        <span class="hidden text-xs tracking-wide text-[#8a877f] sm:inline">{{ $t('brand.privateIntelligence') }}</span>
        <LanguageSwitcher />
      </div>
    </header>

    <section class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-md flex-col justify-center px-6 py-14">
      <h1 class="font-serif text-5xl leading-[1.02] tracking-[-0.045em]">{{ heading }}</h1>
      <p class="mt-5 text-[16px] leading-7 text-[#6f6c65]">
        {{ mode === 'login' ? $t('login.subLogin') : $t('login.subRegister') }}
      </p>

      <form class="mt-9 space-y-4" @submit.prevent="submit">
        <label v-if="mode === 'register'" class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[#858178]">{{ $t('login.name') }}</span>
          <input v-model="form.name" type="text" autocomplete="name" required class="auth-input">
        </label>

        <label class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[#858178]">{{ $t('login.email') }}</span>
          <input v-model="form.email" type="email" autocomplete="email" required class="auth-input">
        </label>

        <label class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[#858178]">{{ $t('login.password') }}</span>
          <input v-model="form.password" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" required class="auth-input">
        </label>

        <label v-if="mode === 'register'" class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[#858178]">{{ $t('login.confirmPassword') }}</span>
          <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="auth-input">
        </label>

        <p v-if="error" role="alert" class="rounded-2xl border border-[#dcb7ac] bg-[#fbefeb] px-4 py-3 text-sm leading-6 text-[#8a3d2a]">{{ error }}</p>

        <button
          type="submit"
          class="inline-flex h-13 w-full items-center justify-center rounded-full bg-[#1d1d1b] px-7 py-4 text-sm font-medium text-white transition hover:bg-black disabled:cursor-wait disabled:opacity-60"
          :disabled="loading"
        >
          {{ loading ? $t('login.loading') : (mode === 'login' ? $t('login.submitLogin') : $t('login.submitRegister')) }}
        </button>
      </form>

      <button class="mt-6 text-sm text-[#77736c] underline underline-offset-4" @click="mode = mode === 'login' ? 'register' : 'login'">
        {{ mode === 'login' ? $t('login.toggleToRegister') : $t('login.toggleToLogin') }}
      </button>
    </section>
  </main>
</template>

<style scoped>
.auth-input { @apply mt-2 w-full rounded-2xl border border-[#d8d4ca] bg-white/70 px-4 py-3 text-[15px] outline-none transition focus:border-[#1d1d1b]; }
</style>
