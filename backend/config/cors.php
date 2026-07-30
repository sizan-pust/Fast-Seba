<?php

$frontendUrls = array_values(array_filter(array_map(
    static fn (string $url): string => rtrim(trim($url), '/'),
    explode(',', (string) env(
        'FRONTEND_URLS',
        'http://127.0.0.1:3000,http://localhost:3000,https://fastsheba.com.bd,https://www.fastsheba.com.bd'
    ))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $frontendUrls,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
