# AGENTS.md

Instructions for AI coding agents working on **Personal** (Nuxt 3 + Laravel 12 monorepo).

The repository is the source of truth. Read the files you are about to touch, and the files
next to them, before writing anything. When this document and the code disagree, follow the
code and mention the discrepancy.

## Layout

| Path | What lives there |
| --- | --- |
| `backend/` | Laravel 12 REST API (PHP 8.2+), Sanctum tokens, PostgreSQL, queue jobs |
| `frontend/` | Nuxt 3 app (`srcDir: app/`), Vue 3 `<script setup>`, TypeScript, Tailwind |
| `docker-compose.yml` | postgres, app (php-fpm), web (nginx), queue, scheduler, frontend |
| `README.md` | Product overview, env setup, Instagram/Claude configuration |

Read `README.md` first for domain context: the feed, remixes, life moments and the
Instagram/Claude boundaries are described there.

## General principles

- Keep it simple, clean and production-ready. Do not over-engineer.
- Prefer an existing pattern over a new abstraction. This codebase has few layers on purpose.
- Reuse existing components, composables, services, models and helpers before writing new ones.
- Never duplicate business logic. Backend owns domain rules; the frontend renders and orchestrates.
- Add no dependency unless the task cannot reasonably be done without it.
- When modifying existing behaviour, change only what the task requires — no drive-by refactors,
  no reformatting untouched code.
- Preserve backward compatibility unless the request explicitly requires a breaking change.
- Write code the next developer can read in one pass. Comments explain *why*, not *what*
  (see `AppServiceProvider::boot()` and `AuthController::logout()` for the house style).

## Backend — Laravel 12 / PHP 8.2

Follow Laravel conventions and PSR-12; Pint (`laravel` preset, no `pint.json`) is the formatter.

### Structure that already exists

- `app/Http/Controllers` — thin controllers only.
- `app/Services` — all business logic. `App\Services\Instagram` is the Meta API boundary.
- `app/Models` — Eloquent models.
- `app/Jobs` — queued work (`SyncInstagramAccount`).
- `app/Exceptions` — domain exceptions rendered to JSON in `bootstrap/app.php`.

There is **no** `app/Http/Requests`, `app/Policies` or `app/Actions` directory, and no API
Resources. That is deliberate for the current size of the project. Do not introduce those
layers for a single endpoint; introduce one only when a rule set or authorization check is
genuinely shared across several controllers, and say so in your summary when you do.

### Controllers

- Keep them thin: validate → resolve the owned record → delegate to a service → return JSON.
- Inject services as **method parameters** (`public function show(Request $request, ContentPost $content, ContentPostView $view)`), matching `ContentController`, `FeedController`, `MomentController`.
- Single-action controllers use `__invoke` (`FeedController`, `OpportunityController`, `SavedContentController`).
- Always declare return types: `JsonResponse`, or `Response` with `response()->noContent()`.
- Use `Symfony\Component\HttpFoundation\Response` constants for non-200 statuses, as in
  `AuthController` and `InstagramConnectionController`.

### Validation

- Inline `$request->validate([...])` is the convention. Rules are arrays of strings
  (`['required', 'string', 'max:3000']`), never pipe-delimited.
- When one controller validates the same shape twice, extract a private helper on the
  controller — see `MomentController::validated(Request $request, bool $partial = false)`.
- Validate array payloads element-wise (`'topics' => ['sometimes', 'array', 'max:12']`,
  `'topics.*' => ['string', 'max:80']`), as in `ProfileController::update()`.
- Never trust frontend data. Never pass `$request->all()` or unvalidated input into a model.

### Models and mass assignment

- Domain models use `protected $guarded = []`. **This means only validated arrays may be
  written.** `create()`/`update()` must receive `$request->validate()` output or values the
  server computed itself.
- Secrets are excluded with `protected $hidden` (`InstagramAccount::$hidden = ['access_token']`,
  `User::$hidden`). Any new sensitive column must be added there.
- Casts go in the `casts(): array` method, not a `$casts` property. Tokens use `'encrypted'`.
- Declare relationships with return types (`HasMany`, `HasOne`, `BelongsTo`) and use them
  instead of manual `where('user_id', ...)` queries.

### Authorization

The project has no policies yet; access control is enforced two ways, and both are mandatory:

