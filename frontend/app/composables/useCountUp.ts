/**
 * A number that counts itself up the first time its block is read.
 *
 * The launch page states measured things — a fit score, an outlier ratio, a
 * count of posts read — and a figure that lands already finished reads as
 * printed. Counting it up says the app worked it out. It runs once: coming
 * back to a section that has already been read must not restart the arithmetic.
 *
 * Reduced motion, and the server, both get the final value immediately. There
 * is nothing to see in the intermediate frames but the claim.
 */
export function useCountUp(target: MaybeRefOrGetter<number>, active: MaybeRefOrGetter<boolean>, duration = 1100) {
  const final = () => toValue(target)
  const value = ref(final())
  let frame = 0
  let settle: ReturnType<typeof setTimeout> | null = null
  let done = false

  onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    value.value = 0

    watch(() => toValue(active), (on) => {
      if (!on || done) return
      done = true

      const to = final()
      const start = performance.now()

      const step = (now: number) => {
        // Ease out: the count arrives fast and settles, the way a readout does.
        const t = Math.min(1, (now - start) / duration)
        value.value = Math.round(to * (1 - Math.pow(1 - t, 3)))
        if (t < 1) frame = requestAnimationFrame(step)
      }

      frame = requestAnimationFrame(step)

      // A tab that never paints never gets a frame, and a readout frozen
      // half-way through counting is worse than one that never counted. The
      // value lands on its own once the animation's time is up, whatever the
      // frames did.
      settle = setTimeout(() => { value.value = to }, duration + 80)
    }, { immediate: true })
  })

  onUnmounted(() => {
    cancelAnimationFrame(frame)
    if (settle) clearTimeout(settle)
  })

  return value
}
