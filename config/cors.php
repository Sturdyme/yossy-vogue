<?php

return [
'paths' => ['api/*', 'sanctum/csrf-cookie'],

'allowed_methods' => ['*'], // Allows POST, GET, OPTIONS, etc.

'allowed_origins' => [
    'https://yossy-vogue.vercel.app', 
    'http://localhost:5174'
],

'allowed_origins_patterns' => [],

'allowed_headers' => ['*'], // This is crucial for Axios

'exposed_headers' => [],

'max_age' => 0,

'supports_credentials' => true, // Set to true if you are using cookies/sessions

];