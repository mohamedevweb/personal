<script setup lang="ts">
const { status, loading, error, connect, loadStatus } = useInstagram()
const { apiFetch } = usePersonalApi()
const { user, loadUser, updateAccount, updatePassword, resendVerification } = useAuth()
const { t } = useI18n()

async function disconnect() { await apiFetch('/api/integrations/instagram', { method: 'DELETE' }); await loadStatus() }

const account = reactive({ name: '', email: '' })
const accountSaving = ref(false)
const accountMessage = ref<string | null>(null)
const accountError = ref<string | null>(null)

const password = reactive({ current_password: '', password: '', password_confirmation: '' })
const passwordSaving = ref(false)
const passwordMessage = ref<string | null>(null)
const passwordError = ref<string | null>(null)

const resending = ref(false)
const resent = ref(false)

const verified = computed(() => !!user.value?.email_verified_at)

function syncAccountForm() {
  account.name = user.value?.name ?? ''
  account.email = user.value?.email ?? ''
}

function firstError(exception: any, fallback: string): string {
  const errors = exception?.data?.errors as Record<string, string[]> | undefined
  return errors ? Object.values(errors).flat()[0]! : (exception?.data?.message || fallback)
}

async function saveAccount() {
  accountSaving.value = true
  accountMessage.value = null
  accountError.value = null
  try {
    const payload: { name?: string, email?: string } = {}
    if (account.name !== user.value?.name) payload.name = account.name
    if (account.email !== user.value?.email) payload.email = account.email
    if (!Object.keys(payload).length) { accountSaving.value = false; return }
    const emailChanged = 'email' in payload
    await updateAccount(payload)
    accountMessage.value = emailChanged ? t('settings.account.emailChanged') : t('settings.account.saved')
  } catch (exception: any) {
    accountError.value = firstError(exception, t('settings.account.error'))
  } finally {
    accountSaving.value = false
  }
}

async function savePassword() {
  passwordSaving.value = true
  passwordMessage.value = null
  passwordError.value = null
  try {
    await updatePassword({ ...password })
    passwordMessage.value = t('settings.password.saved')
    password.current_password = ''
    password.password = ''
    password.password_confirmation = ''
  } catch (exception: any) {
    passwordError.value = firstError(exception, t('settings.password.error'))
  } finally {
    passwordSaving.value = false
  }
}

async function resend() {
  resending.value = true
  try {
    await resendVerification()
    resent.value = true
  } finally {
    resending.value = false
  }
}

onMounted(async () => {
  await loadStatus()
  if (!user.value) await loadUser().catch(() => {})
  syncAccountForm()
})

watch(user, syncAccountForm)
</script>

