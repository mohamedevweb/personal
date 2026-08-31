<script setup lang="ts">
const { status, connecting, error, connect, loadStatus } = useInstagram()
const { apiFetch } = usePersonalApi()
const { user, loadUser, updateAccount, updatePassword, resendVerification, logout } = useAuth()
const { address: supportAddress, mailto: supportMailto } = useSupportEmail()
const { t } = useI18n()
const toast = useToast()
const instagramAvatarFailed = ref(false)

async function disconnect() {
  try {
    await apiFetch('/api/integrations/instagram', { method: 'DELETE' })
    await loadStatus()
    await loadUser()
    instagramAvatarFailed.value = false
    if (error.value) return
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('settings.disconnectError')))
  }
}

const account = reactive({ email: '' })
const accountSaving = ref(false)

const password = reactive({ current_password: '', password: '', password_confirmation: '' })
const passwordSaving = ref(false)

const resending = ref(false)
const resent = ref(false)

const verified = computed(() => !!user.value?.email_verified_at)

function syncAccountForm() {
  account.email = user.value?.email ?? ''
}

async function saveAccount() {
  accountSaving.value = true
  try {
    const payload: { email?: string } = {}
    if (account.email !== user.value?.email) payload.email = account.email
    if (!Object.keys(payload).length) { accountSaving.value = false; return }
    await updateAccount(payload)
    toast.success(t('settings.account.emailChanged'))
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('settings.account.error')))
  } finally {
    accountSaving.value = false
  }
}

async function savePassword() {
  passwordSaving.value = true
  try {
    await updatePassword({ ...password })
    toast.success(t('settings.password.saved'))
    password.current_password = ''
    password.password = ''
    password.password_confirmation = ''
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('settings.password.error')))
  } finally {
    passwordSaving.value = false
  }
}

const signingOut = ref(false)

async function signOut() {
  signingOut.value = true
  try {
    await logout()
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('settings.session.error')))
    signingOut.value = false
  }
}

async function resend() {
  resending.value = true
  try {
    await resendVerification()
    resent.value = true
  } catch (exception: unknown) {
    toast.error(apiErrorMessage(exception, t('verifyEmail.error')))
  } finally {
    resending.value = false
  }
}

onMounted(async () => {
  await loadStatus()
  instagramAvatarFailed.value = false
  syncAccountForm()
})

watch(user, syncAccountForm)
watch(error, (message) => {
  if (message) toast.error(message)
})
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
          <span class="text-xs font-medium uppercase tracking-[.14em] text-[var(--faint)]">{{ $t('settings.account.email') }}</span>
          <input v-model="account.email" type="email" autocomplete="email" required class="settings-input">
        </label>

        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:cursor-wait disabled:opacity-60" :disabled="accountSaving">
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

        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:cursor-wait disabled:opacity-60" :disabled="passwordSaving">
          {{ passwordSaving ? $t('settings.saving') : $t('settings.password.save') }}
        </button>
      </form>
    </section>

    <!-- Instagram -->
    <section class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('settings.instagram') }}</h2>
      <p class="mt-1 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.instagramCopy') }}</p>
      <div v-if="status.connected" class="mt-7 flex items-center gap-3 rounded-[14px] border border-[var(--line-soft)] bg-[var(--paper)] p-4">
        <img
          v-if="status.account?.profile_picture_url && !instagramAvatarFailed"
          :src="status.account.profile_picture_url"
          :alt="status.account.display_name || `@${status.account.username}`"
          class="h-10 w-10 rounded-full object-cover"
          @error="instagramAvatarFailed = true"
        >
        <div v-else class="grid h-10 w-10 place-items-center rounded-full bg-[var(--surface)] text-xs font-medium">IG</div>
        <div>
          <p class="text-sm font-medium">{{ $t('settings.instagramConnected') }}</p>
          <p class="text-xs text-[var(--faint)]">{{ $t('settings.postsImported', { count: status.account?.imported_media_count, status: status.account?.sync_status }) }}</p>
        </div>
        <button class="ml-auto text-xs text-[var(--faint)] transition hover:text-[var(--danger)]" @click="disconnect">{{ $t('settings.disconnect') }}</button>
      </div>
      <button v-else class="mt-7 inline-flex h-11 items-center justify-center rounded-full b-btn-red px-5 text-[14px] font-medium transition disabled:opacity-60" :disabled="connecting" @click="connect">{{ $t('settings.continueWithInstagram') }}</button>
    </section>

    <!-- Support -->
    <section class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('support.title') }}</h2>
      <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $t('support.copy') }}</p>
      <a :href="supportMailto" class="mt-5 inline-flex h-11 items-center justify-center gap-2 rounded-full b-btn-red px-5 text-[14px] font-medium transition">
        <AppIcon name="mail" :size="17" />
        {{ $t('support.cta') }}
      </a>
      <p class="mt-3 text-xs text-[var(--faint)]">{{ $t('support.hint', { address: supportAddress }) }}</p>
    </section>

    <!-- Privacy -->
    <section class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('settings.privacy') }}</h2>
      <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.privacyCopy') }}</p>
      <NuxtLink to="/privacy" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-[var(--ink)] underline underline-offset-4 transition hover:text-[var(--accent)]">{{ $t('settings.privacyLink') }}</NuxtLink>
    </section>

    <!-- Session -->
    <section class="mt-5 rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <h2 class="font-serif text-[26px] tracking-[-.02em]">{{ $t('settings.session.title') }}</h2>
      <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.session.copy') }}</p>
      <button
        type="button"
        class="mt-5 inline-flex h-11 items-center justify-center gap-2 rounded-full border border-[var(--line)] bg-[var(--paper)] px-5 text-[14px] font-medium transition hover:border-[var(--danger)] hover:text-[var(--danger)] disabled:cursor-wait disabled:opacity-60"
        :disabled="signingOut"
        @click="signOut"
      >
        <AppIcon name="logout" :size="17" />
        {{ signingOut ? $t('settings.session.pending') : $t('settings.session.action') }}
      </button>
    </section>
  </main>
</template>

<style scoped>
.settings-input { @apply mt-2 w-full rounded-[14px] border border-[var(--line)] bg-[var(--paper)] px-4 py-3 text-[15px] outline-none transition focus:border-[var(--ink)]; }

/* iOS zooms the page in whenever a focused field is set under 16px, and never
   zooms back out, so on a touch pointer the field is lifted to the threshold.
   The size the design asks for is kept everywhere a pointer is doing the
   typing. */
@media (pointer: coarse) { .settings-input { font-size: 16px; } }
</style>
