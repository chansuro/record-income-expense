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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'phone' => env('TWILIO_PHONE_NUMBER')
    ],
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'publishable_key' => env('STRIPR_PUBLISHABLE_KEY'),
        'price' => env('STRIP_PRICE'),
        'webhook' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'googlecloud' =>[
        'key' => env('GOOGLE_CLOUD_KEY_FILE'),
        'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
        'firebase' => env('FIREBASE_CREDENTIALS'),
    ],
    'tax' =>[
        'weekly_personal_allowance' => env('WEEKLY_PERSONAL_ALLOWANCE'),
        'yearly_personal_allowance' => env('YEARLY_PERSONAL_ALLOWANCE')
    ]

];
