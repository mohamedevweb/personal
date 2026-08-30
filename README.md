# Personal

Personal is a Nuxt 3 + Laravel 12 MVP foundation. The authenticated creator's Instagram connection is real, content generation runs on OpenAI, and the For You feed is ranked from public Instagram data fetched through ScrapeCreators. A deterministic mock discovery driver is available for tests and unconfigured development.

PostgreSQL is the configured application database. PHPUnit uses an isolated in-memory SQLite database for fast integration tests.

## Included product experiences

- Email and password accounts with Sanctum API tokens
- Real Instagram Login onboarding and background import
- Global feed with 24 ranked opportunities and instant eligible-content rotation
- Content analysis with hook, structure, fit, and performance context
- Reel, carousel, and caption remix flows grounded in life moments
- Editable carousel slides with reorder, delete, and regeneration controls
- Life Moments with explainable story-potential scores
- Combined trend + personal-moment opportunities
- Editable Personal memory, Saved content, Create, and Settings
- 15 benchmark creators and 40 realistic seeded posts in development and test only

Recommendation scoring is deterministic and explainable — see [How the For You feed ranks](#how-the-for-you-feed-ranks). The authenticated user's Instagram profile and media are real data fetched from Meta, and drafts are written by a language model from that data.

## Structure

- `frontend/` — Nuxt/Vue product application, authentication and onboarding
- `backend/` — Laravel REST API, product domain, seeded benchmark layer, encrypted Instagram credentials, and queue-backed sync
- `docker-compose.yml` — Postgres, API (php-fpm + nginx), background and interactive queue workers, scheduler and the Nuxt server

The Instagram boundary lives under `backend/app/Services/Instagram`. Product code consumes normalized `InstagramAccount` and `InstagramMedia` models and never consumes Meta response shapes directly.

## Authentication

`POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me` and `POST /api/auth/logout` issue and revoke Sanctum tokens. The frontend keeps the token in a cookie and a global route middleware sends signed-out visitors to `/login`.

Every API route is rate limited: 120 requests per minute per identity by default, 10 per minute for generation, and 5 per minute per email address (plus 20 per address) for credential endpoints.

Local development can still mint a demo token from `/api/development/session`. That route requires **both** `APP_ENV=local` and `ENABLE_DEV_SESSION=true`, so a deploy carrying a stale environment file cannot expose it.

## Content generation

Drafting runs on **OpenAI**, through the Responses API with a strict per-format JSON schema. Set `OPENAI_API_KEY`. Remixes use the low-latency `gpt-5.6-luna` model by default, independently from the model used by chat and analysis.

The provider is one line of configuration. `CONTENT_GENERATION_DRIVER` selects an implementation of `ContentGenerationService`:

| Driver | Behaviour |
| --- | --- |
| `openai` (default) | OpenAI Responses API. Requires `OPENAI_API_KEY`. |
| `claude` | Anthropic Messages API. Requires `ANTHROPIC_API_KEY`. |
| `mock` | Deterministic offline copy. Used by the test suite. |

A driver whose API key is missing falls back to `mock`, so an unconfigured environment degrades the drafts instead of breaking the product.

Only the transport differs between drivers. The prompt, the JSON schema and the assembly of the final payload live in `ContentDraftBlueprint` and `ContentDraftAssembler`, so switching provider never means rewriting the prompt.

The model writes only the creative half of a draft. The source hook, the selected life moment and the creator profile are attached from our own records, so the response cannot invent them, and prompts instruct the model to borrow structure and never subject matter. Refusals, truncated answers and API errors all surface to the creator as a `503` with a readable message instead of a stack trace.

Remix latency, cost and depth are tunable with `OPENAI_REMIX_MODEL`, `OPENAI_REMIX_MAX_OUTPUT_TOKENS`, and `OPENAI_REMIX_REASONING_EFFORT`. The defaults use no reasoning and a 2,500-token ceiling because the per-format schemas describe a short, bounded draft. `OPENAI_MODEL` remains the model used by chat and auxiliary analysis.

## How the For You feed ranks

A post earns its place by beating the account that published it, not by coming from a large one. The connected account still comes from Meta's official Instagram API. Public discovery goes through `InstagramDataProvider`, with ScrapeCreators as the only real-data driver and a deterministic mock for tests and local development.

After the first Instagram import, onboarding sends the user directly to their feed. The approved catalogue seeds that first experience from the detected vertical without requiring a creator selection. The private inspiration infrastructure remains available outside the onboarding gate: selected accounts can lead that user's feed, with at most two posts per inspiration for diversity, while the approved catalogue supplies only publications that clear the same relevance gate. The feed is deliberately shorter when fewer relevant posts exist. Newly selected accounts are measured and checked for safety. A user-selected account stays `discovered` and never becomes part of the global catalogue without the normal editorial approval.

The feed has two views. `GET /api/feed` powers **For You**, combining private inspirations with approved creators close to the user's vertical and market. Its `items` contain only publications that pass the member's semantic relevance threshold, while `explore_items` contains a small, separate set from configured adjacent verticals. `GET /api/feed/global` powers **Global**, ranking all recent, measured and safe posts from approved creators with the same outlier and freshness model, but without niche or market quotas.

Every member who completes Instagram sync also has one public identity in `creators`, linked through nullable `creators.user_id`. Sync reuses an existing public creator by Instagram ID and then username, so a discovered creator who later joins Personal is never duplicated or stripped of editorial approval. Their own posts are excluded from both of their feeds and their account is never suggested back to them as an inspiration. Other users may see that creator only through the normal measurement, safety and catalogue rules.

After deploying the creator identity migration, link members who completed Instagram sync before this feature without making any provider request:

```bash
docker compose exec app php artisan personal:link-registered-creators
```

Set `DISCOVERY_DRIVER=scrapecreators` and `SCRAPECREATORS_API_KEY` to run creator discovery and account measurement through ScrapeCreators. Profile responses use ScrapeCreators' provider-side cache for the same three-day window as account measurement by default. Change `SCRAPECREATORS_CACHE_MAX_AGE` when testing freshness versus cost.

**Creator DNA.** A public handle is enough to start: `AnalyzeCreatorHandle` reads the public profile and up to 12 recent captions, then stores a structured `creator_dna` with positioning, primary niche, sub-niches, topics, audience, language, content pillars, tone, explicit current projects and goals, observable content strengths and voice. That initial DNA completes onboarding. A background pass imports up to 30 posts, transcribes a representative sample of the creator's Reels and reads selected carousel slides with multimodal OCR. Reel and carousel jobs run as a parallel batch, then ordered slide text, narrative structure and recurring visual patterns refine the same DNA. The media results are stored per post, so later rebuilds do not pay to analyse unchanged content again. These signals fill the member's Personal memory automatically. `SyncInstagramAccount` enriches the same profile later when the member connects through OAuth, while a memory edited manually is never overwritten. Model-backed analysis has a deterministic fallback, so an unavailable enrichment never removes the caption-based DNA.

**Stage 1, `DiscoverNicheContent`.** The Creator DNA becomes precise account-search phrases. ScrapeCreators finds seed creators, then Instagram suggested accounts expand each seed into a reusable creator graph. Creators are upserted by Instagram ID when available, relationships are refreshed rather than duplicated, and global query cooldowns avoid paying twice for a niche Personal already knows.

**Stage 2, `MeasureAccountEngagement`.** Each discovered account is fetched with its recent posts. Its own bio and captions classify its niche, normalized niches are attached in `creator_niches`, and its recent performance establishes the baseline used to score every post from that account.

Three numbers come out of it:

| Column | Meaning |
| --- | --- |
| `creators.performance_baselines` | Median views and median available engagement for the account's recent posts. |
| `content_posts.outlier_score` | Weighted lift over those available baselines. `1.0` is ordinary for the account, `3.0` is a genuine breakout. |
| `content_posts.engagement_rate` | Available engagement as a share of the audience, so a 20k and a 2M account are comparable. |

`performance_ratio` is kept in step with `outlier_score` for the clients still reading it.

Because the baseline is the account's own median, roughly half of any scrape lands below `1.0` by construction — that is the point. `DISCOVERY_MIN_OUTLIER_SCORE` (default `1.2`) is the floor to reach the feed, and `DISCOVERY_FEED_WINDOW_DAYS` (default `30`) drops posts describing a niche that has already moved on.

A lift is a ratio, and a ratio has no sense of scale: an account whose median post gets two likes turns a three-like post into a `1.5×` "outlier". So two absolute floors sit underneath it — `DISCOVERY_MIN_FOLLOWERS` (default `5000`) and `DISCOVERY_MIN_POST_ENGAGEMENT` (default `500`). An account below the follower floor is measured, so its cooldown applies and we stop paying to re-scrape it, but it is never scored and never classified.

There is deliberately **no fallback**. An unmeasured post carries no performance evidence, and an unrelated post carries no personal relevance. When either signal is missing, the feed stops instead of filling its requested size. A short or empty feed is preferable to a page of weak or off-topic recommendations.

`FeedRanker` first combines outlier score, reach and freshness. The personalized feed then classifies each publication from its full caption, tags and any stored Reel transcript or carousel reading. That classification is compared with the member's Creator DNA and the publishing account through broad verticals, narrower semantic clusters and topic overlap. `FEED_MIN_CREATOR_AFFINITY` is an entry threshold, not merely a ranking preference. Adjacent verticals use their own threshold and remain in `explore_items`. Performance stays the main ordering weight after relevance has admitted a post. Saves and remixes boost similar creators and topics; a dismissal can suppress a subject, creator or language. The blend remains configurable through `FEED_WEIGHT_PERFORMANCE` and `FEED_WEIGHT_CREATOR_AFFINITY`; the Global feed keeps the performance-only ordering.

Niche is read from the account itself. `CreatorNicheService` classifies a discovered creator from profile metadata, recurring hashtags and a balanced multi-post caption sample. Topics must be supported by the profile or repeated across distinct posts, so an isolated campaign prop or food mention cannot become part of the niche. The versioned result is cached on the creator and recalculated only when the analysis contract changes. Discovery previously stamped every account with the niche of whichever user found them, which described the searcher rather than the creator.

**Content safety.** Every measured account is checked before its publications can enter the shared catalogue. A deterministic French and English policy rejects explicit and abusive profile text, captions, hashtags and provider safety flags. When an OpenAI key is configured, `omni-moderation-latest` also checks each caption and image for sexual, hateful, harassing, violent, self-harm and illicit material. A second structured visual check enforces the product rule across every carousel frame: sexual or suggestive content, nudity, lingerie or bikinis, sexual or adult topics and graphic violence are blocked even in artistic, editorial or commercial contexts. Blocked creators are not scraped again, blocked posts are never scored, and an unavailable check leaves content pending by default instead of admitting it. The policy version is stored with each decision. Older database rows can be rechecked in bounded passes with `personal:enforce-content-safety-policy`, so a new version cannot silently inherit an outdated allowed decision. The policy can be configured with the `DISCOVERY_CONTENT_SAFETY_*` and `DISCOVERY_CONTENT_POLICY_*` variables in `backend/.env.example`.

Instagram CDN images and Reel videos are exposed to the frontend through signed API URLs. The API fetches each image server-side and keeps a temporary local cache under `storage/app/private/instagram-media`, which avoids browser blocking caused by Instagram's cross-origin resource policy. Reel videos are streamed on demand with byte-range support so the native player can start and seek without downloading the whole file first. `APP_URL` must be the public HTTPS URL of the backend so the generated media URLs are reachable from the frontend. Cache duration, signature lifetime, request timeout and media size limits are configurable with the `INSTAGRAM_MEDIA_*` variables documented in `backend/.env.example`.

Docker stores that cache in the shared `instagram-media-cache` volume. The API and queue workers therefore reuse the same files, and rebuilding a container does not discard the only usable copy after an Instagram CDN URL expires.

Discovery search cost is capped on every axis: search queries have a cooldown (`DISCOVERY_COOLDOWN_DAYS`), recovery passes retain the legacy measurement cutoff (`DISCOVERY_MEASURE_COOLDOWN_DAYS`), and one discovery run measures at most `DISCOVERY_MEASURE_BATCH` accounts. Ongoing account refreshes use the adaptive schedule below.

### Adaptive Instagram scraping

Tracked creators now carry `last_scraped_at`, `next_scrape_at`, `last_post_at`, `scrape_priority` and `scrape_status`. The adaptive public-account scheduler is paused by default. Set `INSTAGRAM_SCRAPING_SCHEDULED=true` to resume its daily scan of due creators in France, the United Kingdom and the United States. It then recalculates their priority from inspiration selections, matching For You verticals, catalogue importance, posting frequency, recent activity and recent outliers. Configured HOT, ACTIVE, WARM and COLD windows decide which accounts remain due for a given daily pass. Provider failures use exponential backoff instead of retrying every scheduler pass. A second daily command removes unprotected discovery content outside those three markets while preserving saved posts, remixes and member identities.

The same creator and Instagram media IDs remain global, so one provider refresh serves every user who selected that account. Feed requests never call a discovery provider. A full creator refresh upserts only unseen publications and updates metrics already present in the provider response. ScrapeCreators' cache remains enabled for interactive search, while scheduled refreshes bypass it because `next_scrape_at` is the application cache boundary.

Recent post metrics have their own lifecycle. Due posts are grouped by creator, so one recent-post request updates every due post returned for that account. Fresh content is checked on the configured 0 to 24 hour, 1 to 3 day and 4 to 7 day cadence. Posts from 8 to 30 days remain active only while outlier score, velocity, growth or ranking importance justifies the cost. Older posts stop automatically unless they are exceptional and protected by a save or remix. Promising posts move through HOT, WARM and COLD as growth decelerates.

Every real metric refresh writes one `content_post_metric_snapshots` row with views, likes, comments, shares, elapsed time, view delta, velocity and acceleration. The daily retention command keeps raw points for 30 days, downsamples older history to one point per UTC day and expires it after 365 days. All cadence, thresholds, batch sizes and retention windows live in `config/instagram_scraping.php` and can be overridden through the documented environment values.

The maintenance commands remain scheduled automatically. The adaptive scrape command runs automatically only when `INSTAGRAM_SCRAPING_SCHEDULED=true`; all three are safe to run manually:

```bash
cd backend
php artisan personal:dispatch-instagram-scrapes
php artisan personal:prune-post-metric-snapshots
php artisan personal:prune-unsupported-markets --dry-run
```

## Curated creator catalog

The first production dataset is a versioned Golden Catalog in `backend/database/catalog/instagram_creators.php`. It contains 25 human-curated French creators, five in each of the five canonical verticals. Each entry records the exact Instagram URL, editorial sources, topics and rationale. Followers and recognition tiers are intentionally absent because they are measured rather than guessed. Entries start as `pending` during review and are changed to `approved` only after the human editorial decision. Entries removed from production stay `inactive`, so a later import cannot recreate them.

Run the read-only audit first. It makes one profile request per creator. A separate posts request is made only when the profile response contains fewer than six publications. It then writes JSON and CSV reports under `backend/storage/app/private/catalog-reports` on the host and `/var/www/html/storage/app/private/catalog-reports` inside Docker:

```bash
docker compose exec app php artisan personal:audit-creator-catalog
```

To audit only newly added or corrected handles, repeat `--handle` as needed:

```bash
docker compose exec app php artisan personal:audit-creator-catalog \
  --handle=first_creator \
  --handle=second_creator
```

Provider failures are reported separately from editorial rejections. They include the HTTP status and provider message when available, leave unknown metrics as `null`, and can be retried without paying again for successful rows:

```bash
docker compose exec app php artisan personal:audit-creator-catalog \
  --retry-report=/var/www/html/storage/app/private/catalog-reports/creator-catalog-audit-YYYYMMDD-HHMMSS.json
```

`market_unverified` and `market_signal_mismatch` are warnings because the human-validated FR market in the manifest is authoritative. A legacy `recognition_tier_mismatch` is also a warning and proposes the measured tier. Review successful rows, then change only accepted manifest entries to `approved`. Import is idempotent, applies the manifest's market and canonical vertical, calculates recognition tier from the retrieved follower count, preserves provider provenance, synchronizes subtopics and queues measurement in batches of 10:

```bash
docker compose exec app php artisan personal:import-creator-catalog
```

Removing an entry from the manifest, or switching it to `inactive`, only stops the next import. The rows already in the database keep their `approved` curation status until the reconciliation command retires them, so an editorial retirement always takes two steps. Preview it first:

```bash
docker compose exec app php artisan personal:retire-catalog-creators --dry-run
```

It deactivates every catalog seed the manifest no longer approves and deletes their posts, then removes the creator once nothing is left attached. Posts a member saved or remixed are kept and stopped instead, unless `--including-protected` is passed. Deleting the posts rather than only deactivating the creator is deliberate: `curation_status` filters the personalised feed only while `DISCOVERY_CURATED_CATALOG_ONLY` is on, so a retirement that relied on it alone would silently depend on that flag.

After the queue has measured the approved seeds and the feed is useful, set `DISCOVERY_CURATED_CATALOG_ONLY=true` and restart the app, queue and scheduler containers. The feed then excludes every non-approved creator and stops automatic search-based insertion. Market quotas fall back to the best remaining approved French content while this first catalog is FR-only.

Related-account expansion is review-only and never writes creators or posts. Candidates are ranked by recent activity, metric coverage and median engagement, with niche proximity inherited from the approved seed that surfaced them:

```bash
docker compose exec app php artisan personal:discover-creator-candidates
```

Use that report to expand deliberately from 25 to 60 and then 120 creators. Add selected candidates to the manifest as `pending`, audit them, approve them and import again. Discovery never promotes candidates automatically.

An approved CSV can also be imported in explicit batches. The command reads the selected rows, measures each account from its recent publications, runs the normal content safety policy, and stores only the latest safe post. FR is an authoritative market hint. Combined UK/US rows still rely on the measured profile to choose GB or US.

Preview a 20-row batch before making provider requests or database writes:

```bash
cd backend
php artisan personal:seed-creator-batch /absolute/path/to/creators.csv --batch=1 --size=20 --dry-run
```

Then seed that exact batch. Repeating it is idempotent, and duplicate handles in later CSV batches update the existing creator instead of creating another row:

```bash
php artisan personal:seed-creator-batch /absolute/path/to/creators.csv --batch=1 --size=20
```

Measured content is retained for 90 days. The scheduler runs the purge daily and always protects posts saved by a user or used in a remix. Preview it manually with:

```bash
docker compose exec app php artisan personal:prune-discovery-content --dry-run
```

## Instagram app setup

This implementation uses **Instagram API with Instagram Login**, not Instagram Basic Display and not the Facebook Login flow.

1. Create a Meta app and add **Instagram API with Instagram Login**.
2. Add the exact callback URL from `INSTAGRAM_REDIRECT_URI` to the app's valid OAuth redirect URIs.
3. Request `instagram_business_basic` and `instagram_business_manage_insights`. The older `business_basic`-style scopes are deprecated.
4. Add test Creator/Business accounts while the Meta app is in development mode. Production accounts outside the app's roles require Meta App Review and Advanced Access.
5. Set `INSTAGRAM_APP_ID`, `INSTAGRAM_APP_SECRET`, and the callback URL in `backend/.env`.

The direct Instagram Login flow targets professional Creator and Business accounts and uses `graph.instagram.com`; it does not require a Facebook Page association.

## Running it

Compose carries the backing services; the frontend runs from the source tree, so
a change to a component is on screen immediately instead of waiting on an image
rebuild.

Start the backend:

```bash
cp backend/.env.example backend/.env
docker compose run --rm app php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan db:seed
```

Then the frontend, in a second shell:

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

The app is then on http://localhost:3000 and the API on http://localhost:8000. Override `API_PORT`, `NUXT_PUBLIC_API_BASE` and the `DB_*` values from the shell or a root `.env` if those ports are taken.

Composition:

- `app` — php-fpm, and the only service that runs migrations (`RUN_MIGRATIONS=true`)
- `web` — nginx, serving `public/` and proxying PHP to `app`
- `queue` / `interactive-queue` / `scheduler` — the same image running discovery work, analysis, isolated remix generation and scheduled tasks
- `postgres` — with a health check the backend services wait on

`NUXT_PUBLIC_API_BASE` is read by the browser, so it must be a URL the browser can reach. Product data is fetched client-side, so server-side rendering never needs an internal API address.

### Production queue status

From `/opt/personal` on the VPS, run the read-only queue status tool:

```bash
./queue-status.sh
```

It shows whether every queue worker service is running, followed by ready, delayed,
reserved and failed job counts for each queue. To list the job classes currently
waiting or running without printing their payloads, use:

```bash
./queue-status.sh --details --limit=50
```

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

## Error monitoring

Sentry captures unhandled Laravel API, queue and scheduler exceptions, together with Nuxt
browser and Nitro server errors. It is disabled by default and starts only when a DSN is set.
Default PII collection and session replay are intentionally disabled. Performance traces are
sampled at 10 percent by default and can be changed independently on each runtime.

Create one Laravel project and one Nuxt project in the same Sentry organization, then set:

```dotenv
# backend/.env
SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/1
SENTRY_ENVIRONMENT=production
SENTRY_RELEASE=git-commit-sha
SENTRY_TRACES_SAMPLE_RATE=0.1

# frontend runtime environment
NUXT_PUBLIC_SENTRY_DSN=https://examplePublicKey@o0.ingest.sentry.io/2
NUXT_PUBLIC_SENTRY_ENVIRONMENT=production
NUXT_PUBLIC_SENTRY_RELEASE=git-commit-sha
NUXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE=0.1
```

Use the same commit SHA for both releases so distributed frontend to API traces line up. For
readable production JavaScript stack traces, provide `SENTRY_ORG`, `SENTRY_PROJECT` and a secret
`SENTRY_AUTH_TOKEN` only while running `npm run build`. The token uploads source maps and must
never use the `NUXT_PUBLIC` prefix or be present in the runtime container.

## Verification

```bash
cd backend && php artisan test && ./vendor/bin/pint --test
cd frontend && npm run typecheck && npm run build
```
