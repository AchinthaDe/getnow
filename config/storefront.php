<?php

declare(strict_types=1);

return [
    'name' => env('STOREFRONT_NAME', 'GetNow'),
    'driver' => env('STOREFRONT_DRIVER', 'demo'),
    'api_url' => env('STOREFRONT_API_URL', 'http://localhost:8001/api/v1'),
    'timeout' => (int) env('STOREFRONT_API_TIMEOUT', 10),
    'delivery_fee_minor' => (int) env('STOREFRONT_DELIVERY_FEE_MINOR', 45000),
    'free_delivery_minor' => (int) env('STOREFRONT_FREE_DELIVERY_MINOR', 2500000),
    'currency' => 'LKR',
    'country_tagline' => 'Sri Lanka · Prices in LKR',
    'demo_email' => 'demo@getnow.test',
    'demo_password' => 'GetNowDemo!2026',
    // Adapt endpoint paths here when connecting the separate Laravel backend.
    'endpoints' => [
        'products' => '/products',
        'categories' => '/categories',
        'login' => '/auth/login',
        'register' => '/auth/register',
        'forgot' => '/auth/forgot-password',
        'reset' => '/auth/reset-password',
        'me' => '/auth/me',
        'logout' => '/auth/logout',
        'orders' => '/orders',
    ],
];