1. **Scope through the user relation**: `$request->user()->moments()`, `$request->user()->instagramAccount()`, `$request->user()->savedContent()`. This is the preferred form.
2. **Explicit ownership guard** when a route-model-bound record arrives: `abort_unless($moment->user_id === $request->user()->id, 404)` — see `MomentController::ensureOwner()`. Return **404**, not 403, so record existence does not leak.

Every route touching user-owned data goes through one of these. If a check does not fit
either shape, add a Policy and register it — do not skip the check.

### Responses

- Responses are hand-built arrays with explicit keys. Shared shapes live in a view/service
  class: `ContentPostView::make()` is the single renderer for a content post; use it rather
  than serializing a `ContentPost` model directly.
- For small payloads use `->only([...])` (`AuthController`, `ProfileController`) so new
  columns are never exposed by accident.
- Never return a raw model that holds tokens, hashes, or internal state.
- Keys are `snake_case` and match the frontend types in `frontend/app/types/`.

### Errors

- Throw a domain exception (`ContentGenerationException`, `InstagramIntegrationException`)
  and map it to a status in `bootstrap/app.php` `withExceptions()`. Current mapping:
  Instagram → 422, generation → 503.
- Exception messages are user-facing product copy ("Personal could not draft this right
  now…"). Log the technical detail with `Log::error(..., ['exception' => $exception])`; never
  surface a stack trace or provider payload.

### Services, config and secrets

- Business logic lives in `app/Services`, with constructor promotion and `readonly`
  properties (`ClaudeContentGenerationService`, `RecommendationService`).
- Swappable behaviour goes behind an interface bound in `AppServiceProvider::register()` —
  `ContentGenerationService` resolves to the Claude or mock driver from
  `config('services.content_generation.driver')`. Follow that pattern, not `if (env(...))`.
- **Read `env()` only inside `config/`.** Application code uses `config('services.…')`.
- Third-party credentials go in `config/services.php` plus `backend/.env.example` with an
  empty value. Never commit a real key, token or secret.
- Rate limiters are declared in `AppServiceProvider::boot()`: `api` (120/min), `auth`
  (5/min per email + 20/min per IP), `generation` (10/min). Any new endpoint that costs money
  or touches credentials must be attached to an appropriate limiter in `routes/api.php`.

### Routes

`routes/api.php` groups by middleware: `throttle:auth` for credentials, `auth:sanctum` for
the product API, `auth:sanctum` + `throttle:generation` for model-backed endpoints. Import
controllers with `use` statements and keep route order/grouping consistent.

When adding an endpoint:

1. Add the route to the right middleware group.
2. Validate input.
3. Authorize (relation-scoped query or ownership guard).
4. Run the business logic — in a service if it is more than a couple of statements.
5. Return a predictable JSON shape with an explicit status.
6. Map expected failures to a domain exception with readable copy.

### Database

- One dated migration per feature (`2026_08_16_110000_create_personal_mvp_tables.php`),
  with a real `down()` that reverses it — see the existing files.
- Do not edit a shipped migration; add a new one.
- Use `foreignId(...)->constrained()->cascadeOnDelete()` (or `nullOnDelete()` where the row
  should survive), and index columns you filter or sort on (`published_at`).
- Avoid N+1: eager-load with `with(['contentPost.creator', 'lifeMoment'])` (`FeedController`)
  or aggregate with `withCount()` (`InstagramConnectionController::status()`).
- Let the database narrow candidates before PHP scoring — see the `CANDIDATE_MULTIPLIER`
  comment in `RecommendationService`.
- Wrap multi-write operations that must all succeed in `DB::transaction()`.
- Do not store derived values that can be computed cheaply unless there is a measured reason.
- Never delete user or production data without an explicit instruction.

### Authentication

Sanctum personal access tokens, issued by `AuthController` and named after the user agent.
The API is also `statefulApi()`, so `logout` must revoke the token *and* invalidate the
session. Do not add a second auth mechanism. `/api/development/session` only exists when
`APP_ENV=local` **and** `config('app.enable_dev_session')` — keep both guards.

## Frontend — Nuxt 3 / Vue 3 / TypeScript

- `<script setup lang="ts">` with the Composition API, everywhere. No Options API, no
  default-exported components.
- Nuxt auto-imports composables, components and Vue APIs — do not add imports for them.
  Types and helpers from `~/types/*` are imported explicitly:
  `import type { ContentPost } from '~/types/product'`.
- Composables are **named** exports: `export function usePersonalApi() { … }` in
  `app/composables/`, returning a plain object of refs and functions.

### API access

- **Every** request goes through `usePersonalApi().apiFetch`. Do not call `$fetch`,
  `useFetch` or `useAsyncData` against the API directly — the token header, the 401 handling,
  the dev-token bootstrap and the redirect to `/login` all live in that composable.
- Data is loaded **client-side in `onMounted`** (see `pages/index.vue`, `pages/create.vue`).
  This is intentional: `NUXT_PUBLIC_API_BASE` is a browser-reachable URL, so the server has no
  internal API address. Keep new pages on the same pattern.
- Auth state lives in `useAuth()` (`useState('personal-user')` + the token cookie). Route
  protection is `app/middleware/auth.global.ts`; add public paths to its `publicRoutes` set.

### Types

- API payload types and domain entities live in `app/types/product.ts` and
  `app/types/instagram.ts`, with **snake_case keys mirroring the backend response exactly**.
  When a backend response changes, update the type and every consumer in the same change.
- Avoid `any`. The few existing uses (`options: any` in `apiFetch`, `exception: any` in catch
  blocks, `content_post?: any`) are legacy — do not add new ones, and prefer typing them
  properly when you touch that code.
- Shared display helpers (`compactNumber`, `relativeDate`) are exported from
  `~/types/product`. Reuse them instead of re-formatting inline.

### Components and pages

- Pages orchestrate: fetch, hold local `ref` state, and delegate rendering. Business rules
  stay on the backend.
- Extract reusable logic into a composable, reusable UI into a component. Do not create a
  component for one-off markup unless it makes the page meaningfully easier to read.
- Props with `defineProps<{ post: ContentPost }>()`, events with typed `defineEmits<{ save: [post: ContentPost] }>()` — see `ContentCard.vue`. Never mutate props; emit instead.
- Prefer `computed` over duplicated state (`initials` in `layouts/default.vue`); avoid
  watchers when a computed will do.
- Icons: add a new glyph branch to `components/AppIcon.vue` rather than inlining an SVG.
- Guard browser-only APIs (`window`, `document`, `localStorage`) with `import.meta.client`,
  as `usePersonalApi` and `useInstagram` do. Clear timers/intervals on unmount.
- Handle all four states where they apply: loading (skeletons with `animate-pulse`), empty,
  success, and error. Error copy is product-voiced and read from the API when available:
  `error.value = exception?.data?.message || 'Personal could not load today’s opportunities.'`

### Copy and tone

- **Avoid dashes (`—`, `–`, ` - `) in user-facing copy.** Prefer a comma, a full stop, or two
  sentences. This applies to i18n strings, error messages and any product-voiced text.
- All user-facing text lives in i18n (`frontend/i18n/locales/en.json` + `fr.json`). Never
  hardcode a visible string in a component; add the key to **both** locales in the same change.
- French copy uses **tutoiement** (informal "tu", not "vous") and matches the product voice.

## Tailwind / UI

The design system is a small set of CSS variables in `app/assets/css/main.css`:
`--ink #17171a`, `--muted #6e6e73`, `--faint #9b9b9f`, `--paper #f7f7f6` (app background),
`--rail #fcfcfb` (sidebar), `--surface #ffffff` (cards), `--line #e8e8e4`, `--line-soft`,
`--accent #b6871f` + `--accent-soft` (gold), `--ai #7c62f5`, `--positive`, `--night #0d0c0a`,
`--display` (headline serif), plus the `hero-night` / `panel-night` utilities and the
`animate-rise` / `animate-breathe` keyframes.

- Use Tailwind utilities; do not add custom CSS unless it is a genuinely new primitive, in
  which case it belongs in `main.css`.
- Reuse existing tokens and shapes: `bg-[var(--paper)]`, `border-[var(--line)]`,
  cards `rounded-[20px] border border-[var(--line)] bg-[var(--surface)]` with
  `shadow-[0_1px_2px_rgba(23,23,26,.04)]`, one dark hero per page
  (`hero-night rounded-[26px]` with centred serif headline and a white pill CTA) and its
  inner `panel-night rounded-[20px]` panels, icon tiles
  `rounded-[13px] bg-[var(--accent-soft)] text-[#8a6413]`, primary buttons
  `rounded-full bg-[var(--ink)] px-5 py-3 text-sm font-medium text-white`, secondary buttons
  `rounded-full border border-[var(--line)] bg-[var(--surface)]`, serif display headings
  (`font-serif tracking-[-.03em]`), eyebrow labels
  (`text-[10px] font-semibold uppercase tracking-[.18em] text-[var(--faint)]`).
- Do not invent new colours, radii, shadows or type scales when an equivalent already exists.
- Responsive: mobile-first, `md:` for the desktop sidebar layout (`md:ml-[264px]`), with the
  mobile bottom nav and header in `layouts/default.vue`. The page title lives in the desktop
  top bar (`pageTitle` in the layout), so pages start with their content, not an `<h1>`.
  Check both widths for UI changes.
- Accessibility: real `<button>`/`<NuxtLink>` elements, `alt` text on images (`aria-hidden`
  on decorative SVGs), `role="alert"` on error messages, visible hover/focus/disabled states,
  and a disabled or busy state for actions in flight (`drafting` in `pages/create.vue`).

## Frontend ↔ backend contract

- Response shapes are a contract. Do not rename or drop a key without updating
  `frontend/app/types/` and every page/composable that reads it.
- Keep names identical across the stack: backend `snake_case` keys stay `snake_case` in the
  TypeScript types.
- Backend validation is the source of truth; frontend checks are a convenience only.

## Security checklist

Consider, for every change: authentication, authorization (per-user resource ownership),
input validation, mass-assignment exposure (`$guarded = []`), XSS (never `v-html` with API or
user content), CSRF/session handling, SQL injection (no raw string interpolation in queries),
file-upload validation, rate limiting on expensive or credential endpoints, and safe handling
of tokens and secrets (encrypted casts, `$hidden`, never logged).

Never weaken or bypass a security check to make a feature work. If a check blocks the feature,
solve it properly or stop and ask.

## Naming

Follow the existing conventions: `PascalCase` singular models (`ContentPost`, `LifeMoment`),
`XxxController`, `XxxService`, `PascalCase.vue` components, `useXxx.ts` composables,
kebab-case routes and page files, plural snake_case tables (`content_posts`, `life_moments`),
`camelCase` PHP methods and TS functions.

Use domain words — `remix`, `moment`, `opportunity`, `creator`, `draft`, `sync`. Avoid
`data`, `item`, `value`, `temp`, `thing`, `test` when a domain name is available (note
`pages/index.vue` still has a `data` ref; do not copy it).

## Verification

No ESLint/Prettier is configured on the frontend; `nuxt typecheck` and `nuxt build` are the checks.

```bash
cd backend && php artisan test && ./vendor/bin/pint --test
```

```bash
cd frontend && npm run typecheck && npm run build
```

- Backend tests are PHPUnit classes (`Tests\TestCase`, `RefreshDatabase`, `test_snake_case`
  method names) running on in-memory SQLite; several seed `DatabaseSeeder` in `setUp()`.
  See `tests/Feature/PersonalMvpTest.php` and `tests/Feature/AuthTest.php`.
- Run the relevant checks after meaningful changes. **Never claim a command passed unless you
  ran it.** If you cannot run one, say so explicitly.
- When fixing a bug, add or update a feature test that fails without the fix.

## Before calling a task done

- No `dd()`, `dump()`, `console.log`, commented-out code, temporary mocks or hardcoded credentials.
- No unused imports, dead code, or unrelated diff noise.
- Edge cases checked; types, validation and authorization verified.
- UI changes checked at mobile and desktop widths.
- Existing behaviour still works (run the tests).

## Working method

1. Understand the request; re-read `README.md` if the domain is unfamiliar.
2. Inspect the relevant existing files and their neighbours.
3. Pick the smallest clean implementation that fits the existing architecture.
4. Implement it.
5. Check callers and related files for regressions (routes, types, pages consuming the API).
6. Run the relevant checks above.
7. Review your own diff and remove anything unnecessary.
8. Summarize what changed, what you ran, and anything you could not verify.

Do not make architectural decisions without a concrete need. When the request is ambiguous,
choose the option that is simplest, safest, easiest to maintain, and most consistent with
what is already here — and state the assumption you made.
