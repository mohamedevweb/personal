<script setup lang="ts">
/**
 * 02 — posts beating the account that published them, not the biggest posts.
 *
 * This is the feed as the app lays it out: the toolbar, then the grid of
 * cards. Each card shows the post exactly the way Instagram shows it, with
 * everything Personal adds sitting below the fold line.
 */
const { t } = useI18n()

// Instagram's CDN refuses to be hotlinked, so a thumbnail is a wash rather
// than a borrowed photograph: light falling across a frame, out of focus, with
// the format mark on top of it.
const WASHES = [
  { angle: 146, kind: 'reel' },
  { angle: 34, kind: 'carousel' },
  { angle: 198, kind: null }
] as const

const posts = computed(() => (['one', 'two', 'three'] as const).map((key, index) => ({
  key,
  kind: WASHES[index]!.kind,
  wash: `radial-gradient(78% 62% at 28% 18%, rgba(255, 255, 255, .72) 0%, rgba(255, 255, 255, 0) 62%), linear-gradient(${WASHES[index]!.angle}deg, rgba(233, 227, 214, .9) 0%, rgba(176, 166, 148, .75) 54%, rgba(104, 96, 82, .6) 100%)`,
  hook: t(`landing.how.outliers.items.${key}.hook`),
  views: t(`landing.how.outliers.items.${key}.views`),
  ratio: t(`landing.how.outliers.items.${key}.ratio`),
  handle: t(`landing.how.outliers.items.${key}.handle`),
  date: t(`landing.how.outliers.items.${key}.date`),
  followers: t(`landing.how.outliers.items.${key}.followers`),
  likes: t(`landing.how.outliers.items.${key}.likes`)
})))
</script>

<template>
  <LandingMockScreen :title="$t('landing.how.screens.feed')">
    <!-- The feed's own toolbar: which feed you are reading, and the one button
         that goes looking again. -->
    <div class="flex items-center justify-between gap-3">
      <div class="inline-flex items-center gap-1 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] p-1">
        <span class="inline-flex h-7 items-center rounded-full bg-[var(--b-black)] px-3.5 text-[12px] font-medium text-[var(--b-ivory)]">
          {{ $t('feed.forYou') }}
        </span>
        <span class="inline-flex h-7 items-center rounded-full px-3.5 text-[12px] text-[var(--b-stone)]">
          {{ $t('feed.global') }}
        </span>
      </div>

      <span class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] px-3.5 text-[12px]">
        <AppIcon name="sparkles" :size="13" />
        <span class="hidden sm:inline">{{ $t('feed.refresh') }}</span>
      </span>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="post in posts"
        :key="post.key"
        class="flex flex-col overflow-hidden rounded-[16px] border border-[var(--b-line)] bg-[var(--b-surface)] shadow-[0_1px_2px_rgba(23,23,21,.04)]"
      >
        <header class="flex items-center gap-2.5 px-3 py-2.5">
          <span class="rounded-full bg-gradient-to-tr from-[#f9ce34] via-[#ee2a7b] to-[#6228d7] p-[2px]">
            <span class="block h-7 w-7 rounded-full border-2 border-[var(--b-surface)] bg-[#e2ddd2]" />
          </span>
          <span class="min-w-0 flex-1 leading-tight">
            <span class="block truncate text-[12px] font-semibold">
              {{ post.handle }}
              <span class="font-normal text-[var(--b-stone)]"> · {{ post.date }}</span>
            </span>
            <span class="block truncate text-[11px] text-[var(--b-stone)]">
              {{ $t('contentCard.followers', { count: post.followers }) }}
            </span>
          </span>
          <AppIcon name="dots" :size="15" class="shrink-0 text-[var(--b-stone)]" />
        </header>

        <div class="relative aspect-[4/3] bg-[#cbc2b1]" :style="{ backgroundImage: post.wash }">
          <AppIcon
            v-if="post.kind"
            :name="post.kind"
            :size="20"
            :stroke-width="1.9"
            class="absolute right-3 top-3 text-white drop-shadow-[0_1px_3px_rgba(0,0,0,.45)]"
          />
        </div>

        <div class="flex items-center gap-3.5 px-3 pb-1 pt-2.5">
          <AppIcon name="heart" :size="19" :stroke-width="1.6" />
          <AppIcon name="chat" :size="19" :stroke-width="1.6" class="-scale-x-100" />
          <AppIcon name="paper-plane" :size="19" :stroke-width="1.6" />
          <AppIcon name="bookmark" :size="19" :stroke-width="1.6" class="ml-auto" />
        </div>

        <div class="px-3 pb-3 text-[12px] leading-[17px]">
          <p class="font-semibold">{{ $t('contentCard.likes', { count: post.likes }) }}</p>
          <p class="mt-1 line-clamp-2">
            <span class="font-semibold">{{ post.handle }}</span>
            {{ ' ' }}{{ post.hook }}
          </p>
        </div>

        <!-- Everything Personal adds on top of the post lives below this line. -->
        <div class="mt-auto border-t border-[var(--b-line)] bg-[var(--b-ivory)] p-3">
          <div class="flex items-center gap-1">
            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-[var(--b-red-100)] px-1.5 py-1 text-[10px] font-semibold text-[var(--b-red-700)]">
              <AppIcon name="trend" :size="12" />
              {{ $t('contentCard.average', { ratio: post.ratio }) }}
            </span>
            <span class="shrink-0 rounded-full border border-[var(--b-line)] px-1.5 py-1 text-[10px] text-[var(--b-stone)]">
              {{ $t('contentCard.views', { count: post.views }) }}
            </span>
          </div>

          <div class="mt-3 grid gap-2">
            <span class="inline-flex h-8 items-center justify-center gap-1.5 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] text-[12px]">
              <AppIcon name="bookmark" :size="13" />
              {{ $t('contentCard.save') }}
            </span>
            <span class="inline-flex h-9 items-center justify-center gap-2 rounded-full bg-[var(--b-black)] text-[12.5px] font-medium text-[var(--b-ivory)]">
              {{ $t('contentCard.remixForMe') }}
              <AppIcon name="arrow" :size="13" />
            </span>
          </div>
        </div>
      </article>
    </div>
  </LandingMockScreen>
</template>
