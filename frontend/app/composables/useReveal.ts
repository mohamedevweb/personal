/**
 * Progressive reveal for the launch page. Sections marked `data-reveal` fade up
 * as they enter the viewport.
 *
 * The hidden state is applied only once JS is running, so the page still reads
 * in full if the script never executes, and it is never applied at all when the
 * visitor has asked for reduced motion.
 *
 * Mark a block `data-reveal-now` when it is above the fold by construction — the
 * hero, in practice. The observer's root is inset by 10% and needs 12% of a
 * block in view, so on a short window the last block of a tall hero never
 * qualifies and its content stays hidden until the visitor scrolls. Content that
 * is meant to be visible on arrival must not depend on that.
 */
export function useReveal() {
  const root = ref<HTMLElement | null>(null)
  let observer: IntersectionObserver | null = null

  onMounted(() => {
    const el = root.value
    if (!el || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    const targets = el.querySelectorAll<HTMLElement>('[data-reveal]')
    if (!targets.length) return

    el.classList.add('js-reveal')

    if (!('IntersectionObserver' in window)) {
      targets.forEach(node => node.classList.add('is-revealed'))
      return
    }

    observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return
        entry.target.classList.add('is-revealed')
        observer?.unobserve(entry.target)
      })
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 })

    targets.forEach((node) => {
      if (node.dataset.revealNow !== undefined) {
        node.classList.add('is-revealed')
        return
      }
      observer!.observe(node)
    })
  })

  onUnmounted(() => observer?.disconnect())

  return { root }
}
