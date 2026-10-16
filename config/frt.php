<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Owner account
    |--------------------------------------------------------------------------
    |
    | Used by `php artisan db:seed` to create the first owner account.
    | Set OWNER_PASSWORD in production and change it after the first login.
    |
    */

    'owner' => [
        'username' => env('OWNER_USERNAME', 'owner'),
        'name' => env('OWNER_NAME', 'Danila Nice Fabriga'),
        'password' => env('OWNER_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard sign-in limit
    |--------------------------------------------------------------------------
    |
    | The owner must log in again this many hours after signing in, even if
    | the dashboard is still in use. Being idle for SESSION_LIFETIME minutes
    | (default 120) also ends the session.
    |
    */

    'session_max_hours' => (int) env('OWNER_SESSION_HOURS', 8),

];
