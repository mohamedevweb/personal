# Personal

Personal is a Nuxt 3 + Laravel 12 MVP foundation. The authenticated creator's Instagram connection is real; recommendation, trend, competitor, and generation intelligence remain replaceable mocks for the MVP.

PostgreSQL is the configured application database. PHPUnit uses an isolated in-memory SQLite database for fast integration tests.

## Included product experiences

- Real Instagram Login onboarding and background import
- Personalized **For You** feed with 12 ranked daily opportunities
- Content analysis with hook, structure, fit, and performance context
- Reel, carousel, and caption remix flows grounded in life moments
- Editable carousel slides with reorder, delete, and regeneration controls
- Life Moments with explainable story-potential scores
- Combined trend + personal-moment opportunities
- Editable Personal memory, Saved content, Create, and Settings
- 15 benchmark creators and 40 realistic seeded posts

Recommendation scoring and content generation live behind small service boundaries and intentionally use deterministic MVP logic. The authenticated user's Instagram profile and media are the exception: those are fetched from Meta and stored as normalized real data.

## Structure

- `frontend/` — Nuxt/Vue product application and onboarding
- `backend/` — Laravel REST API, product domain, seeded benchmark layer, encrypted Instagram credentials, and queue-backed sync

The Instagram boundary lives under `backend/app/Services/Instagram`. Product code consumes normalized `InstagramAccount` and `InstagramMedia` models and never consumes Meta response shapes directly.

## Instagram app setup

This implementation uses **Instagram API with Instagram Login**, not Instagram Basic Display and not the Facebook Login flow.

1. Create a Meta app and add **Instagram API with Instagram Login**.
2. Add the exact callback URL from `INSTAGRAM_REDIRECT_URI` to the app's valid OAuth redirect URIs.
3. Request `instagram_business_basic` and `instagram_business_manage_insights`. The older `business_basic`-style scopes are deprecated.
4. Add test Creator/Business accounts while the Meta app is in development mode. Production accounts outside the app's roles require Meta App Review and Advanced Access.
5. Set `INSTAGRAM_APP_ID`, `INSTAGRAM_APP_SECRET`, and the callback URL in `backend/.env`.

The direct Instagram Login flow targets professional Creator and Business accounts and uses `graph.instagram.com`; it does not require a Facebook Page association.

## Local setup

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

Local development creates a demo user and keeps a short-lived Personal API token in memory through a local-only endpoint. That endpoint is not registered outside `APP_ENV=local`; production must use the product's normal user authentication. This token is for the Personal API and is unrelated to the Instagram token, which never reaches the browser.

## Security and lifecycle

- OAuth state is random, stored only as a SHA-256 hash, expires after 10 minutes, and is single-use.
- Instagram tokens use Laravel's `encrypted` model cast and are hidden from serialization.
- The callback exchanges the authorization code server-side, then upgrades the result to a long-lived token.
- Tokens nearing expiry are refreshed server-side during sync.
- Daily queued sync keeps profile/media current; unavailable metrics are omitted rather than filled with fake values.
- Disconnecting deletes the Instagram account row, encrypted token, and locally imported media.

## Verification

```bash
cd backend && php artisan test && ./vendor/bin/pint --test
cd frontend && npm run typecheck && npm run build
```
