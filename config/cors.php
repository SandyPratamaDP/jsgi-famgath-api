<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],

    // Set CORS_ALLOWED_ORIGINS in .env to your Vercel URL.
    // Multiple origins: CORS_ALLOWED_ORIGINS=https://app.vercel.app,https://preview.vercel.app
    'allowed_origins' => array_filter(
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))
    ),

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 86400,

    // No session-based auth — credentials not needed.
    // Keep false to avoid wildcard+credentials CORS error.
    'supports_credentials' => false,
];
