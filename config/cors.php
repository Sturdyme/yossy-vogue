<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'register', 'logout'],

    'allowed_methods' => ['*'],

    // Add your ACTUAL Vercel URL here
    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174', // Added for your Vite app
        'https://yossy-vogue.vercel.app/'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // SET THIS TO TRUE for Sign-in/Register to work correctly
    'supports_credentials' => true,

];