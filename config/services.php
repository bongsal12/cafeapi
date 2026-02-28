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
    'account_id' => env('BAKONG_ACCOUNT_ID'),
    'merchant_name' => env('BAKONG_MERCHANT_NAME', 'Khmer cafe'),
    'merchant_city' => env('BAKONG_MERCHANT_CITY', 'Phnom Penh'),

    // USD = 840, KHR = 116
    'currency' => (int) env('BAKONG_CURRENCY', 840),

    'qr_expire_seconds' => (int) env('BAKONG_QR_EXPIRE_SECONDS', 300),
    'debug' => (bool) env('BAKONG_DEBUG', false),
],





];
