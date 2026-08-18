<script setup lang="ts">
definePageMeta({ layout: false })

useHead({
  title: 'Personal — Claude for personal branding',
  meta: [
    { name: 'description', content: "Personal is an AI that knows what's happening in your life and turns it into your personal brand. Know what to post next." }
  ]
})

const loop = [
  {
    step: '01',
    label: 'Understand me',
    title: 'It learns who you are before it opens its mouth.',
    body: 'Connect Instagram once. Personal reads your posts, your audience and your positioning, then tells you what it understood — your niche, your topics, your tone. If it gets something wrong, you correct it in a sentence.',
    accent: '#c85234'
  },
  {
    step: '02',
    label: 'Find what works',
    title: 'It finds the post you would never have found alone.',
    body: 'Personal watches creators in your niche at your size and surfaces the outliers — the posts doing 8× their author’s average, while they are still climbing. Not a trend report. Four things worth making today.',
    accent: '#3f6079'
  },
  {
    step: '03',
    label: 'Remix for me',
    title: 'It rewrites the winner as something only you could post.',
    body: 'Personal explains why the post works — hook, structure, timing — then rebuilds it around your life. Your pivot, your last four months, your actual opinion. A Reel or a carousel you could publish in the next ten minutes.',
    accent: '#a07a2c'
  }
]

const steps = [
  { title: 'Connect Instagram', body: 'One OAuth screen. No password, no scraping, no posting on your behalf.' },
  { title: 'Personal reads you', body: 'Niche, topics, audience, tone and positioning — shown back to you in plain language.' },
  { title: 'Create what’s next', body: 'A daily brief of content opportunities, each one remixable into your own voice.' }
]

const faqs = [
  {
    q: 'What does Personal actually do?',
    a: 'It answers one question every day: what should I post next? It understands your profile, finds the posts outperforming in your niche right now, explains why they work, and rewrites them around what is happening in your life.'
  },
  {
    q: 'How is this different from a trend tool?',
    a: 'Trend tools show you what is popular. Personal shows you what is working for creators like you, at your size, and then does the hard part — turning it into a piece of content that sounds like you and draws on your own story.'
  },
  {
    q: 'What do you need from me to start?',
    a: 'Your Instagram account, connected through Instagram’s official login. Everything else — niche, tone, audience, positioning — Personal works out on its own and shows you for approval.'
  },
  {
    q: 'What is a Moment?',
    a: 'A short note about something real in your life: a pivot, a launch, a failure, a lesson. Personal keeps them and links them to opportunities, so when a format starts working, it already knows which of your stories fits it.'
  },
  {
    q: 'Will it post for me?',
    a: 'No. Personal drafts, you decide. Nothing is published without you, and nothing is scheduled behind your back.'
  },
  {
    q: 'Who is it for?',
    a: 'Creators building a personal brand on Instagram who post regularly and are tired of staring at an empty caption box. It is most useful if you already know your niche and want to stop guessing about format.'
  },
  {
    q: 'How much does it cost?',
    a: 'Nothing during early access. We are onboarding a small group of creators by hand and building the next version around what they tell us.'
  }
]

const heroPhases = [
  { id: 'understand', tab: 'Understand me', eyebrow: 'Reading your last 40 posts' },
  { id: 'find', tab: 'Find what works', eyebrow: 'Scanning your niche' },
  { id: 'remix', tab: 'Remix for me', eyebrow: 'Writing your version' }
]

const profileFields = [
  { label: 'Niche', value: 'Indie product building' },
  { label: 'Audience', value: 'Solo founders, 22–34' },
  { label: 'Tone', value: 'Direct · honest · dry' },
  { label: 'Positioning', value: 'Builds in public, shows the mess' }
]

const outliers = [
  { hook: 'The pricing mistake that cost me 8 months', ratio: 8.4, views: '1.2M', tone: '#3f6079' },
  { hook: 'Nobody tells you this about your first 1000', ratio: 5.1, views: '740K', tone: '#6b7f8c' },
  { hook: 'I rebuilt my whole onboarding in a weekend', ratio: 3.7, views: '410K', tone: '#8d9aa3' }
]

const remixHookFull = 'I spent four months studying creators. Then I deleted the product.'
const HERO_PHASE_MS = 4600

const activePhase = ref(0)
const progressKey = ref(0)
const isHovering = ref(false)
const remixHookTyped = ref('')
const outlierDisplay = ref(outliers.map(() => 0))
const reducedMotion = ref(false)

