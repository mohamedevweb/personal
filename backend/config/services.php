<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'content_generation' => [
        'driver' => env('CONTENT_GENERATION_DRIVER', 'openai'),
        // A worker or provider can disappear without running the job's failure
        // callback. Polling turns that abandoned state into a retryable failure.
        'stale_after_seconds' => (int) env('CONTENT_GENERATION_STALE_AFTER_SECONDS', 180),
    ],

    'discovery' => [
        // HikerAPI is the default public-data provider. ScrapeCreators can feed
        // the same normalized pipeline, Apify remains available as a fallback,
        // and mock keeps local development deterministic.
        'driver' => env('DISCOVERY_DRIVER', 'hiker'),
        'hiker' => [
            'api_key' => env('HIKER_API_KEY'),
            'base_url' => env('HIKER_BASE_URL', 'https://api.hikerapi.com'),
            'timeout' => (int) env('HIKER_TIMEOUT', 30),
            'retries' => (int) env('HIKER_RETRIES', 3),
            'retry_delay_ms' => (int) env('HIKER_RETRY_DELAY_MS', 500),
        ],
        'scrapecreators' => [
            'api_key' => env('SCRAPECREATORS_API_KEY'),
            'base_url' => env('SCRAPECREATORS_BASE_URL', 'https://api.scrapecreators.com'),
            'timeout' => (int) env('SCRAPECREATORS_TIMEOUT', 30),
            'retries' => (int) env('SCRAPECREATORS_RETRIES', 3),
            'retry_delay_ms' => (int) env('SCRAPECREATORS_RETRY_DELAY_MS', 500),
            // The profile endpoint supports provider-side caching. Three days
            // matches the account measurement cooldown and avoids duplicate cost.
            'cache_max_age' => env('SCRAPECREATORS_CACHE_MAX_AGE', '3d'),
        ],
        'apify' => [
            'token' => env('APIFY_TOKEN'),
            'actor' => env('APIFY_INSTAGRAM_ACTOR', 'apify~instagram-scraper'),
            // Scrapes whole accounts (real follower count + recent posts) so the
            // engagement rate can be measured per account.
            'profile_actor' => env('APIFY_PROFILE_ACTOR', 'apify~instagram-profile-scraper'),
            // Cost knob: Apify bills per result written to the dataset.
            'results_limit' => (int) env('APIFY_RESULTS_LIMIT', 30),
            // Re-scrape post URLs to recover likes/views (hashtag pages hide them).
            // Doubles the result count; set false for engagement-blind, half-price runs.
            'enrich_metrics' => (bool) env('APIFY_ENRICH_METRICS', true),
            'timeout' => (int) env('APIFY_TIMEOUT', 120),
        ],
        // Recent posts pulled per account to average the engagement rate over.
        'profile_posts' => (int) env('DISCOVERY_PROFILE_POSTS', 12),
        'search_query_limit' => (int) env('DISCOVERY_SEARCH_QUERY_LIMIT', 6),
        'search_results_per_query' => (int) env('DISCOVERY_SEARCH_RESULTS_PER_QUERY', 8),
        'seed_limit' => (int) env('DISCOVERY_SEED_LIMIT', 8),
        'related_per_seed' => (int) env('DISCOVERY_RELATED_PER_SEED', 6),
        // Legacy recovery cutoff used while discovery resumes accounts that were
        // never measured. Ongoing refreshes use config/instagram_scraping.php.
        'measure_cooldown_days' => (int) env('DISCOVERY_MEASURE_COOLDOWN_DAYS', 3),
        // Cap on accounts measured per job run, so a large niche can't blow the
        // Apify budget in one pass.
        'measure_batch' => (int) env('DISCOVERY_MEASURE_BATCH', 30),
        // Accounts per Apify call. A synchronous run is capped at five minutes and a
        // whole batch does not reliably fit, so the batch is scraped in chunks.
        'measure_chunk' => (int) env('DISCOVERY_MEASURE_CHUNK', 10),
        // How many hashtags an expansion produces and how long the cache holds.
        'hashtag_limit' => (int) env('DISCOVERY_HASHTAG_LIMIT', 10),
        'cache_days' => (int) env('DISCOVERY_CACHE_DAYS', 7),
        // A hashtag scraped within this window is skipped, so cost scales with the
        // number of distinct niches rather than the number of users.
        'cooldown_days' => (int) env('DISCOVERY_COOLDOWN_DAYS', 7),
        // The feed only ranks posts published inside this window. An older post
        // describes a niche that has already moved on, however well it did.
        'feed_window_days' => (int) env('DISCOVERY_FEED_WINDOW_DAYS', 30),
        'feed_size' => (int) env('DISCOVERY_FEED_SIZE', 24),
        // An explicit refresh re-scrapes a small relevant set instead of the whole
        // catalogue, so the button can find new posts without an unbounded bill.
        'refresh_creator_limit' => (int) env('DISCOVERY_REFRESH_CREATOR_LIMIT', 8),
        // Minimum outlier score to reach the feed: a post has to beat the account
        // that published it, not merely come from a large one.
        'min_outlier_score' => (float) env('DISCOVERY_MIN_OUTLIER_SCORE', 1.2),
        // Absolute floors, because the outlier score is a ratio and a ratio has no
        // sense of scale. An account whose median post gets 2 likes turns a 3-like
        // post into a 1.5x "outlier" — arithmetically true, worthless as a benchmark.
        // Nothing under these thresholds is evidence of anything.
        'min_followers' => (int) env('DISCOVERY_MIN_FOLLOWERS', 5000),
        'min_post_engagement' => (int) env('DISCOVERY_MIN_POST_ENGAGEMENT', 500),
        'safety' => [
            'enabled' => (bool) env('DISCOVERY_CONTENT_SAFETY_ENABLED', true),
            'use_openai' => (bool) env('DISCOVERY_CONTENT_SAFETY_USE_OPENAI', true),
            'fail_closed' => (bool) env('DISCOVERY_CONTENT_SAFETY_FAIL_CLOSED', true),
            'model' => env('DISCOVERY_CONTENT_SAFETY_MODEL', 'omni-moderation-latest'),
            'blocked_categories' => [
                'harassment', 'harassment/threatening', 'hate', 'hate/threatening',
                'illicit', 'illicit/violent', 'self-harm', 'self-harm/intent',
                'self-harm/instructions', 'sexual', 'sexual/minors', 'violence',
                'violence/graphic',
            ],
            'blocked_metadata_flags' => [
                'adult', 'explicit', 'is_adult', 'is_explicit', 'is_nsfw',
                'is_sensitive', 'mature', 'nsfw', 'sexual',
            ],
            // A deterministic first pass catches clear French and English policy
            // violations before any provider or model-specific signal is needed.
            'blocked_terms' => [
                'adult content', 'contenu adulte', 'contenu explicite', 'explicit content',
                'naked', 'nsfw', 'nude', 'nudes', 'nudite', 'onlyfans', 'porn',
                'porno', 'pornographie', 'pornographic', 'sex tape', 'sexual content',
                'topless',
                'bitch', 'connard', 'connasse', 'encule', 'enculee', 'fuck',
                'fils de pute', 'nique ta mere', 'pute', 'salope', 'shit', 'slut',
                'whore',
                'abruti', 'abrutie', 'debile', 'idiot', 'idiote', 'moron',
            ],
        ],
        'ranking' => [
            'weights' => [
                'outlier' => (float) env('FEED_WEIGHT_OUTLIER', 0.60),
                'reach' => (float) env('FEED_WEIGHT_REACH', 0.20),
                'recency' => (float) env('FEED_WEIGHT_RECENCY', 0.20),
            ],
            'outlier_ceiling' => (float) env('FEED_OUTLIER_CEILING', 3.0),
            'reach_ceiling' => (float) env('FEED_REACH_CEILING', 6.0),
        ],
        // Reach-bait tags. These are not niches — they are what accounts with no
        // audience post under in order to be seen, so scraping them returns spam by
        // construction. Stripped from every hashtag expansion.
        'blocked_hashtags' => [
            'explore', 'explorepage', 'explorer', 'f4f', 'follow', 'followforfollow',
            'followme', 'foryou', 'foryoupage', 'fyp', 'instadaily', 'instagood',
            'instagram', 'like4like', 'likeforlike', 'l4l', 'million', 'millionviews',
            'reel', 'reels', 'reelsinstagram', 'reelsvideo', 'trending', 'trendingreels',
            'viral', 'viralpost', 'viralreel', 'viralreels', 'viralvideo',
        ],
    ],

    'instagram_media_proxy' => [
        'disk' => env('INSTAGRAM_MEDIA_CACHE_DISK', 'local'),
        'cache_days' => (int) env('INSTAGRAM_MEDIA_CACHE_DAYS', 7),
        'browser_cache_hours' => (int) env('INSTAGRAM_MEDIA_BROWSER_CACHE_HOURS', 24),
        'signature_hours' => (int) env('INSTAGRAM_MEDIA_SIGNATURE_HOURS', 24),
        'timeout' => (int) env('INSTAGRAM_MEDIA_TIMEOUT', 15),
        'max_bytes' => (int) env('INSTAGRAM_MEDIA_MAX_BYTES', 10_485_760),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5'),
        // Remix drafting is a bounded creative task, so it uses a faster model
        // and avoids spending tokens on reasoning needed by harder workflows.
        'remix_model' => env('OPENAI_REMIX_MODEL', 'gpt-5.6-luna'),
        'remix_reasoning_effort' => env('OPENAI_REMIX_REASONING_EFFORT', 'none'),
        'remix_max_output_tokens' => (int) env('OPENAI_REMIX_MAX_OUTPUT_TOKENS', 2500),
        'request_timeout' => (int) env('OPENAI_TIMEOUT', 120),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
        'effort' => env('ANTHROPIC_EFFORT', 'medium'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 8000),
        'timeout' => (float) env('ANTHROPIC_TIMEOUT', 120),
    ],

    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'redirect_uri' => env('INSTAGRAM_REDIRECT_URI', env('APP_URL').'/instagram/callback'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'api_version' => env('INSTAGRAM_API_VERSION', 'v25.0'),
        'authorization_url' => env('INSTAGRAM_AUTHORIZATION_URL', 'https://www.instagram.com/oauth/authorize'),
        'token_url' => env('INSTAGRAM_TOKEN_URL', 'https://api.instagram.com/oauth/access_token'),
        'graph_url' => env('INSTAGRAM_GRAPH_URL', 'https://graph.instagram.com'),
        'scopes' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('INSTAGRAM_SCOPES', 'instagram_business_basic,instagram_business_content_publish,instagram_business_manage_insights'))
        ))),
        'media_limit' => (int) env('INSTAGRAM_MEDIA_LIMIT', 25),
    ],

];
