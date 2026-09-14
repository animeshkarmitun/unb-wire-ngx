<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Kill Switch
    |--------------------------------------------------------------------------
    |
    | When false, all rate limiting is disabled. Useful for testing.
    |
    */
    'enabled' => env('RATE_LIMIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Development Multiplier
    |--------------------------------------------------------------------------
    |
    | Multiplied against all limit values in non-production environments.
    | Set to 1 for production-strict behavior.
    |
    */
    'dev_multiplier' => env('RATE_LIMIT_DEV_MULTIPLIER', 1),

    /*
    |--------------------------------------------------------------------------
    | Endpoint Rate Limits
    |--------------------------------------------------------------------------
    |
    | Production values. Each limit has 'attempts' (max requests) and
    | 'decay' (window in minutes).
    |
    */
    'limits' => [

        'portal_login' => [
            'attempts' => 10,
            'decay' => 1,
        ],

        'portal_forgot_pw' => [
            'attempts' => 3,
            'decay' => 1,
        ],

        'portal_reset_pw' => [
            'attempts' => 5,
            'decay' => 1,
        ],

        'portal_feed' => [
            'attempts' => 60,
            'decay' => 1,
        ],

        'portal_story' => [
            'attempts' => 120,
            'decay' => 1,
        ],

        'portal_search_token' => [
            'attempts' => 60,
            'decay' => 1,
        ],

        'portal_session' => [
            'attempts' => 60,
            'decay' => 1,
        ],

        'client_feed' => [
            'attempts' => 60,
            'decay' => 1,
        ],

        'media_download' => [
            'attempts' => 60,
            'decay' => 1,
        ],

        'staff_upload' => [
            'attempts' => 60,
            'decay' => 1,
        ],

        'ai_assist' => [
            'attempts' => 30,
            'decay' => 1,
        ],

        'staff_login' => [
            'attempts' => 5,
            'decay' => 1,
        ],

        'email_verify' => [
            'attempts' => 6,
            'decay' => 1,
        ],

    ],

];