let phaseTimer: ReturnType<typeof setInterval> | null = null
let typeTimer: ReturnType<typeof setInterval> | null = null

function stopAutoplay() {
  if (phaseTimer) clearInterval(phaseTimer)
  phaseTimer = null
}

function startAutoplay() {
  stopAutoplay()
  if (reducedMotion.value) return
  phaseTimer = setInterval(() => {
    activePhase.value = (activePhase.value + 1) % heroPhases.length
    progressKey.value++
  }, HERO_PHASE_MS)
}

function goToPhase(index: number) {
  if (index === activePhase.value) return
  activePhase.value = index
  progressKey.value++
  startAutoplay()
}

function typeRemixHook() {
  if (typeTimer) clearInterval(typeTimer)
  if (reducedMotion.value) {
    remixHookTyped.value = remixHookFull
    return
  }
  remixHookTyped.value = ''
  let i = 0
  typeTimer = setInterval(() => {
    i++
    remixHookTyped.value = remixHookFull.slice(0, i)
    if (i >= remixHookFull.length && typeTimer) clearInterval(typeTimer)
  }, 26)
}

function animateOutliers() {
  if (reducedMotion.value) {
    outlierDisplay.value = outliers.map(o => o.ratio)
    return
  }
  const start = performance.now()
  const duration = 850
  function tick(now: number) {
    const t = Math.min(1, (now - start) / duration)
    const eased = 1 - Math.pow(1 - t, 3)
    outlierDisplay.value = outliers.map(o => Math.round(o.ratio * eased * 10) / 10)
    if (t < 1) requestAnimationFrame(tick)
  }
  requestAnimationFrame(tick)
}

watch(activePhase, (phase) => {
  if (phase === 1) animateOutliers()
  if (phase === 2) typeRemixHook()
}, { immediate: true })

const revealRoot = ref<HTMLElement | null>(null)

function pauseHeroAutoplay() {
  isHovering.value = true
  stopAutoplay()
}

function resumeHeroAutoplay() {
  isHovering.value = false
  startAutoplay()
}

onMounted(() => {
  reducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (reducedMotion.value) {
    outlierDisplay.value = outliers.map(o => o.ratio)
    remixHookTyped.value = remixHookFull
  } else {
    startAutoplay()
  }

  const root = revealRoot.value
  const targets = root?.querySelectorAll('[data-reveal]')
  if (!root || !targets?.length) return

  // Hidden state is applied only once JS is running, so the page still reads
  // fully if the script never executes.
  root.classList.add('js-reveal')

  if (!('IntersectionObserver' in window)) {
    targets.forEach(node => node.classList.add('is-revealed'))
    return
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return
      entry.target.classList.add('is-revealed')
      observer.unobserve(entry.target)
    })
  }, { rootMargin: '0px 0px -12% 0px', threshold: 0.15 })

  targets.forEach(node => observer.observe(node))
})

onUnmounted(() => {
  stopAutoplay()
  if (typeTimer) clearInterval(typeTimer)
})
</script>

