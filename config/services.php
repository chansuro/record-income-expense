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
        'yearly_personal_allowance' => env('YEARLY_PERSONAL_ALLOWANCE'),
        'mileage_celling' => 100000,
        'mileage_rate_within_celling' => 0.45,
        'mileage_rate_above_celling' => 0.25,
    ],
    'subscription' =>[
        'price' => env('SUBSCRIPTION_PLAN_AMOUNT')
    ],
    'openai' =>[
        'key'=> env('OPEN_AI_KEY'),
        'model'=>env('OPENAI_MODEL'),
        'base_url'=>env('OPENAI_BASE_URL'),
        'timeout'=>env('OPENAI_TIMEOUT'),
        'max_history'=>env('OPENAI_MAX_HISTORY'),
        'system_prompt'=>env('OPENAI_SYSTEM_PROMPT')
    ],
    'hmrc' => [
    'environment' => env('HMRC_ENV', 'sandbox'),
    'client_id' => env('HMRC_CLIENT_ID'),
    'client_secret' => env('HMRC_CLIENT_SECRET'),
    'redirect_uri' => env(
        'HMRC_REDIRECT_URI',
        'http://localhost:8000/api/hmrc/callback'
    ),
    'arn' => env('HMRC_ARN'),
    'scopes' => env(
        'HMRC_SCOPES',
        'read:self-assessment write:self-assessment'
    ),
    'base_url' => env(
        'HMRC_BASE_URL',
        'https://test-api.service.hmrc.gov.uk'
    ),

    'auth_url' => env(
        'HMRC_AUTH_URL',
        'https://test-www.tax.service.gov.uk'
    ),
],

];
