<?php

return [
    'cas' => [
        'base_url' => env('STJ_CAS_BASE_URL', 'https://cas.stjacks.com'),
        'validate_url' => env('STJ_CAS_VALIDATE_URL', 'https://cas.stjacks.com/API/validateUser'),
        'signature' => env('STJ_CAS_SIGNATURE'),
        'origin' => env('STJ_CAS_ORIGIN', env('APP_URL', 'http://localhost')),
        'callback_url' => env('STJ_CAS_CALLBACK_URL'),
        'timeout' => (int) env('STJ_CAS_TIMEOUT', 12),
    ],
    'api' => [
        'base_url' => env('STJ_API_BASE_URL', 'http://127.0.0.1:8002/api'),
        'dashboard_token' => env('STJ_API_DASHBOARD_TOKEN'),
        'timeout' => (int) env('STJ_API_TIMEOUT', 15),
    ],
];
