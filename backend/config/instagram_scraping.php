<?php

return [
    // Scheduler passes are cheap database scans. Provider calls only happen in
    // the queued jobs selected by next_scrape_at and next_metrics_scrape_at.
    'scheduled' => (bool) env('INSTAGRAM_SCRAPING_SCHEDULED', false),
    'creator_batch' => (int) env('INSTAGRAM_SCRAPE_CREATOR_BATCH', 30),
    'metrics_creator_batch' => (int) env('INSTAGRAM_SCRAPE_METRICS_CREATOR_BATCH', 30),
    'metrics_posts_per_creator' => (int) env('INSTAGRAM_SCRAPE_METRICS_POSTS_PER_CREATOR', 24),

    'creator' => [
        'intervals_hours' => [
            'hot' => [
                (int) env('INSTAGRAM_SCRAPE_HOT_MIN_HOURS', 6),
                (int) env('INSTAGRAM_SCRAPE_HOT_MAX_HOURS', 12),
            ],
            'active' => [
                (int) env('INSTAGRAM_SCRAPE_ACTIVE_MIN_HOURS', 24),
                (int) env('INSTAGRAM_SCRAPE_ACTIVE_MAX_HOURS', 24),
            ],
            'warm' => [
                (int) env('INSTAGRAM_SCRAPE_WARM_MIN_HOURS', 48),
                (int) env('INSTAGRAM_SCRAPE_WARM_MAX_HOURS', 72),
            ],
            'cold' => [
                (int) env('INSTAGRAM_SCRAPE_COLD_MIN_HOURS', 120),
                (int) env('INSTAGRAM_SCRAPE_COLD_MAX_HOURS', 168),
            ],
        ],
        'thresholds' => [
            'hot' => (float) env('INSTAGRAM_SCRAPE_HOT_PRIORITY', 70),
            'active' => (float) env('INSTAGRAM_SCRAPE_ACTIVE_PRIORITY', 40),
            'warm' => (float) env('INSTAGRAM_SCRAPE_WARM_PRIORITY', 15),
        ],
        'weights' => [
            'user_selection' => (float) env('INSTAGRAM_SCRAPE_USER_SELECTION_WEIGHT', 10),
            'relevant_feed' => (float) env('INSTAGRAM_SCRAPE_RELEVANT_FEED_WEIGHT', 2),
            'approved_catalog' => (float) env('INSTAGRAM_SCRAPE_APPROVED_WEIGHT', 20),
            'posting_frequency' => (float) env('INSTAGRAM_SCRAPE_POSTING_FREQUENCY_WEIGHT', 20),
            'recent_post' => (float) env('INSTAGRAM_SCRAPE_RECENT_POST_WEIGHT', 20),
            'recent_outlier' => (float) env('INSTAGRAM_SCRAPE_RECENT_OUTLIER_WEIGHT', 10),
            'hot_post' => (float) env('INSTAGRAM_SCRAPE_HOT_POST_WEIGHT', 10),
        ],
        'failure_backoff_hours' => (int) env('INSTAGRAM_SCRAPE_FAILURE_BACKOFF_HOURS', 6),
        'failure_backoff_max_hours' => (int) env('INSTAGRAM_SCRAPE_FAILURE_BACKOFF_MAX_HOURS', 72),
    ],

    'posts' => [
        'intervals_hours' => [
            'first_day' => (int) env('INSTAGRAM_METRICS_FIRST_DAY_HOURS', 6),
            'days_one_to_three' => (int) env('INSTAGRAM_METRICS_DAYS_ONE_TO_THREE_HOURS', 24),
            'days_four_to_seven' => (int) env('INSTAGRAM_METRICS_DAYS_FOUR_TO_SEVEN_HOURS', 48),
            'hot_min' => (int) env('INSTAGRAM_METRICS_HOT_MIN_HOURS', 3),
            'hot_max' => (int) env('INSTAGRAM_METRICS_HOT_MAX_HOURS', 6),
            'warm' => (int) env('INSTAGRAM_METRICS_WARM_HOURS', 24),
            'cold' => (int) env('INSTAGRAM_METRICS_COLD_HOURS', 72),
            'exceptional' => (int) env('INSTAGRAM_METRICS_EXCEPTIONAL_HOURS', 168),
            'failure_backoff' => (int) env('INSTAGRAM_METRICS_FAILURE_BACKOFF_HOURS', 12),
        ],
        'hot_outlier_score' => (float) env('INSTAGRAM_METRICS_HOT_OUTLIER_SCORE', 2.0),
        'exceptional_outlier_score' => (float) env('INSTAGRAM_METRICS_EXCEPTIONAL_OUTLIER_SCORE', 3.0),
        'hot_velocity_multiplier' => (float) env('INSTAGRAM_METRICS_HOT_VELOCITY_MULTIPLIER', 2.0),
        'warm_velocity_multiplier' => (float) env('INSTAGRAM_METRICS_WARM_VELOCITY_MULTIPLIER', 0.5),
        'baseline_maturity_hours' => (int) env('INSTAGRAM_METRICS_BASELINE_MATURITY_HOURS', 72),
        'meaningful_growth_rate' => (float) env('INSTAGRAM_METRICS_MEANINGFUL_GROWTH_RATE', 0.05),
    ],

    'snapshots' => [
        // Raw points remain useful while a post is actively growing. Afterwards
        // the prune command keeps one point per UTC day, then expires the series.
        'raw_days' => (int) env('INSTAGRAM_METRICS_SNAPSHOT_RAW_DAYS', 30),
        'retention_days' => (int) env('INSTAGRAM_METRICS_SNAPSHOT_RETENTION_DAYS', 365),
    ],
];
