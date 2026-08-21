<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT'),
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null
        ? 0.1
        : (float) env('SENTRY_TRACES_SAMPLE_RATE'),
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),
    'ignore_transactions' => [
        '/up',
    ],
];
