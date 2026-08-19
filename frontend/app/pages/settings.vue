<script setup lang="ts">
const { status, loading, error, connect, loadStatus } = useInstagram()
const { apiFetch } = usePersonalApi()
async function disconnect() { await apiFetch('/api/integrations/instagram', { method: 'DELETE' }); await loadStatus() }
onMounted(loadStatus)
</script>

<template>
  <main class="mx-auto max-w-3xl px-5 py-10 md:px-10 md:py-14">
    <p class="text-[11px] font-semibold uppercase tracking-[.17em] text-[#918d85]">{{ $t('settings.eyebrow') }}</p><h1 class="mt-4 font-serif text-4xl tracking-[-.04em] md:text-[54px]">{{ $t('settings.title') }}</h1>
    <section class="mt-10 rounded-[24px] border border-[var(--line)] bg-[#fbfaf7] p-6 md:p-8"><div class="flex items-start gap-4"><div class="grid h-12 w-12 place-items-center rounded-2xl bg-[#1d1d1b] text-sm text-white">IG</div><div class="flex-1"><h2 class="text-lg font-medium">{{ $t('settings.instagram') }}</h2><p class="mt-1 text-sm leading-6 text-[#77736c]">{{ $t('settings.instagramCopy') }}</p></div></div><div v-if="status.connected" class="mt-7 flex items-center gap-3 rounded-2xl bg-[#f0eee8] p-4"><img v-if="status.account?.profile_picture_url" :src="status.account.profile_picture_url" class="h-10 w-10 rounded-full"><div><p class="text-sm font-medium">@{{ status.account?.username }}</p><p class="text-xs text-[#77736c]">{{ $t('settings.postsImported', { count: status.account?.imported_media_count, status: status.account?.sync_status }) }}</p></div><button class="ml-auto text-xs text-[#9a4c36]" @click="disconnect">{{ $t('settings.disconnect') }}</button></div><button v-else class="mt-7 rounded-full bg-[#1d1d1b] px-5 py-3 text-sm text-white" :disabled="loading" @click="connect">{{ $t('settings.continueWithInstagram') }}</button><p v-if="error" class="mt-4 text-sm text-[#9a4c36]">{{ error }}</p></section>
    <section class="mt-5 rounded-[24px] border border-[var(--line)] bg-[#fbfaf7] p-6 md:p-8"><h2 class="text-lg font-medium">{{ $t('settings.privacy') }}</h2><p class="mt-2 text-sm leading-6 text-[#77736c]">{{ $t('settings.privacyCopy') }}</p></section>
  </main>
</template>
