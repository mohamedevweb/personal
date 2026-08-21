/**
 * Records the product walkthrough that goes under the launch post.
 *
 * It drives the real local app with the seeded demo creator — no mockups, no
 * after-effects — and writes an MP4. The pacing is deliberately slow: every
 * screen holds long enough to be read on a phone, which is where most of the
 * launch traffic will watch it.
 *
 * Prerequisites: `docker compose up`, `php artisan db:seed`, and
 *   npm install playwright && npx playwright install chromium
 *
 *   node docs/marketing/record-demo.mjs
 *
 * The result is silent on purpose. The voiceover and the captions are written in
 * demo-video-script.md and belong on the timeline afterwards.
 */
import { chromium } from 'playwright'
import { mkdir, rm, readdir, rename } from 'node:fs/promises'
import { execFile } from 'node:child_process'
import { promisify } from 'node:util'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const run = promisify(execFile)

const APP = process.env.APP_URL ?? 'http://localhost:3000'
const API = process.env.API_URL ?? 'http://localhost:8000'
const REMIX_ID = process.env.REMIX_ID
const CREDENTIALS = {
  email: process.env.DEMO_EMAIL ?? 'creator@personal.local',
  password: process.env.DEMO_PASSWORD ?? 'personal'
}

const HERE = dirname(fileURLToPath(import.meta.url))
const OUT = process.env.OUT_DIR ?? HERE
const RAW = join(OUT, '.video-raw')
const SIZE = { width: 1440, height: 900 }

/**
 * Playwright's bundled ffmpeg is compiled down to VP8/WebM only — it has no
 * H.264 encoder — so the MP4 pass needs a real ffmpeg on PATH. The WebM is
 * always produced; the MP4 is the social-platform convenience on top of it.
 */
async function h264() {
  try {
    const { stdout } = await run('ffmpeg', ['-hide_banner', '-encoders'])
    return stdout.includes('libx264') ? 'ffmpeg' : null
  } catch {
    return null
  }
}

const HIDE_DEV_CHROME = `
  #nuxt-devtools-container,
  #vue-tracer-overlay,
  nuxt-devtools-inspect-panel { display: none !important; }
`

/**
 * Playwright's recorder does not draw the pointer, and a walkthrough where
 * things happen with nothing moving reads as a slideshow. This paints a cursor
 * that follows the synthesized mouse and pulses on click.
 */
const CURSOR = `
  const dot = document.createElement('div')
  dot.id = '__demo_cursor'
  dot.style.cssText = [
    'position:fixed', 'z-index:2147483647', 'left:0', 'top:0',
    'width:22px', 'height:22px', 'margin:-11px 0 0 -11px', 'border-radius:999px',
    'background:rgba(20,18,16,.82)', 'box-shadow:0 0 0 6px rgba(20,18,16,.14)',
    'pointer-events:none', 'transition:transform .12s ease-out', 'opacity:0'
  ].join(';')
  document.documentElement.appendChild(dot)
  addEventListener('mousemove', e => {
    dot.style.opacity = '1'
    dot.style.left = e.clientX + 'px'
    dot.style.top = e.clientY + 'px'
  }, true)
  addEventListener('mousedown', () => { dot.style.transform = 'scale(.7)' }, true)
  addEventListener('mouseup', () => { dot.style.transform = 'scale(1)' }, true)
`

