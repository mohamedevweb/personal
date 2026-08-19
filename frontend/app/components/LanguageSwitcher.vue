<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'light' | 'dark' }>(), { variant: 'light' })

const { locale, setLocale, locales } = useI18n()

const options = computed(() => (locales.value as { code: string, name: string }[]))

function select(code: string) {
  if (code !== locale.value) setLocale(code as 'en' | 'fr')
}
</script>

<template>
  <div
    class="inline-flex items-center rounded-full border p-0.5 text-[11px] font-medium"
    :class="variant === 'dark' ? 'border-white/15 bg-white/5' : 'border-[var(--line)] bg-[var(--surface)]'"
    role="group"
    :aria-label="$t('common.language')"
  >
    <button
      v-for="option in options"
      :key="option.code"
      type="button"
      class="rounded-full px-2.5 py-1 uppercase tracking-[.06em] transition"
      :class="locale === option.code
        ? (variant === 'dark' ? 'bg-white text-[var(--ink)]' : 'bg-[var(--ink)] text-white')
        : (variant === 'dark' ? 'text-white/55 hover:text-white' : 'text-[var(--muted)] hover:text-[var(--ink)]')"
      :aria-pressed="locale === option.code"
      @click="select(option.code)"
    >
      {{ option.code }}
    </button>
  </div>
</template>
