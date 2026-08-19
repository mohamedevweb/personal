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
    ],

    'discovery' => [
        // "apify" scrapes real niche content; "mock" returns deterministic sample
        // posts so the feed is testable without a paid Apify run.
        'driver' => env('DISCOVERY_DRIVER', 'mock'),
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
        // A creator re-measured within this window is skipped, so profile-scrape
        // cost scales with the number of tracked accounts, not syncs.
        'measure_cooldown_days' => (int) env('DISCOVERY_MEASURE_COOLDOWN_DAYS', 3),
        // Cap on accounts measured per job run, so a large niche can't blow the
        // Apify budget in one pass.
        'measure_batch' => (int) env('DISCOVERY_MEASURE_BATCH', 30),
        // How many hashtags an expansion produces and how long the cache holds.
        'hashtag_limit' => (int) env('DISCOVERY_HASHTAG_LIMIT', 10),
        'cache_days' => (int) env('DISCOVERY_CACHE_DAYS', 7),
        // A hashtag scraped within this window is skipped, so cost scales with the
        // number of distinct niches rather than the number of users.
        'cooldown_days' => (int) env('DISCOVERY_COOLDOWN_DAYS', 7),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5'),
        // Reasoning models only ("minimal" through "high"). Leave empty for the
        // rest; sending it to a non-reasoning model is rejected.
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', ''),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 8000),
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
