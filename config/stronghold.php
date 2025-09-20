<?php

use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth Providers
    |--------------------------------------------------------------------------
    |
    | This array contains the OAuth providers that your application supports.
    | You may add any provider that is supported by Laravel Socialite.
    |
    */

    'providers' => [
        'github',
        'google',
        'facebook',
        'twitter',
        'linkedin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of Stronghold's features are optional. You may disable the features
    | by removing them from this array.
    |
    */

    'features' => [
        'confirm-new-location',
        'sign-in-notification',
        'socialite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile Photo Disk
    |--------------------------------------------------------------------------
    |
    | This configuration value determines the default disk that will be used
    | when storing profile photos. Typically this will be the "public" disk.
    |
    */

    'profile_photo_disk' => 'public',

    /*
    |--------------------------------------------------------------------------
    | New Location Confirmation
    |--------------------------------------------------------------------------
    |
    | These settings control how the new location confirmation feature works.
    |
    */

    'new_location_confirmation' => [
        'code_length' => 6,
        'code_expiration' => 30, // minutes
    ],
];
