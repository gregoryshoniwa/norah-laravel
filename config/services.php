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
    'innbucks' => [
        'url' => env('INNBUCKS_URL'),
        'username' => env('INNBUCKS_USERNAME'),
        'password' => env('INNBUCKS_PASSWORD'),
        'key' => env('INNBUCKS_KEY'),
    ],

    'ecocash' => [
        'url' => env('ECOCASH_URL'),
        'username' => env('ECOCASH_USERNAME'),
        'password' => env('ECOCASH_PASSWORD'),
    ],

];
