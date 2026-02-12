<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Authentication
    |--------------------------------------------------------------------------
    |
    | Credentials can be set via .env (priority) or managed through
    | the Filament admin panel (stored encrypted in DB as fallback).
    |
    */

    'email' => env('CLOUDFLARE_EMAIL'),
    'api_key' => env('CLOUDFLARE_API_KEY'),
    'token' => env('CLOUDFLARE_TOKEN'),
    'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | API Response Caching
    |--------------------------------------------------------------------------
    |
    | Cache TTL (in seconds) for Cloudflare API responses.
    | Set to 0 to disable caching.
    |
    */

    'cache' => [
        'ttl' => (int) env('CLOUDFLARE_CACHE_TTL', 300),
        'prefix' => 'cloudflare',
    ],

];
