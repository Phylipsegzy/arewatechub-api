<?php

// Drop this in config/cors.php.
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Live
        'https://app.arewatechub.com.ng',
        // Local dev — keep these so you can still develop against
        // localhost while the live site is up. Remove later if you want
        // local dev to only hit a staging API instead.
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
