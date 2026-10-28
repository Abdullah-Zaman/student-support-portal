<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Test user creation
    |--------------------------------------------------------------------------
    |
    | Enables the Super Admin's "Create test user" tool, which makes
    | verified, active accounts with any email domain and no emails sent.
    | For local testing only: it is also disabled whenever the app runs in
    | production, whatever this flag says. Leave false everywhere else.
    |
    */

    'allow_test_user_creation' => (bool) env('ALLOW_TEST_USER_CREATION', false),

];
