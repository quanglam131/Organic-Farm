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

    // Distance from the farm to the customer for the shipping fee (Tuần 6).
    // With a Google key the Distance Matrix API gives the driving distance; without one, the address is
    // geocoded with OpenStreetMap Nominatim and the straight-line distance is multiplied by road_factor.
    'maps' => [
        'google_key' => env('GOOGLE_MAPS_API_KEY'),
        'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
        'road_factor' => (float) env('SHIPPING_ROAD_FACTOR', 1.3),
        'timeout' => 5,
    ],

];
