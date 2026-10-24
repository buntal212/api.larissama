<?php

$allowedOrigins = array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('LARISSAMA_CORS_ALLOWED_ORIGINS', 'http://localhost:9000,http://localhost:9201')),
));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'OPTIONS'],
    'allowed_origins' => array_values($allowedOrigins),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key'],
    'exposed_headers' => ['Date'],
    'max_age' => 600,
    'supports_credentials' => false,
];
