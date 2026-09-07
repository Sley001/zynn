<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'payment_group_id' => env('TELEGRAM_PAYMENT_GROUP_ID'),
    'aba_sender_id' => env('TELEGRAM_ABA_SENDER_ID'),
    'admin_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): ?int => is_numeric(trim($id)) ? (int) trim($id) : null,
        explode(',', (string) env('TELEGRAM_ADMIN_USER_IDS', ''))
    ), static fn (?int $id): bool => $id !== null)),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'mode' => env('TELEGRAM_MODE', 'disabled'),
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
    'api_base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),
    'payment_merchant_name' => env('TELEGRAM_PAYMENT_MERCHANT_NAME', 'LIV SEANGLY'),
    'payment_match_grace_minutes' => (int) env('TELEGRAM_PAYMENT_MATCH_GRACE_MINUTES', 15),
];
