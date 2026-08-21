/**
 * Captures the marketing screenshot set from the local stack.
 *
 * The launch post, the App Store-style listing and the Meta App Review
 * submission all need the same thing: the real product, at retina resolution,
 * with demo data rather than a personal account. This drives the local app with
 * the seeded demo creator and writes every shot to ./screenshots.
 *
 * Prerequisites: `docker compose up` (frontend on :3000, API on :8000) and
 * `php artisan db:seed` so the demo creator has a populated feed.
 *
 *   npm install playwright && npx playwright install chromium
 *   node docs/marketing/capture-screenshots.mjs
 */
import { chromium } from 'playwright'
import { mkdir } from 'node:fs/promises'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const APP = process.env.APP_URL ?? 'http://localhost:3000'
const API = process.env.API_URL ?? 'http://localhost:8000'
const CREDENTIALS = {
  email: process.env.DEMO_EMAIL ?? 'creator@personal.local',
  password: process.env.DEMO_PASSWORD ?? 'personal'
}

const OUT = process.env.OUT_DIR ?? join(dirname(fileURLToPath(import.meta.url)), 'screenshots')

/** Desktop shots run at 2x, so a 1440-wide frame exports at 2880 for print and retina. */
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }

function desktopShots({ contentId, remixId }) {
  return [
    { name: '01-landing', path: '/', wait: 1200 },
    { name: '02-feed', path: '/feed', wait: 2500 },
    { name: '03-content', path: `/content/${contentId}`, wait: 2000 },
    { name: '04-remix', path: `/remix/${remixId}`, wait: 2000 },
    { name: '05-moments', path: '/moments', wait: 1500 },
    { name: '06-create', path: '/create', wait: 1800 },
    { name: '07-personal', path: '/personal', wait: 1500 },
    { name: '08-saved', path: '/saved', wait: 1500 },
    { name: '09-terms', path: '/terms', wait: 1200 },
    { name: '10-privacy', path: '/privacy', wait: 1200 }
  ]
}

function mobileShots({ remixId }) {
  return [
    { name: '20-mobile-feed', path: '/feed', wait: 2500 },
    { name: '21-mobile-remix', path: `/remix/${remixId}`, wait: 2000 },
    { name: '22-mobile-moments', path: '/moments', wait: 1500 },
    { name: '23-mobile-create', path: '/create', wait: 1800 }
  ]
}

async function token() {
  const response = await fetch(`${API}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(CREDENTIALS)
  })
  if (!response.ok) throw new Error(`Sign-in failed (${response.status}). Is the API seeded?`)
  return (await response.json()).token
}

/**
 * The onboarding gate and the language toggle are both cookie-driven, so seeding
 * them up front keeps every shot in English and out of the connect-Instagram
 * screen without touching the UI.
 */
function cookies(value) {
  const url = APP
  return [
    { name: 'personal_token', value, url },
    { name: 'personal-onboarding-skipped', value: 'true', url },
    { name: 'personal_lang', value: 'en', url },
    { name: 'i18n_redirected', value: 'en', url }
  ]
}

/**
 * The dev server ships the Nuxt devtools anchor and the tracer overlay into the
 * page, and both land in the bottom of every frame. Marketing shots have to be
 * of the product alone, so they are hidden before each capture.
 */
const HIDE_DEV_CHROME = `
  #nuxt-devtools-container,
  #vue-tracer-overlay,
  nuxt-devtools-inspect-panel { display: none !important; }
`

/**
 * The remix screen is the shot that actually sells the product, so it gets a
 * freshly generated draft when the AI provider is reachable. When it is not —
 * an expired key locally, no budget in CI — REMIX_ID falls back to a draft that
 * already exists, and the run says so rather than silently skipping the shot.
 */
async function subjects(value) {
  const feed = await fetch(`${API}/api/feed`, {
    headers: { Accept: 'application/json', Authorization: `Bearer ${value}` }
  }).then(r => r.json())

  const contentId = feed.items[0]?.id
  if (!contentId) throw new Error('The feed is empty. Run `php artisan db:seed` against the local API.')

  const generated = await fetch(`${API}/api/content/${contentId}/remix`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${value}` },
    body: JSON.stringify({ format: 'reel' })
  })

  if (generated.ok) return { contentId, remixId: (await generated.json()).remix.id }

  const fallback = process.env.REMIX_ID
  if (!fallback) throw new Error(`Remix generation failed (${generated.status}) and REMIX_ID is not set.`)
  console.warn(`  ! generation failed (${generated.status}); using existing remix ${fallback}`)
  return { contentId, remixId: fallback }
}

async function capture(context, shots, suffix) {
  const page = await context.newPage()
  for (const shot of shots) {
    await page.goto(APP + shot.path, { waitUntil: 'networkidle' })
    await page.addStyleTag({ content: HIDE_DEV_CHROME })
    await page.waitForTimeout(shot.wait ?? 1500)
    // Remote thumbnails decode late; without this the first card can export grey.
    await page.evaluate(() => Promise.all(
      [...document.images].filter(i => !i.complete).map(i => new Promise(r => { i.onload = i.onerror = r }))
    ))
    const file = join(OUT, `${shot.name}.png`)
    await page.screenshot({ path: file, fullPage: shot.fullPage ?? false })
    console.log(`  ${shot.name}${suffix} → ${file}`)
  }
  await page.close()
}

const browser = await chromium.launch()
try {
  await mkdir(OUT, { recursive: true })
  const value = await token()
  const targets = await subjects(value)

  console.log('Desktop (1440×900 @2x):')
  const desktop = await browser.newContext({ viewport: DESKTOP, deviceScaleFactor: 2 })
  await desktop.addCookies(cookies(value))
  await capture(desktop, desktopShots(targets), '')
  await desktop.close()

  console.log('Mobile (390×844 @3x):')
  const mobile = await browser.newContext({ viewport: MOBILE, deviceScaleFactor: 3, isMobile: true, hasTouch: true })
  await mobile.addCookies(cookies(value))
  await capture(mobile, mobileShots(targets), '')
  await mobile.close()
} finally {
  await browser.close()
}
console.log(`\nDone. ${OUT}`)
