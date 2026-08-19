<script setup lang="ts">
const { status, loading, error, connect, loadStatus } = useInstagram()
const { apiFetch } = usePersonalApi()
async function disconnect() { await apiFetch('/api/integrations/instagram', { method: 'DELETE' }); await loadStatus() }
onMounted(loadStatus)
</script>

<template>
  <main class="mx-auto max-w-[820px] px-5 pb-16 pt-2 md:px-8">
    <section class="rounded-[22px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <div class="flex items-start gap-4">
        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-[13px] bg-[var(--ink)] text-[13px] font-medium text-white">IG</div>
        <div class="flex-1">
          <h2 class="text-[17px] font-medium">{{ $t('settings.instagram') }}</h2>
          <p class="mt-1 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.instagramCopy') }}</p>
        </div>
      </div>
      <div v-if="status.connected" class="mt-7 flex items-center gap-3 rounded-[16px] border border-[var(--line-soft)] bg-[var(--paper)] p-4">
        <img v-if="status.account?.profile_picture_url" :src="status.account.profile_picture_url" alt="" class="h-10 w-10 rounded-full object-cover">
        <div>
          <p class="text-sm font-medium">@{{ status.account?.username }}</p>
          <p class="text-xs text-[var(--faint)]">{{ $t('settings.postsImported', { count: status.account?.imported_media_count, status: status.account?.sync_status }) }}</p>
        </div>
        <button class="ml-auto text-xs text-[var(--faint)] transition hover:text-[#9a4c36]" @click="disconnect">{{ $t('settings.disconnect') }}</button>
      </div>
      <button v-else class="mt-7 rounded-full bg-[var(--ink)] px-5 py-3 text-sm font-medium text-white transition hover:bg-black disabled:opacity-60" :disabled="loading" @click="connect">{{ $t('settings.continueWithInstagram') }}</button>
      <p v-if="error" role="alert" class="mt-4 text-sm text-[#9a4c36]">{{ error }}</p>
    </section>

    <section class="mt-5 rounded-[22px] border border-[var(--line)] bg-[var(--surface)] p-6 md:p-8">
      <div class="flex items-start gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[13px] bg-[var(--paper)] text-[var(--muted)]"><AppIcon name="shield" :size="19" /></span>
        <div>
          <h2 class="text-[17px] font-medium">{{ $t('settings.privacy') }}</h2>
          <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $t('settings.privacyCopy') }}</p>
        </div>
      </div>
    </section>
  </main>
</template>
