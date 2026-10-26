<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration number format
    |--------------------------------------------------------------------------
    |
    | Example: SU92-BSSEM-F22-171. {programs} is replaced at runtime by
    | the codes of the programs open for registration (see the programs
    | table, managed by the Super Admin). Capture group 1 is the program
    | code, group 2 is the batch code (F22 = Fall 2022, S23 = Spring 2023).
    |
    */

    'registration_no_pattern' => env('STUDENT_REGISTRATION_NO_PATTERN', '/^SU\d{2}-({programs})[ME]-([FS]\d{2})-\d{1,4}$/'),

    /*
    |--------------------------------------------------------------------------
    | University email domain
    |--------------------------------------------------------------------------
    */

    'email_domain' => env('STUDENT_EMAIL_DOMAIN', '@superior.edu.pk'),

    /*
    |--------------------------------------------------------------------------
    | Email must match registration number
    |--------------------------------------------------------------------------
    |
    | When enabled, the university email must be the lowercased
    | registration number plus the email domain above.
    |
    */

    'enforce_email_matches_registration_no' => env('STUDENT_EMAIL_MATCHES_REGISTRATION_NO', true),

];
