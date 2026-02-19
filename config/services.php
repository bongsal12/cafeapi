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

  // config/services.php

'bakong' => [
    'token' => env('BAKONG_TOKEN'),
    'base_url' => env('BAKONG_BASE_URL', 'https://api-bakong.nbc.gov.kh'),

    // You MUST be able to change this if Bakong path differs
    'generate_path' => env('BAKONG_GENERATE_PATH', '/api/v1/khqr/generate'),
    'check_path' => env('BAKONG_CHECK_PATH', '/api/v1/khqr/check'),

    // Some Bakong endpoints may be GET (your nginx 405 indicates mismatch)
    'generate_method' => env('BAKONG_GENERATE_METHOD', 'POST'), // POST|GET
    'check_method' => env('BAKONG_CHECK_METHOD', 'POST'),       // POST|GET

    'merchant_name' => env('BAKONG_MERCHANT_NAME', 'Bo Coffee'),
    'merchant_city' => env('BAKONG_MERCHANT_CITY', 'Phnom Penh'),
    'account_id' => env('BAKONG_ACCOUNT_ID'),
    'mcc' => env('BAKONG_MCC', '5999'),
    'country' => env('BAKONG_COUNTRY', 'KH'),
    'currency' => (int) env('BAKONG_CURRENCY', 840), // USD=840
    'qr_expire_seconds' => (int) env('BAKONG_QR_EXPIRE_SECONDS', 600),

    // Debug
    'debug' => (bool) env('BAKONG_DEBUG', false),
],



];
