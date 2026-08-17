<script setup lang="ts">
import type { InstagramSyncStatus } from '~/types/instagram'

definePageMeta({ layout: false })

const route = useRoute()
const { status, loading, error, connect, loadStatus, startPolling } = useInstagram()

const stages: { key: InstagramSyncStatus; label: string }[] = [
  { key: 'connecting', label: 'Connecting Instagram' },
  { key: 'importing_content', label: 'Importing your content' },
  { key: 'understanding_niche', label: 'Understanding your niche' },
  { key: 'learning_style', label: 'Learning your style' },
  { key: 'finding_patterns', label: 'Finding patterns' }
]

const activeStage = computed(() => {
  if (status.value.account?.sync_status === 'completed') return stages.length
  return Math.max(stages.findIndex(stage => stage.key === status.value.account?.sync_status), 0)
})

const callbackError = computed(() => route.query.instagram === 'error'
  ? String(route.query.message || 'Instagram authorization was cancelled.')
  : null)

onMounted(async () => {
  await loadStatus()
  if (route.query.instagram === 'connected' || (status.value.connected && status.value.account?.sync_status !== 'completed')) {
    startPolling()
  }
})
</script>

<template>
  <main class="min-h-screen overflow-hidden bg-[#f7f5f0]">
    <header class="flex h-20 items-center justify-between border-b border-[#dedbd3]/80 px-6 md:px-10">
      <NuxtLink to="/" class="text-[17px] font-semibold tracking-[-0.04em]">Personal</NuxtLink>
      <span class="text-xs tracking-wide text-[#8a877f]">Your private content intelligence</span>
    </header>

    <section class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-6xl items-center gap-14 px-6 py-14 md:grid-cols-[1fr_0.88fr] md:px-10 md:py-20">
      <div class="max-w-xl animate-rise">
        <div class="mb-8 flex items-center gap-2 text-xs font-medium uppercase tracking-[0.16em] text-[#858178]">
          <span class="h-1.5 w-1.5 rounded-full bg-[#c85234]" />
          First, let’s understand you
        </div>

        <template v-if="!status.connected">
          <h1 class="font-serif text-5xl leading-[1.02] tracking-[-0.045em] md:text-7xl">
            Connect your<br>Instagram.
          </h1>
          <p class="mt-7 max-w-lg text-[17px] leading-7 text-[#6f6c65]">
            Personal uses your Instagram account to understand your content, niche and what already works for you.
          </p>

          <button
            class="mt-10 inline-flex h-14 items-center gap-3 rounded-full bg-[#1d1d1b] px-7 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-black disabled:cursor-wait disabled:opacity-60"
            :disabled="loading"
            @click="connect"
          >
            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
              <rect x="3" y="3" width="18" height="18" rx="5" />
              <circle cx="12" cy="12" r="4" />
              <circle cx="17.5" cy="6.5" r=".8" class="fill-current stroke-0" />
            </svg>
            {{ loading ? 'Preparing Instagram…' : 'Continue with Instagram' }}
            <span aria-hidden="true">↗</span>
          </button>

          <p class="mt-5 flex items-center gap-2 text-xs text-[#8a877f]">
            <svg viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current" stroke-width="1.5"><rect x="4" y="8" width="12" height="9" rx="2"/><path d="M7 8V6a3 3 0 016 0v2"/></svg>
            Your access token stays encrypted on Personal’s server.
          </p>
        </template>

        <template v-else>
          <div class="mb-8 inline-flex items-center gap-3 rounded-full border border-[#d8d4ca] bg-white/60 py-2 pl-2 pr-4">
            <img v-if="status.account?.profile_picture_url" :src="status.account.profile_picture_url" alt="" class="h-8 w-8 rounded-full object-cover">
            <span v-else class="grid h-8 w-8 place-items-center rounded-full bg-[#ebe7de] text-xs">IG</span>
            <span class="text-sm font-medium">@{{ status.account?.username }} connected</span>
            <span class="text-[#4e785e]">✓</span>
          </div>

          <h1 class="font-serif text-5xl leading-[1.02] tracking-[-0.045em] md:text-7xl">
            {{ status.account?.sync_status === 'completed' ? 'You’re ready.' : 'Understanding your brand…' }}
          </h1>
          <p class="mt-7 max-w-lg text-[17px] leading-7 text-[#6f6c65]">
            <template v-if="status.account?.sync_status === 'completed'">
              We imported {{ status.account.imported_media_count }} recent posts and built the first version of your Personal profile.
            </template>
            <template v-else>
              We’re reading the real signals in your profile and recent content. You can leave this screen open while the import finishes.
            </template>
          </p>

          <NuxtLink
            v-if="status.account?.sync_status === 'completed'"
            to="/"
            class="mt-10 inline-flex h-14 items-center rounded-full bg-[#1d1d1b] px-7 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-black"
          >
            Start Personal&nbsp; →
          </NuxtLink>

          <button
            v-if="status.account?.sync_status === 'failed'"
            class="mt-8 inline-flex h-12 items-center rounded-full border border-[#b9b4aa] bg-white/60 px-6 text-sm font-medium transition hover:bg-white"
            :disabled="loading"
            @click="connect"
          >
            Reconnect Instagram
          </button>
        </template>

        <p v-if="callbackError || error || status.account?.sync_error" role="alert" class="mt-7 max-w-lg rounded-2xl border border-[#dcb7ac] bg-[#fbefeb] px-4 py-3 text-sm leading-6 text-[#8a3d2a]">
          {{ callbackError || status.account?.sync_error || error }}
        </p>
      </div>

      <aside class="relative min-h-[430px] rounded-[2rem] border border-[#dedbd3] bg-[#eeebe4] p-7 md:p-10">
        <div class="absolute right-8 top-8 flex gap-1.5">
          <span v-for="i in 3" :key="i" class="h-1.5 w-1.5 rounded-full bg-[#c9c5bb]" />
        </div>

        <p class="text-xs font-medium uppercase tracking-[0.14em] text-[#858178]">Personal profile · live</p>
        <div v-if="status.connected" class="mt-12 space-y-1">
          <div
            v-for="(stage, index) in stages"
            :key="stage.key"
            class="flex items-center gap-4 rounded-2xl px-3 py-3.5 transition-all duration-500"
            :class="index === activeStage ? 'bg-white shadow-[0_5px_20px_rgba(54,48,38,.06)]' : ''"
          >
            <span class="grid h-7 w-7 place-items-center rounded-full border text-xs transition-all"
              :class="index < activeStage ? 'border-[#4e785e] bg-[#4e785e] text-white' : index === activeStage ? 'animate-breathe border-[#c85234] bg-[#c85234] text-white' : 'border-[#d5d0c6] text-[#aaa69e]'">
              {{ index < activeStage ? '✓' : index + 1 }}
            </span>
            <span class="text-sm" :class="index <= activeStage ? 'text-[#282724]' : 'text-[#aaa69e]'">{{ stage.label }}</span>
          </div>
        </div>

        <div v-else class="mt-14 space-y-7">
          <div class="h-3 w-28 rounded-full bg-[#d8d4ca]" />
          <div class="space-y-3">
            <div class="h-10 w-4/5 rounded-xl bg-[#e3dfd6]" />
            <div class="h-10 w-3/5 rounded-xl bg-[#e3dfd6]" />
          </div>
          <div class="grid grid-cols-3 gap-3 pt-4">
            <div v-for="i in 3" :key="i" class="h-24 rounded-2xl border border-[#ddd8ce] bg-[#f4f1eb]" />
          </div>
          <p class="pt-3 font-serif text-xl leading-7 text-[#8a877f]">Your content becomes context—not a dashboard.</p>
        </div>
      </aside>
    </section>
  </main>
</template>