async function token() {
  const response = await fetch(`${API}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(CREDENTIALS)
  })
  if (!response.ok) throw new Error(`Sign-in failed (${response.status}). Is the API seeded?`)
  return (await response.json()).token
}

async function firstContentId(value) {
  const feed = await fetch(`${API}/api/feed`, {
    headers: { Accept: 'application/json', Authorization: `Bearer ${value}` }
  }).then(r => r.json())
  if (!feed.items?.length) throw new Error('The feed is empty. Run `php artisan db:seed`.')
  return feed.items[0].id
}

/** A eased scroll, because a jumped scrollTop looks like a cut in a recording. */
async function glide(page, distance, ms = 2200) {
  await page.evaluate(([distance, ms]) => new Promise(resolve => {
    const start = window.scrollY
    const began = performance.now()
    const step = now => {
      const t = Math.min(1, (now - began) / ms)
      const eased = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2
      window.scrollTo(0, start + distance * eased)
      t < 1 ? requestAnimationFrame(step) : resolve()
    }
    requestAnimationFrame(step)
  }), [distance, ms])
}

async function visit(page, path, settle = 1600) {
  await page.goto(APP + path, { waitUntil: 'networkidle' })
  await page.addStyleTag({ content: HIDE_DEV_CHROME })
  await page.evaluate(CURSOR)
  await page.waitForTimeout(settle)
}

async function record() {
  const value = await token()
  const contentId = await firstContentId(value)
  const remixId = REMIX_ID ?? await (async () => {
    const generated = await fetch(`${API}/api/content/${contentId}/remix`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${value}` },
      body: JSON.stringify({ format: 'reel' })
    })
    if (!generated.ok) throw new Error(`Remix generation failed (${generated.status}). Set REMIX_ID to an existing draft.`)
    return (await generated.json()).remix.id
  })()

  const browser = await chromium.launch()
  const context = await browser.newContext({
    viewport: SIZE,
    deviceScaleFactor: 1,
    recordVideo: { dir: RAW, size: SIZE }
  })
  await context.addCookies([
    { name: 'personal_token', value, url: APP },
    { name: 'personal-onboarding-skipped', value: 'true', url: APP },
    { name: 'personal_lang', value: 'en', url: APP },
    { name: 'i18n_redirected', value: 'en', url: APP }
  ])

  const page = await context.newPage()

  // 1 — the promise, on the landing page.
  await visit(page, '/', 2600)
  await glide(page, 620, 2600)
  await page.waitForTimeout(1600)

  // 2 — the feed: evidence before ideas.
  await visit(page, '/feed', 2600)
  await page.mouse.move(720, 520, { steps: 28 })
  await page.waitForTimeout(1400)
  await glide(page, 520, 2400)
  await page.waitForTimeout(1800)

  // 3 — why a post is on the feed at all.
  await visit(page, `/content/${contentId}`, 2400)
  await glide(page, 700, 2600)
  await page.waitForTimeout(1800)

  // 4 — the payoff: the same pattern, rewritten from the creator's own moment.
  await visit(page, `/remix/${remixId}`, 3000)
  await page.mouse.move(940, 690, { steps: 30 })
  await page.waitForTimeout(1600)
  await glide(page, 620, 2600)
  await page.waitForTimeout(2200)

  // 5 — where that material comes from.
  await visit(page, '/moments', 2600)
  await glide(page, 420, 2200)
  await page.waitForTimeout(1600)

  // 6 — the memory that keeps every draft sounding like one person.
  await visit(page, '/personal', 2600)
  await page.waitForTimeout(1400)

  // 7 — close on the invitation.
  await visit(page, '/create', 2600)
  await page.waitForTimeout(2000)

  await context.close()
  await browser.close()
}

async function encode() {
  const [file] = (await readdir(RAW)).filter(name => name.endsWith('.webm'))
  if (!file) throw new Error('No recording was produced.')

  const webm = join(OUT, 'personal-demo.webm')
  const mp4 = join(OUT, 'personal-demo.mp4')
  await rename(join(RAW, file), webm)
  await rm(RAW, { recursive: true, force: true })

  const encoder = await h264()
  if (!encoder) return { webm, mp4: null }

  // yuv420p and even dimensions are what every social platform re-encodes from
  // without turning the type to mush.
  await run(encoder, [
    '-y', '-i', webm,
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '20',
    '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
    '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2',
    mp4
  ])

  return { webm, mp4 }
}

await mkdir(OUT, { recursive: true })
await rm(RAW, { recursive: true, force: true })
await record()
const { webm, mp4 } = await encode()
console.log(`\nRecorded:\n  ${webm}`)
console.log(mp4
  ? `  ${mp4}`
  : '\nNo H.264 encoder found, so only the WebM was written.\nRun `brew install ffmpeg` and re-run to also get personal-demo.mp4 for social platforms.')
