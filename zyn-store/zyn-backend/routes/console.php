<?php

use App\Services\CheckoutSessionService;
use App\Services\TelegramBotClient;
use App\Services\TelegramPaymentAlertService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('zyn:status', function (): void {
    $this->info('ZYN Store API is ready.');
})->purpose('Check the ZYN Store API application status');

Artisan::command('checkout-sessions:prune', function (CheckoutSessionService $checkoutSessions): void {
    $result = $checkoutSessions->expireAndPrune();

    $this->info("Expired {$result['expired']} checkout session(s).");
    $this->info("Pruned {$result['pruned']} abandoned checkout session(s).");
})->purpose('Expire and prune abandoned checkout payment sessions');

Artisan::command('telegram:payments:poll {--once : Process one getUpdates response and exit} {--timeout=25 : Telegram long-poll timeout in seconds}', function (
    TelegramBotClient $telegram,
    TelegramPaymentAlertService $alerts,
): int {
    $missing = [];

    if (config('telegram.mode') !== 'polling') {
        $missing[] = 'TELEGRAM_MODE=polling';
    }

    if (! $telegram->isConfigured()) {
        $missing[] = 'TELEGRAM_BOT_TOKEN';
    }

    if (! is_numeric(config('telegram.payment_group_id'))) {
        $missing[] = 'TELEGRAM_PAYMENT_GROUP_ID';
    }

    if (! is_numeric(config('telegram.aba_sender_id'))) {
        $missing[] = 'TELEGRAM_ABA_SENDER_ID';
    }

    if ($missing !== []) {
        $this->error('Telegram payment polling is not configured in the backend runtime.');
        $this->line('Set these in C:\zyn-store\zyn-backend\.env, then run php artisan optimize:clear:');
        foreach ($missing as $setting) {
            $this->line('- '.$setting);
        }
        $this->line('Do not put these only in .env.example or frontend .env.local.');

        return Command::FAILURE;
    }

    $cacheKey = 'telegram_payments_poll_offset';
    $timeout = max(0, (int) $this->option('timeout'));

    do {
        $offset = Cache::get($cacheKey);

        try {
            $updates = $telegram->getUpdates(is_numeric($offset) ? (int) $offset : null, $timeout);
        } catch (Throwable $exception) {
            $this->error('Telegram API request failed: '.$exception->getMessage());
            $this->line('Check the bot token, internet connection, Telegram access, and that only one poller is running.');

            return Command::FAILURE;
        }

        foreach ($updates as $update) {
            $alerts->processUpdate($update);

            if (isset($update['update_id']) && is_numeric($update['update_id'])) {
                Cache::put($cacheKey, ((int) $update['update_id']) + 1);
            }
        }

        $this->info('Processed '.count($updates).' Telegram update(s).');
    } while (! $this->option('once'));

    return Command::SUCCESS;
})->purpose('Safely poll Telegram for ABA payment alerts in local development');

Artisan::command('telegram:diagnose-updates {--limit=5 : Maximum updates to inspect} {--timeout=0 : Telegram polling timeout in seconds}', function (
    TelegramBotClient $telegram,
): int {
    if (! $telegram->isConfigured()) {
        $this->error('TELEGRAM_BOT_TOKEN is not configured.');

        return Command::FAILURE;
    }

    try {
        $updates = $telegram->getUpdates(null, max(0, (int) $this->option('timeout')), max(1, (int) $this->option('limit')));
    } catch (Throwable $exception) {
        $this->error('Telegram API request failed: '.$exception->getMessage());
        $this->line('Check the bot token, internet connection, Telegram access, and that only one poller is running.');

        return Command::FAILURE;
    }

    $rows = collect($updates)->map(function (array $update): array {
        $message = $update['message']
            ?? $update['edited_message']
            ?? $update['channel_post']
            ?? $update['edited_channel_post']
            ?? null;
        $callback = $update['callback_query'] ?? null;
        $replyToMessage = is_array($message) ? ($message['reply_to_message'] ?? null) : null;
        $replyToMessage = is_array($replyToMessage) ? $replyToMessage : null;

        return [
            'chat.id' => data_get($message, 'chat.id') ?? data_get($callback, 'message.chat.id'),
            'from.id' => data_get($message, 'from.id') ?? data_get($callback, 'from.id'),
            'sender_chat.id' => data_get($message, 'sender_chat.id'),
            'message_id' => data_get($message, 'message_id') ?? data_get($callback, 'message.message_id'),
            'reply_to.from.id' => data_get($replyToMessage, 'from.id'),
            'reply_to.sender_chat.id' => data_get($replyToMessage, 'sender_chat.id'),
            'reply_to_has_text' => is_string(data_get($replyToMessage, 'text')) ? 'yes' : 'no',
            'type' => $callback ? 'callback_query' : ($message ? array_key_first(array_intersect_key($update, array_flip([
                'message',
                'edited_message',
                'channel_post',
                'edited_channel_post',
            ]))) : 'unknown'),
        ];
    });

    $this->table([
        'chat.id',
        'from.id',
        'sender_chat.id',
        'message_id',
        'reply_to.from.id',
        'reply_to.sender_chat.id',
        'reply_to_has_text',
        'type',
    ], $rows->all());

    return Command::SUCCESS;
})->purpose('Display safe Telegram update IDs without printing payment text or bot secrets');

Schedule::command('checkout-sessions:prune')->everyFiveMinutes()->withoutOverlapping();
