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

    'meeting_recorder' => [
        'url' => env('MEETING_RECORDER_URL'),
        'api_url' => env('MEETING_RECORDER_API_URL'),
        'login_path' => env('MEETING_RECORDER_LOGIN_PATH', '/api/v1/auth/login'),
        'redirect_path' => env('MEETING_RECORDER_REDIRECT_PATH', '/login'),
        'username' => env('MEETING_RECORDER_USERNAME'),
        'password' => env('MEETING_RECORDER_PASSWORD'),
        'shared_bearer_token' => env('MEETING_RECORDER_SHARED_BEARER_TOKEN'),
    ],

    'google_maps' => [
        'browser_key' => env('GOOGLE_MAPS_BROWSER_API_KEY'),
    ],

];
