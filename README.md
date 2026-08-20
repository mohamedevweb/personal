# Personal

Personal is a Nuxt 3 + Laravel 12 MVP foundation. The authenticated creator's Instagram connection is real, content generation runs on OpenAI, and the For You feed is ranked from real Instagram accounts scraped through Apify. Every scraping driver degrades to a deterministic mock when its key is absent, so the product runs unconfigured.

PostgreSQL is the configured application database. PHPUnit uses an isolated in-memory SQLite database for fast integration tests.

## Included product experiences

- Email and password accounts with Sanctum API tokens
- Real Instagram Login onboarding and background import
- Personalized **For You** feed with 12 ranked daily opportunities
- Content analysis with hook, structure, fit, and performance context
- Reel, carousel, and caption remix flows grounded in life moments
- Editable carousel slides with reorder, delete, and regeneration controls
- Life Moments with explainable story-potential scores
- Combined trend + personal-moment opportunities
- Editable Personal memory, Saved content, Create, and Settings
- 15 benchmark creators and 40 realistic seeded posts

Recommendation scoring is deterministic and explainable — see [How the For You feed ranks](#how-the-for-you-feed-ranks). The authenticated user's Instagram profile and media are real data fetched from Meta, and drafts are written by a language model from that data.

## Structure

- `frontend/` — Nuxt/Vue product application, authentication and onboarding
- `backend/` — Laravel REST API, product domain, seeded benchmark layer, encrypted Instagram credentials, and queue-backed sync
- `docker-compose.yml` — Postgres, API (php-fpm + nginx), queue worker, scheduler and the Nuxt server

The Instagram boundary lives under `backend/app/Services/Instagram`. Product code consumes normalized `InstagramAccount` and `InstagramMedia` models and never consumes Meta response shapes directly.

## Authentication

`POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me` and `POST /api/auth/logout` issue and revoke Sanctum tokens. The frontend keeps the token in a cookie and a global route middleware sends signed-out visitors to `/login`.

Every API route is rate limited: 120 requests per minute per identity by default, 10 per minute for generation, and 5 per minute per email address (plus 20 per address) for credential endpoints.

Local development can still mint a demo token from `/api/development/session`. That route requires **both** `APP_ENV=local` and `ENABLE_DEV_SESSION=true`, so a deploy carrying a stale environment file cannot expose it.

## Content generation

Drafting runs on **OpenAI**, through the Responses API with a strict per-format JSON schema. Set `OPENAI_API_KEY` and, if you want a model other than the default, `OPENAI_MODEL`.

The provider is one line of configuration. `CONTENT_GENERATION_DRIVER` selects an implementation of `ContentGenerationService`:

| Driver | Behaviour |
| --- | --- |
| `openai` (default) | OpenAI Responses API. Requires `OPENAI_API_KEY`. |
| `claude` | Anthropic Messages API. Requires `ANTHROPIC_API_KEY`. |
| `mock` | Deterministic offline copy. Used by the test suite. |

A driver whose API key is missing falls back to `mock`, so an unconfigured environment degrades the drafts instead of breaking the product.

Only the transport differs between drivers. The prompt, the JSON schema and the assembly of the final payload live in `ContentDraftBlueprint` and `ContentDraftAssembler`, so switching provider never means rewriting the prompt.

The model writes only the creative half of a draft. The source hook, the selected life moment and the creator profile are attached from our own records, so the response cannot invent them, and prompts instruct the model to borrow structure and never subject matter. Refusals, truncated answers and API errors all surface to the creator as a `503` with a readable message instead of a stack trace.

Cost and depth are tunable with `OPENAI_MODEL`, `OPENAI_MAX_OUTPUT_TOKENS`, and `OPENAI_REASONING_EFFORT` — the last one applies to reasoning models only and must stay empty for the others.

## How the For You feed ranks

A post earns its place by beating the account that published it — not by coming from a large one. Discovery runs in two stages, and only the second one is allowed to score anything.

**Stage 1 — `DiscoverNicheContent`.** The creator's niche is expanded into hashtags, those pages are scraped, and the accounts and posts behind them are recorded. The actor returns no follower count on post-level results, so a hashtag row genuinely cannot be judged: nothing here is scored, rows land with `measured_at` null, and stage 1 is best understood as harvesting *accounts* rather than posts.

Hashtag quality decides everything downstream. Reach-bait tags (`viralreels`, `explorepage`, `fyp`, `instagood`…) are not niches — they are what accounts with no audience post under in order to be seen — so scraping them returns spam by construction. `config('services.discovery.blocked_hashtags')` strips them from every expansion, enforced in code rather than trusted to the prompt.

**Stage 2 — `MeasureAccountEngagement`.** Each account found is scraped as a whole profile, which yields three things a hashtag page never exposes: the real follower count, what the account is actually about, and the median engagement of its recent posts. That median is the baseline, and *every* post the account has in the feed is scored against it — including ones picked up earlier through a hashtag.

Three numbers come out of it:

| Column | Meaning |
| --- | --- |
| `creators.baseline_engagement` | The account's normal post, as the median of its recent likes + comments. |
| `content_posts.outlier_score` | Engagement over that baseline. `1.0` is an ordinary post for the account, `3.0` is a genuine breakout. |
| `content_posts.engagement_rate` | Engagement as a share of the audience, so a 20k and a 2M account are comparable. |

`performance_ratio` is kept in step with `outlier_score` for the clients still reading it.

Because the baseline is the account's own median, roughly half of any scrape lands below `1.0` by construction — that is the point. `DISCOVERY_MIN_OUTLIER_SCORE` (default `1.2`) is the floor to reach the feed, and `DISCOVERY_FEED_WINDOW_DAYS` (default `30`) drops posts describing a niche that has already moved on.

A lift is a ratio, and a ratio has no sense of scale: an account whose median post gets two likes turns a three-like post into a `1.5×` "outlier". So two absolute floors sit underneath it — `DISCOVERY_MIN_FOLLOWERS` (default `5000`) and `DISCOVERY_MIN_POST_ENGAGEMENT` (default `500`). An account below the follower floor is measured, so its cooldown applies and we stop paying to re-scrape it, but it is never scored and never classified.

There is deliberately **no fallback**. An unmeasured post carries no evidence, so when measurement has not run yet — or has failed in the queue — the feed shows its empty state rather than degrading to raw scrape output. A page of two-like posts is worth less than an honest empty one.

`RecommendationService` then weighs outlier score (0.35), creator similarity (0.20), topic similarity (0.15), reach (0.15) and freshness (0.15). Both similarity terms match the creator's own vocabulary as substrings, because hashtags arrive glued together — `vegan` has to find `veganmealprep`.

Niche is read from the account itself. `CreatorNicheService` classifies a discovered creator from their bio, their recurring hashtags and a sample of captions, and the result is cached on the creator so the model is not re-run on every measurement. Discovery previously stamped every account with the niche of whichever user found them, which described the searcher rather than the creator.

Scraping cost is capped on every axis: hashtags have a cooldown (`DISCOVERY_COOLDOWN_DAYS`), accounts have their own (`DISCOVERY_MEASURE_COOLDOWN_DAYS`), and a single run measures at most `DISCOVERY_MEASURE_BATCH` accounts. A daily scheduled pass re-measures the stalest tracked accounts within those same limits, which is also how the feed learns about new posts between hashtag runs.

## Instagram app setup

This implementation uses **Instagram API with Instagram Login**, not Instagram Basic Display and not the Facebook Login flow.

1. Create a Meta app and add **Instagram API with Instagram Login**.
2. Add the exact callback URL from `INSTAGRAM_REDIRECT_URI` to the app's valid OAuth redirect URIs.
3. Request `instagram_business_basic` and `instagram_business_manage_insights`. The older `business_basic`-style scopes are deprecated.
4. Add test Creator/Business accounts while the Meta app is in development mode. Production accounts outside the app's roles require Meta App Review and Advanced Access.
5. Set `INSTAGRAM_APP_ID`, `INSTAGRAM_APP_SECRET`, and the callback URL in `backend/.env`.

The direct Instagram Login flow targets professional Creator and Business accounts and uses `graph.instagram.com`; it does not require a Facebook Page association.

## Run with Docker

```bash
cp backend/.env.example backend/.env
docker compose run --rm app php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan db:seed
```

The app is then on http://localhost:3000 and the API on http://localhost:8000. Override `API_PORT`, `FRONTEND_PORT`, `NUXT_PUBLIC_API_BASE` and the `DB_*` values from the shell or a root `.env` if those ports are taken.

Composition:

- `app` — php-fpm, and the only service that runs migrations (`RUN_MIGRATIONS=true`)
- `web` — nginx, serving `public/` and proxying PHP to `app`
- `queue` / `scheduler` — the same image running `queue:work` and `schedule:work`
- `frontend` — the Nuxt Nitro server
- `postgres` — with a health check the backend services wait on

`NUXT_PUBLIC_API_BASE` is read by the browser, so it must be a URL the browser can reach rather than a compose service name. Product data is fetched client-side, so server-side rendering never needs an internal API address.

## Browsing the database locally

Postgres publishes `127.0.0.1:5432`, so any desktop client (TablePlus, DBeaver, Postico, `psql`) can connect with the `DB_*` credentials — `personal` / `personal` / `personal` by default. The bind is loopback-only, so the database is never reachable from outside this machine.

For a browser UI, start the `adminer` service. It sits behind the `tools` profile because it has no authentication of its own, so a plain `docker compose up` never starts it:

```bash
docker compose --profile tools up -d adminer
```

Adminer is then on http://localhost:8080 — system **PostgreSQL**, server **postgres** (prefilled), and the `DB_*` username, password and database. Override `ADMINER_PORT` or `DB_PORT_HOST` if either port is taken.

A shell is often faster than either:

```bash
docker compose exec postgres psql -U personal -d personal
```

## Local setup without Docker

```bash
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

In a second terminal, run the queue worker (the OAuth callback queues the first import):

```bash
cd backend
php artisan queue:work
```

Then start Nuxt:

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

## Security and lifecycle

- Passwords are hashed and never serialized; tokens are per device and revoked individually on sign-out, which also invalidates the session.
- OAuth state is random, stored only as a SHA-256 hash, expires after 10 minutes, and is single-use.
- Instagram tokens use Laravel's `encrypted` model cast and are hidden from serialization.
- The callback exchanges the authorization code server-side, then upgrades the result to a long-lived token.
- Tokens nearing expiry are refreshed server-side during sync.
- Insight requests are scoped to the metrics a media type actually supports, with a single narrowed retry, so an import cannot fan out into a rate-limit wall. The sync job is bounded by a timeout.
- Daily queued sync keeps profile/media current; unavailable metrics are omitted rather than filled with fake values.
- Disconnecting deletes the Instagram account row, encrypted token, and locally imported media.

## Verification

```bash
cd backend && php artisan test && ./vendor/bin/pint --test
cd frontend && npm run typecheck && npm run build
```