<template>
  <main class="page-shell pb-16 pt-2">
    <!-- Account -->
    <section class="rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('settings.account.title') }}</h2>
          <p class="mt-1 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.account.copy') }}</p>
        </div>
        <span
          class="shrink-0 rounded-full px-3 py-1 text-xs font-medium"
          :class="verified ? 'bg-[var(--positive-soft)] text-[var(--positive)]' : 'bg-[var(--warn-soft)] text-[var(--warn-ink)]'"
        >{{ verified ? $t('settings.account.verified') : $t('settings.account.unverified') }}</span>
      </div>

      <div v-if="!verified" class="mt-5 flex flex-wrap items-center gap-3 rounded-[14px] border border-[var(--warn-line)] bg-[var(--warn-soft)]/50 px-4 py-3 text-sm text-[var(--warn-ink)]">
        <span>{{ resent ? $t('settings.account.resent') : $t('settings.account.verifyPrompt') }}</span>
        <button v-if="!resent" class="ml-auto inline-flex h-9 items-center justify-center rounded-full bg-[var(--ink)] px-4 text-[12.5px] font-medium text-[var(--paper)] transition hover:bg-black disabled:opacity-60" :disabled="resending" @click="resend">
          {{ resending ? $t('settings.account.sending') : $t('settings.account.resend') }}
        </button>
      </div>

      <form class="mt-6 max-w-[520px] space-y-4" @submit.prevent="saveAccount">
        <label class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('settings.account.name') }}</span>
          <input v-model="account.name" type="text" autocomplete="name" required class="settings-input">
        </label>
        <label class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('settings.account.email') }}</span>
          <input v-model="account.email" type="email" autocomplete="email" required class="settings-input">
        </label>

        <p v-if="accountMessage" role="status" class="rounded-[14px] border border-[var(--positive-line)] bg-[var(--positive-soft)] px-4 py-3 text-sm leading-6 text-[var(--positive)]">{{ accountMessage }}</p>
        <p v-if="accountError" role="alert" class="rounded-[14px] border border-[var(--danger-line)] bg-[var(--danger-soft)] px-4 py-3 text-sm leading-6 text-[var(--danger)]">{{ accountError }}</p>

        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full bg-[var(--ink)] px-5 text-[14px] font-medium text-[var(--paper)] transition hover:bg-black disabled:cursor-wait disabled:opacity-60" :disabled="accountSaving">
          {{ accountSaving ? $t('settings.saving') : $t('settings.account.save') }}
        </button>
      </form>
    </section>

    <!-- Language -->
    <section class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <div class="flex flex-col items-start justify-between gap-5 sm:flex-row sm:items-center">
        <div>
          <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('settings.language.title') }}</h2>
          <p class="mt-1 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.language.copy') }}</p>
        </div>
        <LanguageSwitcher />
      </div>
    </section>

    <!-- Password -->
    <section class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('settings.password.title') }}</h2>
      <p class="mt-1 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.password.copy') }}</p>

      <form class="mt-6 max-w-[520px] space-y-4" @submit.prevent="savePassword">
        <label class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('settings.password.current') }}</span>
          <input v-model="password.current_password" type="password" autocomplete="current-password" required class="settings-input">
        </label>
        <label class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('settings.password.new') }}</span>
          <input v-model="password.password" type="password" autocomplete="new-password" required class="settings-input">
        </label>
        <label class="block">
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('settings.password.confirm') }}</span>
          <input v-model="password.password_confirmation" type="password" autocomplete="new-password" required class="settings-input">
        </label>

        <p v-if="passwordMessage" role="status" class="rounded-[14px] border border-[var(--positive-line)] bg-[var(--positive-soft)] px-4 py-3 text-sm leading-6 text-[var(--positive)]">{{ passwordMessage }}</p>
        <p v-if="passwordError" role="alert" class="rounded-[14px] border border-[var(--danger-line)] bg-[var(--danger-soft)] px-4 py-3 text-sm leading-6 text-[var(--danger)]">{{ passwordError }}</p>

        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full bg-[var(--ink)] px-5 text-[14px] font-medium text-[var(--paper)] transition hover:bg-black disabled:cursor-wait disabled:opacity-60" :disabled="passwordSaving">
          {{ passwordSaving ? $t('settings.saving') : $t('settings.password.save') }}
        </button>
      </form>
    </section>

    <!-- Instagram -->
    <section class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <div class="flex items-start gap-4">
        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-[12px] bg-[var(--ink)] text-[13px] font-medium text-[var(--paper)]">IG</div>
        <div class="flex-1">
          <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('settings.instagram') }}</h2>
          <p class="mt-1 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.instagramCopy') }}</p>
        </div>
      </div>
      <div v-if="status.connected" class="mt-7 flex items-center gap-3 rounded-[14px] border border-[var(--line-soft)] bg-[var(--paper)] p-4">
        <img v-if="status.account?.profile_picture_url" :src="status.account.profile_picture_url" alt="" class="h-10 w-10 rounded-full object-cover">
        <div>
          <p class="text-sm font-medium">@{{ status.account?.username }}</p>
          <p class="text-xs text-[var(--faint)]">{{ $t('settings.postsImported', { count: status.account?.imported_media_count, status: status.account?.sync_status }) }}</p>
        </div>
        <button class="ml-auto text-xs text-[var(--faint)] transition hover:text-[var(--danger)]" @click="disconnect">{{ $t('settings.disconnect') }}</button>
      </div>
      <button v-else class="mt-7 inline-flex h-11 items-center justify-center rounded-full bg-[var(--ink)] px-5 text-[14px] font-medium text-[var(--paper)] transition hover:bg-black disabled:opacity-60" :disabled="loading" @click="connect">{{ $t('settings.continueWithInstagram') }}</button>
      <p v-if="error" role="alert" class="mt-4 text-sm text-[var(--danger)]">{{ error }}</p>
    </section>

    <!-- Privacy -->
    <section class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <div class="flex items-start gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[12px] bg-[var(--paper)] text-[var(--muted)]"><AppIcon name="shield" :size="19" /></span>
        <div>
          <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('settings.privacy') }}</h2>
          <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.privacyCopy') }}</p>
          <NuxtLink to="/privacy" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-[var(--ink)] underline underline-offset-4 transition hover:text-[var(--accent)]">{{ $t('settings.privacyLink') }}</NuxtLink>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.settings-input { @apply mt-2 w-full rounded-[14px] border border-[var(--line)] bg-[var(--paper)] px-4 py-3 text-[15px] outline-none transition focus:border-[var(--ink)]; }
</style>