<template>
  <div ref="revealRoot" class="min-h-screen bg-[#f7f5f0] text-[#1d1d1b] antialiased">
    <!-- NAV -->
    <header class="sticky top-0 z-40 border-b border-[#dedbd3]/70 bg-[#f7f5f0]/85 backdrop-blur-md">
      <div class="mx-auto flex h-[72px] max-w-[1160px] items-center justify-between px-6 md:px-10">
        <NuxtLink to="/" class="text-[19px] font-semibold tracking-[-0.045em]">Personal</NuxtLink>

        <nav class="hidden items-center gap-9 text-[14px] text-[#6f6c65] md:flex">
          <a href="#product" class="transition hover:text-[#1d1d1b]">Product</a>
          <a href="#how" class="transition hover:text-[#1d1d1b]">How it works</a>
          <a href="#moments" class="transition hover:text-[#1d1d1b]">Moments</a>
          <a href="#faq" class="transition hover:text-[#1d1d1b]">FAQ</a>
        </nav>

        <div class="flex items-center gap-2">
          <NuxtLink to="/login" class="hidden rounded-full px-4 py-2.5 text-[14px] text-[#6f6c65] transition hover:text-[#1d1d1b] sm:inline-flex">Sign in</NuxtLink>
          <NuxtLink to="/login" class="inline-flex h-11 items-center rounded-full bg-[#1d1d1b] px-5 text-[14px] font-medium text-white transition hover:bg-black">Get early access</NuxtLink>
        </div>
      </div>
    </header>

    <!-- HERO -->
    <section class="relative overflow-hidden px-6 pb-8 pt-16 md:px-10 md:pt-24">
      <div class="pointer-events-none absolute -left-40 top-10 h-[520px] w-[520px] rounded-full bg-[#c85234]/10 blur-[120px]" />
      <div class="pointer-events-none absolute -right-32 top-40 h-[460px] w-[460px] rounded-full bg-[#3f6079]/10 blur-[120px]" />

      <div class="relative mx-auto max-w-[1160px]">
        <div class="mx-auto max-w-3xl text-center">
          <p data-reveal class="reveal inline-flex items-center gap-2 rounded-full border border-[#dedbd3] bg-white/70 px-4 py-1.5 text-[12px] font-medium tracking-[.02em] text-[#77736c]">
            <span class="h-1.5 w-1.5 rounded-full bg-[#c85234]" />
            Claude for personal branding
          </p>

          <h1 data-reveal class="reveal mt-8 font-serif text-[46px] leading-[1.02] tracking-[-0.045em] sm:text-[62px] md:text-[76px]" style="transition-delay:60ms">
            You’re a creator.<br class="hidden sm:block"> Not a content machine.
          </h1>

          <p data-reveal class="reveal mx-auto mt-7 max-w-[42rem] text-[17px] leading-8 text-[#6f6c65] md:text-[19px]" style="transition-delay:120ms">
            Personal is an AI that knows what’s happening in your life and turns it into your personal brand.
            Connect your account and stop guessing what to post next.
          </p>

          <div data-reveal class="reveal mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row" style="transition-delay:180ms">
            <NuxtLink to="/login" class="inline-flex h-14 w-full items-center justify-center gap-2 rounded-full bg-[#1d1d1b] px-8 text-[15px] font-medium text-white transition hover:-translate-y-0.5 hover:bg-black sm:w-auto">
              Get early access <AppIcon name="arrow" :size="17" />
            </NuxtLink>
            <a href="#product" class="inline-flex h-14 w-full items-center justify-center rounded-full border border-[#d8d4ca] bg-white/60 px-8 text-[15px] font-medium text-[#1d1d1b] transition hover:-translate-y-0.5 hover:bg-white sm:w-auto">
              See how it works
            </a>
          </div>

          <p data-reveal class="reveal mt-5 text-[13px] text-[#918d85]" style="transition-delay:220ms">Free during early access · Instagram login · Personal never posts for you</p>
        </div>

        <!-- HERO MOCK: live, interactive walkthrough of the core loop -->
        <div
          data-reveal
          class="reveal relative mx-auto mt-16 max-w-[880px]"
          style="transition-delay:280ms"
          @mouseenter="pauseHeroAutoplay"
          @mouseleave="resumeHeroAutoplay"
          @focusin="pauseHeroAutoplay"
          @focusout="resumeHeroAutoplay"
        >
          <div class="pill pill-a"><span class="dot bg-[#c85234]" />Profile understood</div>
          <div class="pill pill-b"><span class="dot bg-[#3f6079]" />12 outliers found</div>
          <div class="pill pill-c"><span class="dot bg-[#a07a2c]" />Moment linked</div>
          <div class="pill pill-d"><span class="dot bg-[#4a7a52]" />Reel drafted · 40s</div>

          <div class="overflow-hidden rounded-[28px] border border-[#e2ded5] bg-[#22221f] p-2 shadow-[0_40px_90px_-40px_rgba(45,40,32,.55)]" :class="{ 'hero-frozen': isHovering }">
            <!-- Instagram-stories-style phase control: watch the loop run, or drive it yourself -->
            <div class="flex items-stretch gap-2 px-4 pt-3 pb-1 md:px-6">
              <button
                v-for="(phase, index) in heroPhases"
                :key="phase.id"
                type="button"
                class="flex-1 py-1.5 text-left"
                :aria-current="activePhase === index ? 'step' : undefined"
                :aria-label="`Jump to ${phase.tab}`"
                @click="goToPhase(index)"
              >
                <span class="block truncate text-[11px] font-medium tracking-[.01em] transition" :class="activePhase === index ? 'text-white' : 'text-white/40 hover:text-white/70'">{{ phase.tab }}</span>
                <span class="mt-2 block h-[3px] w-full overflow-hidden rounded-full bg-white/15">
                  <span v-if="index < activePhase" class="block h-full w-full rounded-full bg-white/60" />
                  <span
                    v-else-if="index === activePhase"
                    :key="`bar-${progressKey}`"
                    class="hero-progress-bar block h-full rounded-full bg-white"
                    :style="{ animationDuration: `${HERO_PHASE_MS}ms` }"
                  />
                </span>
              </button>
            </div>

            <div class="rounded-[22px] bg-[#faf9f5] p-6 md:p-8">
              <div class="flex items-center justify-between border-b border-[#e6e2d9] pb-5">
                <div>
                  <p class="text-[10px] font-semibold uppercase tracking-[.17em] text-[#a09b91]">
                    <Transition name="fade-swap" mode="out-in"><span :key="activePhase">{{ heroPhases[activePhase].eyebrow }}</span></Transition>
                  </p>
                  <p class="mt-2 font-serif text-[26px] leading-none tracking-[-.035em]">Good morning, Mohamed.</p>
                </div>
                <span class="hidden rounded-full bg-[#ecebe4] px-3.5 py-1.5 text-[12px] text-[#6f6c65] sm:inline-flex">4 opportunities</span>
              </div>

              <Transition name="fade-swap" mode="out-in">
                <div :key="activePhase" class="mt-6 grid gap-4 md:grid-cols-[1.15fr_1fr] md:min-h-[268px]">
                  <!-- LEFT: what Personal is doing right now -->
                  <article v-if="activePhase === 0" class="rounded-2xl border border-[#e6e2d9] bg-white p-5">
                    <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">Personal understands you</p>
                    <div class="mt-5 space-y-3.5 text-[14px]">
                      <div
                        v-for="(field, i) in profileFields"
                        :key="field.label"
                        class="hero-stagger flex items-baseline justify-between gap-6 border-b border-[#efece5] pb-3 last:border-0 last:pb-0"
                        :style="{ animationDelay: `${i * 110}ms` }"
                      >
                        <span class="text-[#918d85]">{{ field.label }}</span><span class="text-right font-medium">{{ field.value }}</span>
                      </div>
                    </div>
                  </article>

                  <article v-else-if="activePhase === 1" class="rounded-2xl border border-[#e6e2d9] bg-white p-5">
                    <div class="flex items-center justify-between">
                      <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">Outliers in your niche</p>
                      <span class="text-[11px] text-[#a09b91]">still climbing</span>
                    </div>
                    <div class="mt-4 space-y-3">
                      <div v-for="(row, i) in outliers" :key="row.hook" class="hero-stagger flex items-center gap-3" :style="{ animationDelay: `${i * 110}ms` }">
                        <span class="grid h-10 w-11 shrink-0 place-items-center rounded-lg text-[12px] font-semibold tabular-nums text-white" :style="{ background: row.tone }">{{ outlierDisplay[i].toFixed(1) }}×</span>
                        <div class="min-w-0 flex-1">
                          <p class="truncate text-[13px] font-medium">{{ row.hook }}</p>
                          <p class="mt-0.5 text-[11px] text-[#918d85]">{{ row.views }} views</p>
                        </div>
                      </div>
                    </div>
                  </article>

                  <article v-else class="rounded-2xl border border-[#e6e2d9] bg-white p-5">
                    <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">
                      <AppIcon name="sparkles" :size="12" /> Your version · Reel
                    </div>
                    <p class="mt-4 min-h-[4.5em] font-serif text-[19px] leading-[1.32] tracking-[-.02em]">
                      &ldquo;{{ remixHookTyped }}<span class="hero-caret">|</span>&rdquo;
                    </p>
                    <p class="mt-4 text-[12px] leading-6 text-[#8a877f]">Beat 2 — what the research told me, and why I ignored it. Beat 3 — the line that made me start over.</p>
                  </article>

                  <!-- RIGHT: why, plus the moment it's built from -->
                  <div class="flex flex-col gap-4">
                    <div v-if="activePhase === 0" class="hero-stagger rounded-2xl border border-[#e6e2d9] bg-white p-5">
                      <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">Confidence</p>
                      <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-[#efece5]"><div class="hero-confidence h-full rounded-full bg-[#c85234]" /></div>
                      <p class="mt-3 text-[13px] leading-6 text-[#5f5c56]">Niche, audience and tone matched from your last 40 posts.</p>
                    </div>
                    <div v-else-if="activePhase === 1" class="hero-stagger rounded-2xl border border-[#e6e2d9] bg-white p-5">
                      <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">Why these rank first</p>
                      <p class="mt-3 text-[13px] leading-6 text-[#5f5c56]">Failure → realization → lesson is outperforming in your niche this week — and it fits your voice.</p>
                    </div>
                    <div v-else class="hero-stagger rounded-2xl border border-[#e6e2d9] bg-white p-5">
                      <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">Why Personal recommends this</p>
                      <p class="mt-3 text-[13px] leading-6 text-[#5f5c56]">Built from the outlier above and the moment you shared.</p>
                    </div>

                    <div class="hero-stagger rounded-2xl bg-[#22221f] p-5 text-white" style="animation-delay:160ms">
                      <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-white/50">Your moment</p>
                      <p class="mt-3 text-[13px] leading-6 text-white/80">&ldquo;I pivoted my product after four months of research.&rdquo;</p>
                      <span class="mt-4 inline-flex h-9 items-center gap-2 rounded-full bg-white px-4 text-[12px] font-medium text-[#22221f]">
                        <AppIcon name="sparkles" :size="14" /> Remix for me
                      </span>
                    </div>
                  </div>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CORE LOOP STRIP -->
    <section class="border-y border-[#dedbd3] bg-[#f2f0ea] px-6 py-8 md:px-10">
      <div data-reveal class="reveal mx-auto flex max-w-[1160px] flex-col items-center justify-center gap-3 text-center text-[14px] text-[#6f6c65] sm:flex-row sm:gap-6">
        <span class="font-medium text-[#1d1d1b]">Understand me</span>
        <span class="text-[#c0bcb2]">→</span>
        <span class="font-medium text-[#1d1d1b]">Find what works</span>
        <span class="text-[#c0bcb2]">→</span>
        <span class="font-medium text-[#1d1d1b]">Remix for me</span>
        <span class="hidden text-[#a5a199] sm:inline">·</span>
        <span>the whole product, in three moves</span>
      </div>
    </section>

    <!-- PRODUCT -->
    <section id="product" class="scroll-mt-24 px-6 py-24 md:px-10 md:py-32">
      <div class="mx-auto max-w-[1160px]">
        <div data-reveal class="reveal max-w-3xl">
          <p class="text-[12px] font-semibold uppercase tracking-[.18em] text-[#a09b91]">The product</p>
          <h2 class="mt-5 font-serif text-[38px] leading-[1.06] tracking-[-.04em] md:text-[54px]">
            An AI that runs the strategy, so you can run the story.
          </h2>
        </div>

        <div class="mt-20 space-y-24 md:space-y-32">
          <div v-for="(block, index) in loop" :key="block.step" data-reveal class="reveal grid items-center gap-10 md:grid-cols-2 md:gap-16">
            <div :class="index % 2 === 1 ? 'md:order-2' : ''">
              <p class="flex items-center gap-3 text-[12px] font-semibold uppercase tracking-[.18em]" :style="{ color: block.accent }">
                <span class="tabular-nums">{{ block.step }}</span>
                <span class="h-px w-8" :style="{ background: block.accent }" />
                {{ block.label }}
              </p>
              <h3 class="mt-6 font-serif text-[28px] leading-[1.15] tracking-[-.03em] md:text-[38px]">{{ block.title }}</h3>
              <p class="mt-5 max-w-lg text-[16px] leading-8 text-[#6f6c65]">{{ block.body }}</p>
            </div>

            <div :class="index % 2 === 1 ? 'md:order-1' : ''">
              <!-- 01 · profile -->
              <div v-if="index === 0" class="rounded-[24px] border border-[#e2ded5] bg-white p-7 shadow-[0_24px_60px_-40px_rgba(45,40,32,.4)]">
                <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">Personal understands you</p>
                <div class="mt-6 space-y-5 text-[14px]">
                  <div class="flex items-baseline justify-between gap-6 border-b border-[#efece5] pb-4">
                    <span class="text-[#918d85]">Niche</span><span class="text-right font-medium">Indie product building</span>
                  </div>
                  <div class="flex items-baseline justify-between gap-6 border-b border-[#efece5] pb-4">
                    <span class="text-[#918d85]">Audience</span><span class="text-right font-medium">Solo founders, 22–34</span>
                  </div>
                  <div class="flex items-baseline justify-between gap-6 border-b border-[#efece5] pb-4">
                    <span class="text-[#918d85]">Tone</span><span class="text-right font-medium">Direct · honest · dry</span>
                  </div>
                  <div class="flex items-baseline justify-between gap-6">
                    <span class="text-[#918d85]">Positioning</span><span class="text-right font-medium">Builds in public, shows the mess</span>
                  </div>
                </div>
                <p class="mt-6 rounded-xl bg-[#f5f3ec] px-4 py-3 text-[12px] text-[#77736c]">Not right? Fix it in one sentence and Personal updates.</p>
              </div>

              <!-- 02 · outliers -->
              <div v-else-if="index === 1" class="space-y-3">
                <div v-for="row in [
                  { hook: 'The pricing mistake that cost me 8 months', ratio: '8.4×', views: '1.2M', tone: '#3f6079' },
                  { hook: 'Nobody tells you this about your first 1000', ratio: '5.1×', views: '740K', tone: '#6b7f8c' },
                  { hook: 'I rebuilt my whole onboarding in a weekend', ratio: '3.7×', views: '410K', tone: '#8d9aa3' }
                ]" :key="row.hook" class="flex items-center gap-4 rounded-2xl border border-[#e2ded5] bg-white p-4">
                  <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-[12px] font-semibold text-white" :style="{ background: row.tone }">{{ row.ratio }}</span>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-[14px] font-medium">{{ row.hook }}</p>
                    <p class="mt-1 text-[12px] text-[#918d85]">{{ row.views }} views · your niche · still climbing</p>
                  </div>
                </div>
                <p class="pt-2 text-center text-[12px] text-[#918d85]">Ranked by what actually applies to you — not by follower count.</p>
              </div>

              <!-- 03 · remix -->
              <div v-else class="rounded-[24px] border border-[#e2ded5] bg-white p-7 shadow-[0_24px_60px_-40px_rgba(45,40,32,.4)]">
                <div class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">
                  <AppIcon name="sparkles" :size="14" /> Your version · Reel
                </div>
                <div class="mt-5 space-y-4 text-[14px] leading-7">
                  <p><span class="text-[#a07a2c]">Hook</span> — “I spent four months studying creators. Then I deleted the product.”</p>
                  <p class="text-[#5f5c56]"><span class="text-[#a07a2c]">Beat 2</span> — What the research actually told me, and why I ignored it for so long.</p>
                  <p class="text-[#5f5c56]"><span class="text-[#a07a2c]">Beat 3</span> — The one line from a creator that made me start over.</p>
                  <p class="text-[#5f5c56]"><span class="text-[#a07a2c]">Close</span> — What I’m building instead, in seven days.</p>
                </div>
                <div class="mt-6 flex gap-2 border-t border-[#efece5] pt-5 text-[12px]">
                  <span class="rounded-full bg-[#1d1d1b] px-4 py-2 font-medium text-white">Use this</span>
                  <span class="rounded-full border border-[#dedbd3] px-4 py-2 text-[#6f6c65]">Try a carousel</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- STAT -->
    <section class="border-y border-[#dedbd3] bg-[#22221f] px-6 py-24 text-white md:px-10 md:py-28">
      <div data-reveal class="reveal mx-auto max-w-[1160px] text-center">
        <p class="font-serif text-[64px] leading-none tracking-[-.05em] md:text-[110px]">8.4×</p>
        <p class="mx-auto mt-7 max-w-2xl text-[17px] leading-8 text-white/60 md:text-[19px]">
          The kind of post Personal puts in front of you — outperforming its own creator’s average, in your niche, while it is still climbing. You see it before it becomes a trend everybody copies.
        </p>
      </div>
    </section>

    <!-- MOMENTS -->
    <section id="moments" class="scroll-mt-24 px-6 py-24 md:px-10 md:py-32">
      <div class="mx-auto max-w-[1160px]">
        <div data-reveal class="reveal mx-auto max-w-3xl text-center">
          <p class="text-[12px] font-semibold uppercase tracking-[.18em] text-[#a09b91]">Moments</p>
          <h2 class="mt-5 font-serif text-[38px] leading-[1.06] tracking-[-.04em] md:text-[54px]">
            The more Personal learns, the less you have to explain.
          </h2>
          <p class="mx-auto mt-6 max-w-xl text-[16px] leading-8 text-[#6f6c65]">
            Drop in what is actually happening — a pivot, a launch, a bad week. Personal keeps it and waits for the format that fits.
          </p>
        </div>

        <div data-reveal class="reveal mt-16 grid gap-5 md:grid-cols-3">
          <div class="rounded-[22px] border border-[#e2ded5] bg-white p-6">
            <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">You wrote</p>
            <p class="mt-4 text-[15px] leading-7">“I pivoted my product after spending four months studying creators.”</p>
            <p class="mt-5 text-[12px] text-[#918d85]">Added Tuesday · Turning point</p>
          </div>
          <div class="rounded-[22px] border border-[#e2ded5] bg-[#f2f0ea] p-6">
            <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#a09b91]">Personal noticed</p>
            <p class="mt-4 text-[15px] leading-7">Failure → realization → lesson posts are outperforming in your niche right now.</p>
            <p class="mt-5 text-[12px] text-[#918d85]">3 outliers this week · same format</p>
          </div>
          <div class="rounded-[22px] bg-[#22221f] p-6 text-white">
            <p class="text-[10px] font-semibold uppercase tracking-[.16em] text-white/50">So it says</p>
            <p class="mt-4 text-[15px] leading-7">“You have the perfect story for this format. Want me to draft it as a Reel?”</p>
            <p class="mt-5 text-[12px] text-white/45">That’s the whole point of Personal.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how" class="scroll-mt-24 border-t border-[#dedbd3] bg-[#f2f0ea] px-6 py-24 md:px-10 md:py-32">
      <div class="mx-auto max-w-[1160px]">
        <div data-reveal class="reveal max-w-2xl">
          <p class="text-[12px] font-semibold uppercase tracking-[.18em] text-[#a09b91]">How it works</p>
          <h2 class="mt-5 font-serif text-[38px] leading-[1.06] tracking-[-.04em] md:text-[50px]">Three minutes to set up. Then it’s just there every morning.</h2>
        </div>

        <div data-reveal class="reveal mt-16 grid gap-px overflow-hidden rounded-[24px] border border-[#dedbd3] bg-[#dedbd3] md:grid-cols-3">
          <div v-for="(step, index) in steps" :key="step.title" class="bg-[#f7f5f0] p-8 md:p-10">
            <span class="grid h-10 w-10 place-items-center rounded-full border border-[#d8d4ca] text-[13px] font-medium tabular-nums text-[#77736c]">{{ index + 1 }}</span>
            <h3 class="mt-6 text-[18px] font-medium tracking-[-.02em]">{{ step.title }}</h3>
            <p class="mt-3 text-[15px] leading-7 text-[#6f6c65]">{{ step.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="scroll-mt-24 px-6 py-24 md:px-10 md:py-32">
      <div class="mx-auto grid max-w-[1160px] gap-12 md:grid-cols-[380px_1fr] md:gap-20">
        <div data-reveal class="reveal">
          <p class="text-[12px] font-semibold uppercase tracking-[.18em] text-[#a09b91]">FAQ</p>
          <h2 class="mt-5 font-serif text-[38px] leading-[1.06] tracking-[-.04em] md:text-[46px]">Questions creators ask us first.</h2>
        </div>

        <div data-reveal class="reveal divide-y divide-[#dedbd3] border-y border-[#dedbd3]">
          <details v-for="faq in faqs" :key="faq.q" class="faq group py-6">
            <summary class="flex cursor-pointer list-none items-start justify-between gap-6 text-[17px] leading-7 tracking-[-.015em]">
              {{ faq.q }}
              <span class="mt-1 shrink-0 text-[#a09b91] transition-transform duration-300 group-open:rotate-45">
                <AppIcon name="plus" :size="18" />
              </span>
            </summary>
            <p class="mt-4 max-w-2xl text-[15px] leading-8 text-[#6f6c65]">{{ faq.a }}</p>
          </details>
        </div>
      </div>
    </section>

    <!-- FINAL CTA -->
    <section class="px-6 pb-24 md:px-10">
      <div data-reveal class="reveal relative mx-auto max-w-[1160px] overflow-hidden rounded-[32px] bg-[#22221f] px-8 py-20 text-center text-white md:px-16 md:py-28">
        <div class="pointer-events-none absolute -left-20 -top-24 h-72 w-72 rounded-full bg-[#c85234]/25 blur-[100px]" />
        <div class="pointer-events-none absolute -bottom-28 -right-10 h-72 w-72 rounded-full bg-[#3f6079]/25 blur-[100px]" />
        <div class="relative">
          <h2 class="mx-auto max-w-3xl font-serif text-[40px] leading-[1.05] tracking-[-.04em] md:text-[62px]">Know what to post next.</h2>
          <p class="mx-auto mt-6 max-w-xl text-[16px] leading-8 text-white/60">
            We are onboarding a small group of creators by hand this week. If you post on Instagram and you are tired of the blank caption box, come in early.
          </p>
          <NuxtLink to="/login" class="mt-10 inline-flex h-14 items-center gap-2 rounded-full bg-white px-8 text-[15px] font-medium text-[#22221f] transition hover:-translate-y-0.5">
            Get early access <AppIcon name="arrow" :size="17" />
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="border-t border-[#dedbd3] px-6 py-12 md:px-10">
      <div class="mx-auto flex max-w-[1160px] flex-col gap-8 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-[17px] font-semibold tracking-[-0.045em]">Personal</p>
          <p class="mt-2 text-[14px] text-[#918d85]">Claude for personal branding.</p>
        </div>
        <div class="flex flex-wrap items-center gap-x-8 gap-y-3 text-[14px] text-[#6f6c65]">
          <a href="#product" class="transition hover:text-[#1d1d1b]">Product</a>
          <a href="#how" class="transition hover:text-[#1d1d1b]">How it works</a>
          <a href="#faq" class="transition hover:text-[#1d1d1b]">FAQ</a>
          <NuxtLink to="/login" class="transition hover:text-[#1d1d1b]">Sign in</NuxtLink>
        </div>
        <p class="text-[13px] text-[#a09b91]">© 2026 Personal</p>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.js-reveal .reveal {
  opacity: 0;
  transform: translateY(18px);
  transition: opacity .7s cubic-bezier(.22, 1, .36, 1), transform .7s cubic-bezier(.22, 1, .36, 1);
}
.js-reveal .reveal.is-revealed { opacity: 1; transform: none; }

.pill {
  position: absolute;
  z-index: 10;
  display: none;
  align-items: center;
  gap: .5rem;
  border-radius: 9999px;
  border: 1px solid #e6e2d9;
  background: #fff;
  padding: .55rem 1rem;
  font-size: 12px;
  font-weight: 500;
  color: #3f3d38;
  box-shadow: 0 14px 34px -18px rgba(45, 40, 32, .5);
  animation: float 6s ease-in-out infinite;
}
.pill .dot { height: .4rem; width: .4rem; border-radius: 9999px; }
.pill-a { left: -8.5rem; top: 4rem; animation-delay: 0s; }
.pill-b { right: -7.5rem; top: 9.5rem; animation-delay: .9s; }
.pill-c { left: -7.5rem; bottom: 7rem; animation-delay: 1.8s; }
.pill-d { right: -8.5rem; bottom: 3.5rem; animation-delay: 2.6s; }

@media (min-width: 1180px) {
  .pill { display: flex; }
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-9px); }
}

.faq summary::-webkit-details-marker { display: none; }

/* Hero live demo */
.hero-progress-bar {
  width: 0%;
  animation-name: hero-progress;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}
.hero-frozen .hero-progress-bar { animation-play-state: paused; }
@keyframes hero-progress {
  from { width: 0%; }
  to { width: 100%; }
}

.hero-stagger {
  opacity: 0;
  animation: hero-stagger-in .5s cubic-bezier(.22, 1, .36, 1) both;
}
@keyframes hero-stagger-in {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.hero-confidence {
  width: 0%;
  animation: hero-confidence-fill .9s cubic-bezier(.22, 1, .36, 1) .1s forwards;
}
@keyframes hero-confidence-fill {
  to { width: 92%; }
}

.hero-caret {
  display: inline-block;
  margin-left: 1px;
  animation: hero-blink 1s step-end infinite;
}
@keyframes hero-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0; }
}

.fade-swap-enter-active, .fade-swap-leave-active { transition: opacity .32s ease, transform .32s ease; }
.fade-swap-enter-from { opacity: 0; transform: translateY(6px); }
.fade-swap-leave-to { opacity: 0; transform: translateY(-6px); }

@media (prefers-reduced-motion: reduce) {
  .js-reveal .reveal { opacity: 1; transform: none; transition: none; }
  .pill { animation: none; }
  .hero-progress-bar { animation: none; width: 100%; }
  .hero-stagger { animation: none; opacity: 1; }
  .hero-confidence { animation: none; width: 92%; }
  .hero-caret { animation: none; opacity: 0; }
  .fade-swap-enter-active, .fade-swap-leave-active { transition: none; }
}
</style>
