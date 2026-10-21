<?php

$corsAllowedOrigins = getenv('CORS_ALLOWED_ORIGINS');
$corsAllowedOrigins = $corsAllowedOrigins !== false && $corsAllowedOrigins !== ''
    ? $corsAllowedOrigins
    : 'https://yossy-vogue.vercel.app,http://localhost:5173,http://localhost:5174';

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Comma-separated list in the environment, for example:
    // CORS_ALLOWED_ORIGINS=https://yossy-vogue.vercel.app,http://localhost:5173,http://localhost:5174
    // Keep production and the default Vite dev ports working if the variable is missing.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', $corsAllowedOrigins)
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    // Browsers may cache the preflight answer for an hour
    // instead of asking before every single request.
    'max_age' => 3600,

    // The app authenticates with Bearer tokens, not cookies.
    'supports_credentials' => false,

];
