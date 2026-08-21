<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()

useHead({
  title: () => t('legal.terms.head.title'),
  meta: [{ name: 'description', content: () => t('legal.terms.head.description') }]
})

/**
 * Same contract as the privacy policy: the page describes the document's shape
 * and the locale files hold every word, so a clause can never exist in one
 * language and not the other.
 *
 * `p` — paragraphs before the list. `items` — term/detail rows.
 * `outro` — paragraphs after the list. `note` — the closing aside.
 * `link` — a route the section sends the reader to, under its text.
 */
type Section = { id: string, p?: string[], items?: string[], outro?: string[], note?: boolean, link?: string }

const SECTIONS: Section[] = [
  { id: 'summary', items: ['free', 'yours', 'author', 'noPost', 'beta', 'leave'] },
  { id: 'object', p: ['accept', 'who', 'together'] },
  { id: 'publisher', p: ['who', 'director', 'host'] },
  {
    id: 'service',
    p: ['intro'],
    items: ['feed', 'moments', 'remix', 'chat', 'profile', 'instagram'],
    note: true
  },
  { id: 'account', p: ['intro'], items: ['age', 'truth', 'security', 'sharing'] },
  { id: 'price', p: ['free', 'later', 'costs'] },
  {
    id: 'instagram',
    p: ['optional', 'scope'],
    items: ['meta', 'eligible', 'revoke', 'beyond'],
    outro: ['publish']
  },
  { id: 'ai', p: ['what', 'review', 'author', 'noResult', 'quota'] },
  { id: 'yourContent', p: ['intro'], items: ['licence', 'noTraining', 'warrant', 'backup'] },
  { id: 'discovery', p: ['what', 'rule', 'takedown'] },
  {
    id: 'rules',
    p: ['intro'],
    items: ['illegal', 'minors', 'deception', 'spam', 'technical', 'competing'],
    note: true
  },
  { id: 'ourContent', p: ['ours', 'output', 'feedback'] },
  { id: 'availability', p: ['asis', 'maintenance', 'evolve', 'deps'] },
  { id: 'suspension', p: ['intro'], items: ['you', 'us', 'effect', 'shutdown'] },
  { id: 'liability', p: ['ours', 'not', 'cap', 'yours'] },
  { id: 'thirdParty', p: ['list', 'links'] },
  { id: 'privacy', p: ['policy', 'link'], link: '/privacy' },
  { id: 'changes', p: ['how', 'notice'] },
  { id: 'law', p: ['law', 'first', 'mediation', 'court'] },
  { id: 'contact', p: ['mail', 'privacy', 'post'] }
]

const numbered = SECTIONS.map((section, index) => ({
  ...section,
  number: String(index + 1).padStart(2, '0')
}))

const key = (id: string, suffix: string) => `legal.terms.s.${id}.${suffix}`
</script>

<template>
  <div class="min-h-screen bg-[var(--b-ivory)] text-[var(--b-black)] antialiased">
    <LandingNav />

    <main>
      <header class="px-6 pb-14 pt-16 md:px-10 md:pb-20 md:pt-24">
        <div class="mx-auto max-w-[1200px]">
          <p class="b-eyebrow">{{ $t('legal.terms.hero.eyebrow') }}</p>
          <h1 class="mt-6 max-w-3xl font-display text-[40px] leading-[1.04] tracking-[-.03em] md:text-[64px]">
            {{ $t('legal.terms.hero.title') }}
          </h1>
          <p class="mt-7 max-w-2xl text-[17px] leading-[1.7] text-[var(--b-stone)] md:text-[19px]">
            {{ $t('legal.terms.hero.lede') }}
          </p>
          <p class="mt-8 text-[13px] text-[var(--b-stone)]">{{ $t('legal.terms.hero.updated') }}</p>
        </div>
      </header>

      <div class="border-t border-[var(--b-line)] px-6 py-14 md:px-10 md:py-20">
        <div class="mx-auto grid max-w-[1200px] gap-14 md:grid-cols-[minmax(0,260px)_1fr] md:gap-20">
          <!-- The document is long by nature, so the reader always keeps a map of
               it within reach on desktop. -->
          <nav class="md:sticky md:top-28 md:self-start" :aria-label="$t('legal.terms.toc.label')">
            <p class="b-eyebrow">{{ $t('legal.terms.toc.label') }}</p>
            <ol class="mt-5 space-y-2.5">
              <li v-for="section in numbered" :key="section.id">
                <a
                  :href="`#${section.id}`"
                  class="b-focus flex gap-3 text-[14px] leading-[1.5] text-[var(--b-stone)] transition-colors hover:text-[var(--b-black)]"
                >
                  <span class="pt-[2px] text-[11px] tabular-nums text-[#b4ac9d]">{{ section.number }}</span>
                  {{ $t(key(section.id, 'title')) }}
                </a>
              </li>
            </ol>
          </nav>

          <div class="max-w-[68ch]">
            <section
              v-for="section in numbered"
              :id="section.id"
              :key="section.id"
              class="scroll-mt-28 border-b border-[var(--b-line)] pb-12 pt-12 first:pt-0 last:border-0"
            >
              <p class="b-eyebrow">{{ section.number }}</p>
              <h2 class="mt-4 font-display text-[28px] leading-[1.12] tracking-[-.025em] md:text-[34px]">
                {{ $t(key(section.id, 'title')) }}
              </h2>

              <p
                v-for="paragraph in section.p"
                :key="paragraph"
                class="mt-5 text-[15.5px] leading-[1.75] text-[var(--b-stone)]"
              >
                {{ $t(key(section.id, `p.${paragraph}`)) }}
              </p>

              <dl v-if="section.items" class="mt-7 space-y-5 border-t border-[var(--b-line-soft)] pt-7">
                <div v-for="item in section.items" :key="item">
                  <dt class="text-[15.5px] font-medium leading-[1.5] tracking-[-.01em]">
                    {{ $t(key(section.id, `items.${item}.term`)) }}
                  </dt>
                  <dd class="mt-1.5 text-[15.5px] leading-[1.75] text-[var(--b-stone)]">
                    {{ $t(key(section.id, `items.${item}.detail`)) }}
                  </dd>
                </div>
              </dl>

              <p
                v-for="paragraph in section.outro"
                :key="paragraph"
                class="mt-5 text-[15.5px] leading-[1.75] text-[var(--b-stone)]"
              >
                {{ $t(key(section.id, `outro.${paragraph}`)) }}
              </p>

              <p v-if="section.note" class="mt-6 text-[14px] leading-[1.7] text-[#8b8375]">
                {{ $t(key(section.id, 'note')) }}
              </p>

              <div v-if="section.link" class="mt-7">
                <LandingButtonLink :to="section.link" variant="ghost">
                  {{ $t(key(section.id, 'cta')) }}
                </LandingButtonLink>
              </div>
            </section>

            <div class="pt-12">
              <LandingButtonLink to="/" variant="ghost">
                {{ $t('legal.terms.hero.back') }}
              </LandingButtonLink>
            </div>
          </div>
        </div>
      </div>
    </main>

    <LandingSiteFooter />
  </div>
</template>
