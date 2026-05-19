<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://pos-api.test',     // contoh domain HTTPS lokal Laragon
        'https://localhost:5173',   // FE dev (ubah sesuai)
        'https://127.0.0.1:5173',
        'http://localhost:8000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
