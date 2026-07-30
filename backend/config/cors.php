<?php

$frontendUrls = array_values(array_filter(array_map(
    static fn (string $url): string => rtrim(trim($url), '/'),
    explode(',', (string) env('FRONTEND_URLS', 'http://localhost:3000'))
)));

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => $frontendUrls,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,
];
