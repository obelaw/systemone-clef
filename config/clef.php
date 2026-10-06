<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudflare Workers AI Account ID
    |--------------------------------------------------------------------------
    |
    | The unique Cloudflare account identifier required for Workers AI routing:
    | https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/run/...
    |
    */
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare API Token
    |--------------------------------------------------------------------------
    |
    | Bearer token authorization credential with Workers AI permissions.
    |
    */
    'api_token' => env('CLOUDFLARE_API_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Default AI Decision Model
    |--------------------------------------------------------------------------
    |
    | The default Workers AI model tag identifier.
    | Typically '@cf/cloudflare/clef' or '@cf/cloudflare/clef-flash'.
    |
    */
    'model' => env('CLOUDFLARE_AI_MODEL', '@cf/cloudflare/clef'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare API Base URL
    |--------------------------------------------------------------------------
    |
    | Base URL of Cloudflare Client API v4.
    |
    */
    'base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum connection and response wait time in seconds.
    |
    */
    'timeout' => (int) env('CLOUDFLARE_AI_TIMEOUT', 30),
];
