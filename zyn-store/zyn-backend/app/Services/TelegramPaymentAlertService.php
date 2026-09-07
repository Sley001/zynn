<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TelegramPaymentAlertStatus;
use App\Exceptions\MalformedTelegramPaymentAlertException;
use App\Models\Order;
use App\Models\TelegramPaymentAlert;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramPaymentAlertService
{
    private const ADMIN_CALLBACK_METADATA_KEY = 'telegram_admin_callback';

    public function __construct(
        private readonly TelegramPaymentAlertParser $parser,
        private readonly TelegramCheckoutMatcher $checkoutMatcher,
        private readonly TelegramBotClient $telegram,
    ) {}

    public function processUpdate(array $update): ?TelegramPaymentAlert
    {
        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleAdminCallback($update['callback_query']);

            return null;
        }

        $message = $update['message'] ?? $update['channel_post'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        return $this->processMessage($message, $this->integerOrNull($update['update_id'] ?? null));
    }

    public function processMessage(array $message, ?int $updateId = null): ?TelegramPaymentAlert
    {
        $paymentMessage = $this->paymentMessageFromTelegramMessage($message);

        if (! $paymentMessage) {
            return null;
        }

        $ingestionMetadata = $paymentMessage['_zyn_ingestion_metadata'] ?? null;
        unset($paymentMessage['_zyn_ingestion_metadata']);

        if ($this->isForwardedMessage($paymentMessage)) {
            [$alert] = $this->persistRejectedMessage(
                $paymentMessage,
                $updateId,
                'Forwarded payment alerts are rejected.',
                metadata: is_array($ingestionMetadata) ? $ingestionMetadata : null,
            );

            return $alert;
        }

        $text = $paymentMessage['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            [$alert] = $this->persistRejectedMessage($paymentMessage, $updateId, 'Telegram payment alert did not contain text.');

            return $alert;
        }

        try {
            $parsed = $this->parser->parse($text, $this->messageContext($paymentMessage, $updateId));
        } catch (MalformedTelegramPaymentAlertException) {
            [$alert] = $this->persistRejectedMessage(
                $paymentMessage,
                $updateId,
                'Telegram payment alert did not match the expected ABA format.',
                $text,
                is_array($ingestionMetadata) ? $ingestionMetadata : null,
            );

            return $alert;
        }

        if (is_array($ingestionMetadata)) {
            $parsed['metadata'] = $ingestionMetadata;
        }

        if (! $this->merchantNameMatches($parsed['merchant_name'])) {
            [$alert] = $this->persistRejectedMessage(
                $paymentMessage,
                $updateId,
                'Telegram payment alert merchant did not match this store.',
                $text,
                is_array($ingestionMetadata) ? $ingestionMetadata : null,
            );

            return $alert;
        }

        [$alert, $shouldNotify] = $this->persistParsedAlertForReview($parsed);

        if ($shouldNotify) {
            $this->notifyForAlert($alert);
        }

        return $alert;
    }

    private function paymentMessageFromTelegramMessage(array $message): ?array
    {
        if ($this->messageIsFromConfiguredAbaSource($message)) {
            return $message;
        }

        return $this->abaMessageFromAuthorizedAdminReply($message);
    }

    private function abaMessageFromAuthorizedAdminReply(array $message): ?array
    {
        $groupId = $this->configuredPaymentGroupId();
        $chatId = $this->integerOrNull(data_get($message, 'chat.id'));
        $adminId = $this->integerOrNull(data_get($message, 'from.id'));
        $command = $message['text'] ?? null;
        $reply = $message['reply_to_message'] ?? null;

        if (
            ! $groupId
            || $chatId !== $groupId
            || ! $adminId
            || ! in_array($adminId, $this->adminUserIds(), true)
            || ! is_string($command)
            || $command !== '/paid'
            || ! is_array($reply)
            || $this->isForwardedMessage($message)
            || ($this->integerOrNull($reply['message_id'] ?? null) ?? 0) <= 0
            || ($this->integerOrNull($reply['date'] ?? null) ?? 0) <= 0
            || $this->integerOrNull(data_get($reply, 'chat.id')) !== $chatId
            || ! is_string($reply['text'] ?? null)
            || ! $this->messageIsFromConfiguredAbaSource($reply)
        ) {
            return null;
        }

        $reply['_zyn_ingestion_metadata'] = [
            'ingestion_source' => 'authorized_admin_reply_to_authenticated_aba_message',
            'admin_user_id' => $adminId,
            'command_message_id' => $this->integerOrNull($message['message_id'] ?? null),
        ];

        return $reply;
    }

    public function matchExistingAlert(TelegramPaymentAlert $alert): TelegramPaymentAlert
    {
        $matched = $this->checkoutMatcher->match($alert);

        if ($matched->order_id) {
            $this->notifyForAlert($matched);
        }

        return $matched;
    }

    public function handleAdminCallback(array $callback): bool
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $fromId = $this->integerOrNull(data_get($callback, 'from.id'));
        $data = (string) ($callback['data'] ?? '');

        if (! $fromId || ! in_array($fromId, $this->adminUserIds(), true)) {
            $this->telegram->answerCallbackQuery($callbackId, 'Not authorized for this store.', true);

            return false;
        }

        if (! preg_match('/^zyn_order:(?<action>verify|review):(?<order_id>[0-9]+)$/', $data, $matches)) {
            $this->telegram->answerCallbackQuery($callbackId, 'Unknown order action.', true);

            return false;
        }

        $result = DB::transaction(function () use ($matches, $fromId): array {
            $order = Order::query()
                ->whereKey((int) $matches['order_id'])
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return ['found' => false];
            }

            $alert = TelegramPaymentAlert::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if ($this->telegramAdminCallbackAlreadyHandled($order, $alert)) {
                return [
                    'found' => true,
                    'changed' => false,
                    'callback_text' => 'Order action was already handled.',
                ];
            }

            if ($matches['action'] === 'verify') {
                $order->update([
                    'status' => OrderStatus::Confirmed,
                    'admin_note' => $this->appendAdminNote(
                        $order->admin_note,
                        "Verified for shipping from Telegram by user {$fromId} at ".now('Asia/Phnom_Penh')->toDateTimeString().'.'
                    ),
                ]);

                if ($alert) {
                    $alert->update([
                        'status' => TelegramPaymentAlertStatus::Matched,
                        'metadata' => $this->recordTelegramAdminCallback($alert->metadata, 'verify', $fromId),
                    ]);
                }

                return [
                    'found' => true,
                    'changed' => true,
                    'callback_text' => 'Order marked verified for shipping.',
                    'group_message' => "Order #{$order->order_number} marked verified for shipping.",
                    'group_message_key' => "order:{$order->id}:verify",
                ];
            }

            $order->update([
                'admin_note' => $this->appendAdminNote(
                    $order->admin_note,
                    "Marked needs review from Telegram by user {$fromId} at ".now('Asia/Phnom_Penh')->toDateTimeString().'.'
                ),
            ]);

            if ($alert) {
                $alert->update([
                    'status' => TelegramPaymentAlertStatus::NeedsReview,
                    'metadata' => $this->recordTelegramAdminCallback($alert->metadata, 'review', $fromId),
                ]);
            }

            return [
                'found' => true,
                'changed' => true,
                'callback_text' => 'Order marked needs review.',
                'group_message' => "Order #{$order->order_number} marked needs review.",
                'group_message_key' => "order:{$order->id}:review",
            ];
        }, 3);

        if (! $result['found']) {
            $this->telegram->answerCallbackQuery($callbackId, 'Order was not found.', true);

            return false;
        }

        $this->telegram->answerCallbackQuery($callbackId, $result['callback_text']);
        $this->removeAdminCallbackButtons($callback);

        if (! $result['changed']) {
            return false;
        }

        $this->sendGroupMessageOnce($result['group_message_key'], $result['group_message']);

        return true;
    }

    private function telegramAdminCallbackAlreadyHandled(Order $order, ?TelegramPaymentAlert $alert): bool
    {
        if (data_get($alert?->metadata, self::ADMIN_CALLBACK_METADATA_KEY.'.action')) {
            return true;
        }

        $adminNote = (string) $order->admin_note;

        return str_contains($adminNote, 'Verified for shipping from Telegram by user ')
            || str_contains($adminNote, 'Marked needs review from Telegram by user ');
    }

    private function recordTelegramAdminCallback(?array $metadata, string $action, ?int $fromId): array
    {
        return array_merge($metadata ?? [], [
            self::ADMIN_CALLBACK_METADATA_KEY => [
                'action' => $action,
                'telegram_user_id' => $fromId,
                'recorded_at' => now('Asia/Phnom_Penh')->toISOString(),
            ],
        ]);
    }

    private function removeAdminCallbackButtons(array $callback): void
    {
        $chatId = $this->integerOrNull(data_get($callback, 'message.chat.id'));
        $messageId = $this->integerOrNull(data_get($callback, 'message.message_id'));

        if (! $chatId || ! $messageId) {
            return;
        }

        try {
            $this->telegram->removeMessageReplyMarkup($chatId, $messageId);
        } catch (Throwable $exception) {
            Log::warning('Telegram admin action buttons could not be removed.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function sendGroupMessageOnce(string $dedupeKey, string $message): void
    {
        $cacheKey = 'telegram_admin_callback_group_message:'.$dedupeKey;

        if (! Cache::add($cacheKey, true, now()->addYear())) {
            return;
        }

        $this->sendGroupMessage($message);
    }

    private function persistParsedAlertForReview(array $parsed): array
    {
        try {
            return DB::transaction(function () use ($parsed): array {
                $existingByMessage = TelegramPaymentAlert::query()
                    ->where('telegram_chat_id', $parsed['telegram_chat_id'])
                    ->where('telegram_message_id', $parsed['telegram_message_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existingByMessage) {
                    return [$existingByMessage, false];
                }

                $existingByTransaction = TelegramPaymentAlert::query()
                    ->where('transaction_id', $parsed['transaction_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existingByTransaction) {
                    $duplicate = TelegramPaymentAlert::query()->create(array_merge($parsed, [
                        'transaction_id' => null,
                        'status' => TelegramPaymentAlertStatus::Duplicate,
                        'duplicate_of_alert_id' => $existingByTransaction->id,
                        'processed_at' => now(),
                        'failure_reason' => 'Duplicate ABA transaction ID already processed.',
                        'metadata' => array_merge($parsed['metadata'] ?? [], [
                            'duplicate_transaction_id' => $parsed['transaction_id'],
                        ]),
                    ]));

                    return [$duplicate, false];
                }

                $alert = TelegramPaymentAlert::query()->create(array_merge($parsed, [
                    'status' => TelegramPaymentAlertStatus::Received,
                ]));

                return [$this->checkoutMatcher->match($alert), true];
            }, 3);
        } catch (QueryException $exception) {
            $existing = TelegramPaymentAlert::query()
                ->where('telegram_chat_id', $parsed['telegram_chat_id'])
                ->where('telegram_message_id', $parsed['telegram_message_id'])
                ->orWhere('transaction_id', $parsed['transaction_id'])
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            throw $exception;
        }
    }

    private function persistRejectedMessage(
        array $message,
        ?int $updateId,
        string $reason,
        ?string $rawMessage = null,
        ?array $metadata = null,
    ): array
    {
        return DB::transaction(function () use ($message, $updateId, $reason, $rawMessage, $metadata): array {
            $context = $this->messageContext($message, $updateId);
            $existing = TelegramPaymentAlert::query()
                ->where('telegram_chat_id', $context['telegram_chat_id'])
                ->where('telegram_message_id', $context['telegram_message_id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            $alert = TelegramPaymentAlert::query()->create([
                'telegram_update_id' => $context['telegram_update_id'],
                'telegram_chat_id' => $context['telegram_chat_id'],
                'telegram_message_id' => $context['telegram_message_id'],
                'telegram_from_id' => $context['telegram_from_id'],
                'telegram_sender_chat_id' => $context['telegram_sender_chat_id'],
                'telegram_message_sent_at' => $context['telegram_message_date']
                    ? now('Asia/Phnom_Penh')->setTimestamp($context['telegram_message_date'])
                    : null,
                'telegram_update_received_at' => now('Asia/Phnom_Penh'),
                'raw_message' => $rawMessage,
                'status' => TelegramPaymentAlertStatus::Rejected,
                'processed_at' => now(),
                'failure_reason' => $reason,
                'metadata' => $metadata,
            ]);

            return [$alert, false];
        }, 3);
    }

    private function notifyForAlert(TelegramPaymentAlert $alert): void
    {
        $alert = $alert->fresh(['order.items', 'order.payment']);

        if ($alert->status === TelegramPaymentAlertStatus::Unmatched) {
            $this->sendGroupMessage(implode("\n", [
                'Unmatched ABA payment alert.',
                'Amount: '.$this->formatAmount($alert->amount_cents, $alert->currency),
                'ABA Trx ID: '.$alert->transaction_id,
                'APV: '.$alert->apv,
                'Status: Waiting for an exact receipt claim or needs review. No order was created.',
            ]));

            return;
        }

        if (! $alert->order) {
            return;
        }

        $order = $alert->order;
        $message = implode("\n", [
            'Payment detected',
            'Order: #'.$order->order_number,
            'Amount: '.$this->formatAmount($alert->amount_cents, $alert->currency),
            'Customer: '.$order->customer_name,
            'Phone: '.$order->phone,
            'Items: '.$order->items->map(fn ($item): string => "{$item->product_name} x{$item->quantity}")->implode(', '),
            'ABA Trx ID: '.$alert->transaction_id,
            'APV: '.$alert->apv,
            'Status: '.($alert->status === TelegramPaymentAlertStatus::NeedsReview ? 'Payment detected - needs review' : 'Payment detected'),
        ]);

        $this->sendGroupMessage($message, [
            'inline_keyboard' => [
                [
                    ['text' => 'Verified for shipping', 'callback_data' => 'zyn_order:verify:'.$order->id],
                    ['text' => 'Needs review', 'callback_data' => 'zyn_order:review:'.$order->id],
                ],
            ],
        ]);
    }

    private function sendGroupMessage(string $message, ?array $replyMarkup = null): void
    {
        $groupId = $this->configuredPaymentGroupId();

        if (! $groupId) {
            return;
        }

        try {
            $this->telegram->sendMessage($groupId, $message, $replyMarkup);
        } catch (Throwable $exception) {
            Log::warning('Telegram admin notification failed.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function messageIsFromConfiguredAbaSource(array $message): bool
    {
        $groupId = $this->configuredPaymentGroupId();
        $abaSenderId = $this->configuredAbaSenderId();
        $chatId = $this->integerOrNull(data_get($message, 'chat.id'));
        $fromId = $this->integerOrNull(data_get($message, 'from.id'));
        $senderChatId = $this->integerOrNull(data_get($message, 'sender_chat.id'));

        if (! $groupId || ! $abaSenderId || $chatId !== $groupId) {
            return false;
        }

        return $fromId === $abaSenderId || $senderChatId === $abaSenderId;
    }

    private function merchantNameMatches(string $merchantName): bool
    {
        return strcasecmp($merchantName, (string) config('telegram.payment_merchant_name')) === 0;
    }

    private function isForwardedMessage(array $message): bool
    {
        return isset($message['forward_origin'])
            || isset($message['forward_from'])
            || isset($message['forward_from_chat'])
            || isset($message['forward_sender_name'])
            || (bool) ($message['is_automatic_forward'] ?? false);
    }

    private function messageContext(array $message, ?int $updateId): array
    {
        return [
            'telegram_update_id' => $updateId,
            'telegram_chat_id' => $this->integerOrNull(data_get($message, 'chat.id')),
            'telegram_message_id' => $this->integerOrNull($message['message_id'] ?? null),
            'telegram_from_id' => $this->integerOrNull(data_get($message, 'from.id')),
            'telegram_sender_chat_id' => $this->integerOrNull(data_get($message, 'sender_chat.id')),
            'telegram_message_date' => $this->integerOrNull($message['date'] ?? null),
            'telegram_update_received_at' => now('Asia/Phnom_Penh'),
        ];
    }

    private function configuredPaymentGroupId(): ?int
    {
        return $this->integerOrNull(config('telegram.payment_group_id'));
    }

    private function configuredAbaSenderId(): ?int
    {
        return $this->integerOrNull(config('telegram.aba_sender_id'));
    }

    private function adminUserIds(): array
    {
        return array_values(array_filter(
            array_map(fn ($id): ?int => $this->integerOrNull($id), config('telegram.admin_user_ids', [])),
            fn (?int $id): bool => $id !== null
        ));
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function appendAdminNote(?string $existing, string $note): string
    {
        return trim(trim((string) $existing)."\n".$note);
    }

    private function formatAmount(?int $amountCents, ?string $currency): string
    {
        if ($amountCents === null || ! $currency) {
            return 'unknown';
        }

        return Money::centsToDecimal($amountCents).' '.$currency;
    }
}
