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

    'recaptcha' => [
        'enabled' => env('RECAPTCHA_ENABLED', false),
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'onlyoffice' => [
        'enabled' => env('ONLYOFFICE_ENABLED', false),
        'url' => rtrim((string) env('ONLYOFFICE_URL'), '/'),
        'jwt_secret' => env('ONLYOFFICE_JWT_SECRET'),
    ],

    'sso' => [
        'enabled' => env('IS_USING_SSO', false),
        'client_id' => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        'base_url' => env('SSO_BASE_URL', 'http://localhost:8111'),
        'redirect_uri' => env('SSO_REDIRECT_URI', 'http://localhost:8999/auth/callback'),
        'api_secret' => env('SSO_API_SECRET'),
        'verify_ssl' => env('SSO_VERIFY_SSL', true),
    ],

];
