<?php

return [
    'minimum_age' => 21,
    'currency' => env('STORE_CURRENCY', 'USD'),
    'payment_link' => 'https://link.payway.com.kh/ABAPAYtr5100589',
    'delivery_fee_cents' => (int) env('STORE_DELIVERY_FEE_CENTS', 150),
    'checkout_session_lifetime_minutes' => (int) env('CHECKOUT_SESSION_LIFETIME_MINUTES', 15),
    'checkout_session_prune_after_minutes' => (int) env('CHECKOUT_SESSION_PRUNE_AFTER_MINUTES', 1440),
    'telegram_username' => env('TELEGRAM_USERNAME', 'b_imv'),
    'admin' => [
        'name' => env('ADMIN_NAME', 'Store Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
