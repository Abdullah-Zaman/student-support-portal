<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded admin credentials
    |--------------------------------------------------------------------------
    |
    | Local development defaults. Override via the SEED_* environment
    | variables for any real environment. AdminUserSeeder refuses to run
    | with these default passwords when APP_ENV=production.
    |
    */

    'default_password' => 'Password123!',

    'super_admin' => [
        'email' => env('SEED_SUPER_ADMIN_EMAIL', 'superadmin@superior.edu.pk'),
        'password' => env('SEED_SUPER_ADMIN_PASSWORD', 'Password123!'),
    ],

    'admin_officer' => [
        'email' => env('SEED_ADMIN_OFFICER_EMAIL', 'admin.se@superior.edu.pk'),
        'password' => env('SEED_ADMIN_OFFICER_PASSWORD', 'Password123!'),
    ],

    'hod' => [
        'email' => env('SEED_HOD_EMAIL', 'hod.se@superior.edu.pk'),
        'password' => env('SEED_HOD_PASSWORD', 'Password123!'),
    ],

    'dean' => [
        'email' => env('SEED_DEAN_EMAIL', 'dean@superior.edu.pk'),
        'password' => env('SEED_DEAN_PASSWORD', 'Password123!'),
    ],

];
