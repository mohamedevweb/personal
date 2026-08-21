<script setup lang="ts">
/**
 * The field: the posts Personal read overnight, drifting toward you out of the
 * dark. Each tile is one post seen in perspective, and the handful burning red
 * are the outliers — the reason the product exists, moving through the frame
 * at the same speed as everything else it had to read to find them.
 *
 * It is a 2D canvas rather than a WebGL scene on purpose. The whole effect is
 * ~120 rounded rectangles on a projection, which canvas draws for a fraction
 * of the cost and none of the payload of a 3D runtime.
 *
 * It draws nothing until it is on screen, stops when it leaves, and renders a
 * single still frame when the visitor has asked for less motion.
 */
type Post = {
  x: number
  y: number
  z: number
  width: number
  outlier: boolean
}

// The depth slab the field lives in. Tiles are born at Z_FAR and retired once
// they pass Z_NEAR, which is just in front of the picture plane.
const Z_NEAR = 0.42
const Z_FAR = 3.2
const DRIFT = 0.16
const SPREAD = 1.5
// One post in twelve beats its own creator's average. That is the product's
// claim, so it is also the mix in the field.
const OUTLIER_RATE = 1 / 12

const canvas = ref<HTMLCanvasElement | null>(null)

let context: CanvasRenderingContext2D | null = null
let posts: Post[] = []
let frame = 0
let last = 0
let width = 0
let height = 0
let ratio = 1
const pointer = { x: 0, y: 0 }
const eased = { x: 0, y: 0 }

function random(min: number, max: number) {
  return min + Math.random() * (max - min)
}

/** A post placed somewhere in the slab, or respawned at the back of it. */
function seed(post: Post | null, z: number): Post {
  const next = post ?? ({} as Post)
  next.x = random(-SPREAD, SPREAD)
  next.y = random(-SPREAD * 0.62, SPREAD * 0.62)
  next.z = z
  next.width = random(0.085, 0.155)
  next.outlier = Math.random() < OUTLIER_RATE
  return next
}

function resize() {
  const element = canvas.value
  if (!element) return

  const rect = element.getBoundingClientRect()
  if (!rect.width || !rect.height) return

  ratio = Math.min(window.devicePixelRatio || 1, 2)
  width = rect.width
  height = rect.height
  element.width = Math.round(width * ratio)
  element.height = Math.round(height * ratio)
  context?.setTransform(ratio, 0, 0, ratio, 0, 0)

  // Density follows area rather than being a fixed count, so a phone does not
  // pay for a desktop's field and a wide screen does not look empty.
  const count = Math.round(Math.min(150, Math.max(46, (width * height) / 13500)))
  if (posts.length > count) posts.length = count
  while (posts.length < count) posts.push(seed(null, random(Z_NEAR, Z_FAR)))
}

function tile(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, radius: number) {
  ctx.beginPath()
  if (typeof ctx.roundRect === 'function') ctx.roundRect(x, y, w, h, radius)
  else ctx.rect(x, y, w, h)
}

function draw(delta: number) {
  const ctx = context
  if (!ctx) return

  eased.x += (pointer.x - eased.x) * 0.05
  eased.y += (pointer.y - eased.y) * 0.05

  ctx.clearRect(0, 0, width, height)

  const scale = Math.max(width, height) * 0.52
  const centreX = width / 2
  // The vanishing point sits above centre, so the field opens downward into
  // the light pooled at the bottom of the stage.
  const centreY = height * 0.42

  for (const post of posts) {
    post.z -= DRIFT * delta
    if (post.z <= Z_NEAR) seed(post, Z_FAR)
  }

  // Painter's order: the back of the slab first, so nearer posts occlude it.
  posts.sort((a, b) => b.z - a.z)

  for (const post of posts) {
    const k = scale / post.z
    const x = centreX + (post.x + eased.x * 0.11) * k
    const y = centreY + (post.y + eased.y * 0.07) * k
    const w = post.width * k
    const h = w * 1.24

    if (x + w < -40 || x - w > width + 40 || y + h < -40 || y - h > height + 40) continue

    // Posts fade up as they arrive and dim again as they pass, so nothing ever
    // pops in or out at an edge of the slab.
    const arriving = Math.min(1, (Z_FAR - post.z) / 0.9)
    const leaving = Math.min(1, (post.z - Z_NEAR) / 0.7)
    const alpha = Math.min(arriving, leaving) * 0.85
    if (alpha <= 0.01) continue

    tile(ctx, x - w / 2, y - h / 2, w, h, Math.min(w, h) * 0.16)

    if (post.outlier) {
      // The one fact the field is here to show. It gets the signature, a lit
      // edge, and the only glow on the stage.
      ctx.fillStyle = `rgba(224, 79, 54, ${alpha * 0.3})`
      ctx.fill()
      ctx.strokeStyle = `rgba(255, 106, 77, ${alpha * 0.85})`
      ctx.lineWidth = 1.1
      ctx.stroke()

      ctx.save()
      ctx.globalAlpha = alpha * 0.5
      ctx.shadowColor = 'rgba(224, 79, 54, 1)'
      ctx.shadowBlur = Math.min(46, w * 0.9)
      ctx.fillStyle = 'rgba(224, 79, 54, .5)'
      ctx.fill()
      ctx.restore()
    } else {
      ctx.fillStyle = `rgba(255, 255, 255, ${alpha * 0.045})`
      ctx.fill()
      ctx.strokeStyle = `rgba(255, 255, 255, ${alpha * 0.14})`
      ctx.lineWidth = 1
      ctx.stroke()
    }
  }
}

function loop(now: number) {
  frame = requestAnimationFrame(loop)
  const delta = last ? Math.min((now - last) / 1000, 0.05) : 0.016
  last = now
  draw(delta)
}

function start() {
  if (frame) return
  last = 0
  frame = requestAnimationFrame(loop)
}

function stop() {
  if (!frame) return
  cancelAnimationFrame(frame)
  frame = 0
}

function onMove(event: PointerEvent) {
  if (event.pointerType === 'touch') return
  pointer.x = (event.clientX / window.innerWidth) * 2 - 1
  pointer.y = (event.clientY / window.innerHeight) * 2 - 1
}

let observer: IntersectionObserver | null = null
let resizeObserver: ResizeObserver | null = null

onMounted(() => {
  const element = canvas.value
  if (!element) return

  context = element.getContext('2d')
  if (!context) return

  resize()

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    draw(0)
    return
  }

  resizeObserver = new ResizeObserver(() => resize())
  resizeObserver.observe(element)

  observer = new IntersectionObserver(([entry]) => {
    if (entry?.isIntersecting) start()
    else stop()
  }, { threshold: 0 })
  observer.observe(element)

  window.addEventListener('pointermove', onMove, { passive: true })
})

onUnmounted(() => {
  stop()
  observer?.disconnect()
  resizeObserver?.disconnect()
  window.removeEventListener('pointermove', onMove)
})
</script>

<template>
  <canvas ref="canvas" class="block h-full w-full" aria-hidden="true" />
</template>
