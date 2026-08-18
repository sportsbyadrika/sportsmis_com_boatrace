<?php
// Application configuration. SportsMIS Regatta is a sibling product to
// SportsMIS: same stack and visual language, its own domain and database.
return [
    'name'        => 'SportsMIS Regatta',
    'brand'       => 'SportsMIS',          // parent brand shown in the lockup
    'sub_brand'   => 'Regatta',            // boat-race sub-brand mark
    'url'         => getenv('APP_URL') ?: 'https://regatta.sportsmis.com',
    'home_url'    => 'https://sportsmis.com',
    'env'         => getenv('APP_ENV') ?: 'production',
    'debug'       => (getenv('APP_ENV') === 'local'),
    'timezone'    => 'Asia/Kolkata',
    'locale'      => 'en',
    'secret'      => getenv('APP_SECRET') ?: 'change-this-secret-key-in-production',

    'session' => [
        'name'     => 'sportsmis_regatta_session',
        'lifetime' => 7200,
    ],

    'upload' => [
        'path'       => __DIR__ . '/../public/assets/uploads/',
        'url'        => '/assets/uploads/',
        'max_size'   => 7 * 1024 * 1024, // 7 MB
        'photo_size' => 7 * 1024 * 1024, // 7 MB
        'allowed'    => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        'img_only'   => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    'mail' => [
        'host'         => getenv('MAIL_HOST')         ?: 'smtp.gmail.com',
        'port'         => getenv('MAIL_PORT')         ?: 587,
        'username'     => getenv('MAIL_USERNAME')     ?: '',
        'password'     => getenv('MAIL_PASSWORD')     ?: '',
        'encryption'   => getenv('MAIL_ENCRYPTION')   ?: 'tls',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@sportsmis.com',
        'from_name'    => getenv('MAIL_FROM_NAME')    ?: 'SportsMIS Regatta',
    ],

    // Display screens. The chroma colour is what the YouTube overlay paints
    // behind the result cards so a vision mixer can key it out; operators can
    // still override it per-request with ?chroma=#rrggbb.
    'display' => [
        'chroma'          => getenv('DISPLAY_CHROMA_COLOR') ?: '#00b140',
        'slide_seconds'   => 9,   // LED wall: seconds per slide
        'refresh_seconds' => 60,  // LED wall: full page refresh cadence
    ],
];
