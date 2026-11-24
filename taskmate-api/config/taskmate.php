<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CV Uploads
    |--------------------------------------------------------------------------
    |
    | CVs are stored on the private "local" disk and only served through an
    | authenticated endpoint. The maximum file size is expressed in kilobytes.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Social Authentication
    |--------------------------------------------------------------------------
    |
    | Providers enabled for Laravel Socialite. Each provider must also have
    | credentials configured in config/services.php and the .env file.
    |
    */

    'social_providers' => ['google', 'github'],

    'cv_max_kb' => (int) env('TASKMATE_CV_MAX_KB', 5120),

    'cv_mimes' => ['pdf', 'doc', 'docx'],

    'cv_mimetypes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

];
