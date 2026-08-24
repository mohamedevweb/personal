<script setup lang="ts">
/**
 * 02 — posts beating the account that published them, not the biggest posts.
 *
 * This is the feed as the app lays it out: the toolbar, then the grid of
 * cards. Each card shows the post exactly the way Instagram shows it, with
 * everything Personal adds sitting below the fold line.
 *
 * The toolbar works. The two feeds are the product's actual claim — one is
 * filtered to your niche and your size, the other is everything the crawler
 * found — and the fastest way to make that claim is to let a visitor flip
 * between them and watch the cards change. Refreshing replays the read, which
 * is what the button does in the app.
 */
const props = withDefaults(defineProps<{ active?: boolean }>(), { active: false })

const { t } = useI18n()

/**
 * Thumbnails. Instagram's CDN refuses to be hotlinked, so a post's picture has
 * to be a file this site serves itself: drop a square image in
 * `public/landing/feed/` and name it here, and the card shows a real post.
 *
 * Until one is named, the card falls back to a wash — light falling across a
 * frame, out of focus — which holds the layout without pretending to be a
 * photograph. A file that fails to load falls back the same way, so a missing
 * asset can never leave a broken image on the launch page.
 */
const FEEDS = {
  forYou: [
    { key: 'one', kind: 'reel', angle: 146, image: null },
    { key: 'two', kind: 'carousel', angle: 34, image: null },
    { key: 'three', kind: null, angle: 198, image: null }
  ],
  global: [
    { key: 'four', kind: 'carousel', angle: 76, image: null },
    { key: 'five', kind: null, angle: 214, image: null },
    { key: 'six', kind: 'reel', angle: 12, image: null }
  ]
} as const

type Feed = keyof typeof FEEDS

const feed = ref<Feed>('forYou')
const broken = ref<Record<string, boolean>>({})

/**
 * How many cards have landed. The grid deals itself out rather than appearing
 * whole: this feed is a morning delivery, and a delivery arrives.
 */
const dealt = ref(3)
const reading = ref(false)
const motion = ref(false)

let timer: ReturnType<typeof setInterval> | null = null
let settle: ReturnType<typeof setTimeout> | null = null

function deal() {
  if (timer) clearInterval(timer)

  // Asked for stillness, the cards are simply there. The staggered deal is a
  // flourish; the feed being full is not.
  if (!motion.value) {
    dealt.value = 3
    return
  }

  dealt.value = 0
  timer = setInterval(() => {
    dealt.value += 1
    if (dealt.value >= 3 && timer) clearInterval(timer)
  }, 130)
}

/** The toolbar's own button: the read runs again, and says so while it does. */
function refresh() {
  if (reading.value) return

  reading.value = true
  deal()

  if (settle) clearTimeout(settle)
  settle = setTimeout(() => { reading.value = false }, 900)
}

function show(next: Feed) {
  if (feed.value === next) return
  feed.value = next
  deal()
}

onMounted(() => {
  motion.value = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (!motion.value) return

  dealt.value = 0
  watch(() => props.active, (on) => { if (on && dealt.value === 0) deal() }, { immediate: true })
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (settle) clearTimeout(settle)
})

const posts = computed(() => FEEDS[feed.value].map(({ key, kind, angle, image }) => ({
  key,
  kind,
  image: broken.value[key] ? null : image,
  wash: `radial-gradient(78% 62% at 28% 18%, rgba(255, 255, 255, .72) 0%, rgba(255, 255, 255, 0) 62%), linear-gradient(${angle}deg, rgba(233, 227, 214, .9) 0%, rgba(176, 166, 148, .75) 54%, rgba(104, 96, 82, .6) 100%)`,
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
         that goes looking again. Both are live. -->
    <div class="flex items-center justify-between gap-3">
      <div class="inline-flex items-center gap-1 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] p-1">
        <button
          v-for="tab in (['forYou', 'global'] as const)"
          :key="tab"
          type="button"
          :aria-pressed="feed === tab"
          class="b-focus inline-flex h-7 items-center rounded-full px-3.5 text-[12px] transition-colors duration-300"
          :class="feed === tab
            ? 'bg-[var(--b-black)] font-medium text-[var(--b-ivory)]'
            : 'text-[var(--b-stone)] hover:text-[var(--b-black)]'"
          @click="show(tab)"
        >
          {{ $t(`feed.${tab}`) }}
        </button>
      </div>

      <button
        type="button"
        class="b-focus inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] px-3.5 text-[12px] transition-colors hover:border-[#d6cfc0] hover:bg-[var(--b-ivory)]"
        @click="refresh"
      >
        <AppIcon name="sparkles" :size="13" :class="reading ? 'b-spin' : ''" />
        <span class="hidden sm:inline">{{ reading ? $t('feed.refreshing') : $t('feed.refresh') }}</span>
      </button>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="(post, index) in posts"
        :key="post.key"
        class="flex flex-col overflow-hidden rounded-[16px] border border-[var(--b-line)] bg-[var(--b-surface)] shadow-[0_1px_2px_rgba(23,23,21,.04)] transition-all duration-500 hover:-translate-y-0.5 hover:border-[#d6cfc0] hover:shadow-[0_18px_34px_-26px_rgba(23,23,21,.55)]"
        :class="index < dealt ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0'"
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

        <div class="relative aspect-[4/3] overflow-hidden bg-[#cbc2b1]" :style="{ backgroundImage: post.wash }">
          <img
            v-if="post.image"
            :src="post.image"
            alt=""
            loading="lazy"
            class="h-full w-full object-cover"
            @error="broken[post.key] = true"
          >
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
              {{ $t('contentCard.averageAccount', { ratio: post.ratio }) }}
            </span>
            <span class="shrink-0 rounded-full border border-[var(--b-line)] px-1.5 py-1 text-[10px] text-[var(--b-stone)]">
              {{ $t('contentCard.views', { count: post.views }) }}
            </span>
          </div>

          <div class="mt-3 grid gap-2">
            <LandingMockAction
              class="inline-flex h-8 items-center justify-center gap-1.5 rounded-full border border-[var(--b-line)] bg-[var(--b-surface)] text-[12px] transition-colors hover:border-[#d6cfc0] hover:bg-[var(--b-ivory)]"
            >
              <AppIcon name="bookmark" :size="13" />
              {{ $t('contentCard.save') }}
            </LandingMockAction>

            <LandingMockAction
              class="group inline-flex h-9 items-center justify-center gap-2 rounded-full bg-[var(--b-black)] text-[12.5px] font-medium text-[var(--b-ivory)] hover:bg-black"
            >
              {{ $t('contentCard.remixForMe') }}
              <AppIcon name="arrow" :size="13" class="transition-transform duration-300 group-hover:translate-x-0.5" />
            </LandingMockAction>
          </div>
        </div>
      </article>
    </div>
  </LandingMockScreen>
</template>
