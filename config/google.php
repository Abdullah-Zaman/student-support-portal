<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staff Google sign-in
    |--------------------------------------------------------------------------
    |
    | Comma-separated email domains whose Google accounts may sign in as
    | staff (Admin Officer, HOD, Dean, Super Admin). Students never sign
    | in with Google.
    |
    */

    'staff_domains' => array_values(array_filter(array_map(
        fn (string $domain) => strtolower(trim($domain)),
        explode(',', (string) env('STAFF_GOOGLE_DOMAINS', 'superior.edu.pk')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Staff password login
    |--------------------------------------------------------------------------
    |
    | When false, Admin Officers, HODs and Deans can only sign in with
    | Google. The Super Admin always keeps password login as a fallback.
    | Defaults to true everywhere except production.
    |
    */

    'staff_password_login' => (bool) env('STAFF_PASSWORD_LOGIN', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Email mismatch (local testing only)
    |--------------------------------------------------------------------------
    |
    | When true and the app is not in production, a staff member may
    | connect a Google account whose address differs from their portal
    | email (e.g. a personal Gmail for local testing). Always off in
    | production, whatever this is set to.
    |
    */

    'allow_email_mismatch' => (bool) env('GOOGLE_ALLOW_EMAIL_MISMATCH', false),

    /*
    |--------------------------------------------------------------------------
    | Forward email attachments
    |--------------------------------------------------------------------------
    |
    | Most raw bytes of application attachments put in one forward email.
    | Base64 adds about a third and Gmail's limit is 25 MB, so 15 MB keeps
    | a safe margin. Files beyond it are listed by name instead.
    |
    */

    'forward_attachment_limit_bytes' => 15 * 1024 * 1024,

];
