<script setup lang="ts">
const { toasts, remove } = useToast()
</script>

<template>
  <div class="pointer-events-none fixed inset-x-4 top-4 z-[100] flex flex-col items-end gap-3 md:left-auto md:right-6 md:top-6 md:w-[390px]" :aria-label="$t('toast.regionLabel')">
    <TransitionGroup name="toast">
      <article
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto relative w-full overflow-hidden rounded-[18px] border border-[var(--line)] bg-[var(--surface)] p-3.5 shadow-[0_20px_55px_rgba(23,23,21,.14)]"
        :role="toast.kind === 'error' ? 'alert' : 'status'"
      >
        <div class="flex items-start gap-3">
          <span
            class="grid h-10 w-10 shrink-0 place-items-center rounded-[12px]"
            :class="toast.kind === 'error' ? 'bg-[var(--danger-soft)] text-[var(--danger)]' : 'bg-[var(--positive-soft)] text-[var(--positive)]'"
          >
            <AppIcon :name="toast.kind === 'error' ? 'alert' : 'check'" :size="18" :stroke-width="1.9" />
          </span>

          <div class="min-w-0 flex-1 pt-0.5">
            <p class="font-serif text-[19px] leading-5 tracking-[-.015em]">
              {{ $t(toast.kind === 'error' ? 'toast.errorTitle' : 'toast.successTitle') }}
            </p>
            <p class="mt-1.5 text-[13px] leading-5 text-[var(--muted)]">{{ toast.message }}</p>
          </div>

          <button
            type="button"
            class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-[var(--faint)] transition hover:bg-[var(--paper)] hover:text-[var(--ink)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)]"
            :aria-label="$t('common.close')"
            @click="remove(toast.id)"
          >
            <AppIcon name="close" :size="15" />
          </button>
        </div>

        <span
          class="toast-timer absolute inset-x-0 bottom-0 h-0.5 origin-left"
          :class="toast.kind === 'error' ? 'bg-[var(--danger)]' : 'bg-[var(--positive)]'"
          :style="{ animationDuration: `${toast.duration}ms` }"
          aria-hidden="true"
        />
      </article>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active { transition: opacity .24s ease, transform .3s cubic-bezier(.22, 1, .36, 1); }
.toast-enter-from,
.toast-leave-to { opacity: 0; transform: translateY(-8px) scale(.98); }
.toast-move { transition: transform .3s cubic-bezier(.22, 1, .36, 1); }
.toast-timer { animation-name: toast-timer; animation-timing-function: linear; animation-fill-mode: forwards; }
@keyframes toast-timer { to { transform: scaleX(0); } }

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active,
  .toast-move { transition: none; }
  .toast-timer { animation: none; }
}
</style>
