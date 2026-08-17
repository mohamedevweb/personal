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
            explode(',', env('INSTAGRAM_SCOPES', 'instagram_business_basic,instagram_business_manage_insights'))
        ))),
        'media_limit' => (int) env('INSTAGRAM_MEDIA_LIMIT', 25),
    ],

];
