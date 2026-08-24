<script setup lang="ts">
/**
 * 01 — what Personal understood about you, in its own words.
 *
 * This is the app's Personal screen: the memory card at the top, then the
 * field grid underneath it. In the product the values start as "à compléter"
 * and fill in as the account is read — so here they do exactly that. The step
 * being illustrated is not a filled profile, it is the moment it fills, and a
 * grid that is already complete when you arrive shows the result while hiding
 * the claim.
 *
 * The cells land one after another, in reading order, with the count of posts
 * read climbing alongside them. Nothing about the timing is decorative: it is
 * the same order the app writes them in.
 */
const props = withDefaults(defineProps<{ active?: boolean }>(), { active: false })

const FIELDS = ['positioning', 'audience', 'niche', 'tone', 'topics', 'goals'] as const
const CELLS = FIELDS.length + 1
const STEP_MS = 190

/**
 * How many cells have been written. Server-side and under reduced motion the
 * grid is simply complete: the animation is the argument, but the content is
 * the point, and the content must never depend on JS or on motion.
 */
const written = ref(CELLS)
const posts = useCountUp(40, () => props.active, 1400)

let timer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  written.value = 0

  watch(() => props.active, (on) => {
    if (!on || written.value > 0) return

    timer = setInterval(() => {
      written.value += 1
      if (written.value >= CELLS && timer) clearInterval(timer)
    }, STEP_MS)
  }, { immediate: true })
})

onUnmounted(() => { if (timer) clearInterval(timer) })
</script>

<template>
  <LandingMockScreen :title="$t('landing.how.screens.personal')">
    <!-- The memory card. Same shape as the app: accent tile, eyebrow, the one
         line telling you the profile is yours to argue with. -->
    <div class="flex items-start gap-3.5 rounded-[14px] border border-[var(--b-line)] bg-[var(--b-surface)] p-4">
      <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[10px] bg-[var(--b-red-100)] text-[var(--b-red-700)]">
        <AppIcon name="user" :size="16" />
      </span>

      <div class="min-w-0 flex-1">
        <p class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.profile.label') }}</p>
        <p class="mt-2 text-[13px] leading-[1.5] text-[var(--b-stone)]">{{ $t('landing.how.profile.correct') }}</p>
      </div>

      <LandingMockAction
        class="hidden shrink-0 rounded-full border border-[var(--b-line)] bg-[var(--b-ivory)] px-3.5 py-2 text-[12.5px] transition-colors hover:border-[#d6cfc0] hover:bg-[var(--b-surface)] sm:block"
      >
        {{ $t('landing.how.profile.edit') }}
      </LandingMockAction>
    </div>

    <!-- The field grid, drawn the way the app draws it: one bordered card,
         hairlines between the cells, the label above the value. -->
    <dl class="mt-4 grid overflow-hidden rounded-[14px] border border-[var(--b-line)] bg-[var(--b-surface)] sm:grid-cols-2">
      <div
        v-for="(field, index) in FIELDS"
        :key="field"
        class="border-b border-[var(--b-line-soft)] p-4"
        :class="index % 2 === 0 ? 'sm:border-r sm:border-[var(--b-line-soft)]' : ''"
      >
        <dt class="b-mono text-[var(--b-stone)]">{{ $t(`landing.how.profile.${field}`) }}</dt>

        <!-- Before the read arrives, the cell holds its own height with a rule
             rather than collapsing: the grid must not jump as it fills. -->
        <dd class="relative mt-2.5 text-[14px] leading-[1.45] tracking-[-.01em]">
          <span
            class="block transition-all duration-500"
            :class="index < written ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-0'"
          >{{ $t(`landing.how.profile.${field}Value`) }}</span>

          <span
            class="b-scan absolute inset-x-0 top-[.45em] h-[7px] rounded-full transition-opacity duration-300"
            :class="index < written ? 'opacity-0' : 'opacity-100'"
            aria-hidden="true"
          />
        </dd>
      </div>

      <!-- The last read runs the full width in the app too: it is a sentence
           rather than a field. -->
      <div class="p-4 sm:col-span-2">
        <dt class="b-mono text-[var(--b-stone)]">{{ $t('landing.how.profile.strengths') }}</dt>
        <dd class="relative mt-2.5 text-[14px] leading-[1.45] tracking-[-.01em]">
          <span
            class="block transition-all duration-500"
            :class="FIELDS.length < written ? 'translate-y-0 opacity-100' : 'translate-y-1 opacity-0'"
          >{{ $t('landing.how.profile.strengthsValue') }}</span>

          <span
            class="b-scan absolute inset-x-0 top-[.45em] h-[7px] rounded-full transition-opacity duration-300"
            :class="FIELDS.length < written ? 'opacity-0' : 'opacity-100'"
            aria-hidden="true"
          />
        </dd>
      </div>
    </dl>

    <p class="b-mono mt-4 flex items-center gap-2.5 text-[var(--b-stone)]">
      <span class="b-live" aria-hidden="true" />
      {{ $t('landing.how.profile.source', { count: posts }) }}
    </p>
  </LandingMockScreen>
</template>
