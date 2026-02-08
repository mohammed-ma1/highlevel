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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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
    'external_auth' => [
        'token_url' => env('EXTERNAL_TOKEN_URL', 'https://services.leadconnectorhq.com/oauth/token'),
        'client_id' => env('EXTERNAL_CLIENT_ID', 'your-client-id'),
        'client_secret' => env('EXTERNAL_CLIENT_SECRET', 'your-client-secret'),
        'redirect_uri' => env('EXTERNAL_REDIRECT_URI', 'https://dashboard.mediasolution.io/connect'),
    ],

    // Separate OAuth app for UPayments integration
    'external_auth_upayments' => [
        'token_url' => env('UPAYMENTS_EXTERNAL_TOKEN_URL', 'https://services.leadconnectorhq.com/oauth/token'),
        'client_id' => env('UPAYMENTS_EXTERNAL_CLIENT_ID', '6976c8cafbba5546628848b5-ml6en9hl'),
        'client_secret' => env('UPAYMENTS_EXTERNAL_CLIENT_SECRET', 'fd54e5a8-aacb-43a8-9d55-8b4099be2116'),
        'redirect_uri' => env('UPAYMENTS_EXTERNAL_REDIRECT_URI', 'https://dashboard.mediasolution.io/uconnect'),
    ],

    'upayments' => [
        'test_base_url' => env('UPAYMENTS_TEST_BASE_URL', 'https://sandboxapi.upayments.com/api/v1/'),
        'live_base_url' => env('UPAYMENTS_LIVE_BASE_URL', 'https://api.upayments.com/api/v1/'),
    ],

];
