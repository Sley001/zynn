<?php

namespace App\Services;

use App\Enums\CheckoutSessionStatus;
use App\Enums\TelegramPaymentAlertStatus;
use App\Models\CheckoutSession;
use App\Models\TelegramPaymentAlert;
use Illuminate\Support\Facades\DB;

class TelegramCheckoutMatcher
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Match only authenticated, parsed Telegram bank evidence to one customer
     * claim carrying the exact same transaction ID, amount, and currency.
     */
    public function match(TelegramPaymentAlert $paymentAlert): TelegramPaymentAlert
    {
        return DB::transaction(function () use ($paymentAlert): TelegramPaymentAlert {
            $alert = TelegramPaymentAlert::query()
                ->whereKey($paymentAlert->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($alert->order_id || $alert->matched_checkout_session_id) {
                return $alert;
            }

            $configuredGroupId = filter_var(config('telegram.payment_group_id'), FILTER_VALIDATE_INT);
            $configuredAbaSenderId = filter_var(config('telegram.aba_sender_id'), FILTER_VALIDATE_INT);
            $authenticatedSource = $configuredGroupId !== false
                && $configuredAbaSenderId !== false
                && $alert->telegram_chat_id === $configuredGroupId
                && ($alert->telegram_from_id === $configuredAbaSenderId
                    || $alert->telegram_sender_chat_id === $configuredAbaSenderId);

            if (! $authenticatedSource || ! in_array($alert->status, [
                TelegramPaymentAlertStatus::Received,
                TelegramPaymentAlertStatus::Unmatched,
            ], true)) {
                return $this->unmatched($alert, 'Only an authenticated ABA Telegram bot alert can verify this payment.');
            }

            $reference = strtoupper(trim((string) $alert->transaction_id));
            if ($reference === '' || $alert->amount_cents === null || ! $alert->currency) {
                return $this->unmatched($alert, 'The Telegram payment alert is missing a transaction ID, amount, or currency.');
            }

            $referenceClaims = CheckoutSession::query()
                ->where('status', CheckoutSessionStatus::PaymentClaimed->value)
                ->whereNull('consumed_at')
                ->whereNotNull('payment_receipt_path')
                ->whereRaw('UPPER(claimed_transaction_reference) = ?', [$reference])
                ->lockForUpdate()
                ->get();

            if ($referenceClaims->count() !== 1) {
                return $this->unmatched(
                    $alert,
                    $referenceClaims->isEmpty()
                        ? 'Waiting for a customer receipt claim with this exact Telegram transaction ID.'
                        : 'Multiple customer claims use this transaction ID. Automatic verification was refused.'
                );
            }

            $session = $referenceClaims->first();
            if ($session->total_cents !== $alert->amount_cents || $session->currency !== $alert->currency) {
                return $this->unmatched($alert, 'The Telegram amount or currency does not match the claimed checkout.');
            }

            $alertSentAt = $alert->telegram_message_sent_at;
            $matchWindowStartsAt = $session->created_at?->copy()->subMinutes(2);
            $matchWindowEndsAt = $session->expires_at?->copy()->addMinutes(
                max(0, (int) config('telegram.payment_match_grace_minutes', 15))
            );

            if (
                ! $alertSentAt
                || ! $matchWindowStartsAt
                || ! $matchWindowEndsAt
                || $alertSentAt->lt($matchWindowStartsAt)
                || $alertSentAt->gt($matchWindowEndsAt)
            ) {
                return $this->unmatched($alert, 'The Telegram payment alert is outside this checkout payment window.');
            }

            $result = $this->orders->createTelegramVerifiedFromCheckoutSession($session, $alert);
            $order = $result['order'];

            $session->update([
                'status' => CheckoutSessionStatus::Matched,
                'consumed_at' => now(),
                'resulting_order_id' => $order->id,
            ]);

            $alert->update([
                'status' => $result['needs_review']
                    ? TelegramPaymentAlertStatus::NeedsReview
                    : TelegramPaymentAlertStatus::Matched,
                'matched_checkout_session_id' => $session->id,
                'order_id' => $order->id,
                'payment_id' => $order->payment->id,
                'processed_at' => now(),
                'failure_reason' => null,
                'metadata' => array_merge($alert->metadata ?? [], [
                    'verification_source' => 'authenticated_telegram_alert',
                    'matched_by' => 'exact_transaction_id_amount_currency',
                ]),
            ]);

            return $alert->fresh(['order.items', 'order.payment', 'checkoutSession', 'payment']);
        }, 3);
    }

    private function unmatched(TelegramPaymentAlert $alert, string $reason): TelegramPaymentAlert
    {
        $alert->update([
            'status' => TelegramPaymentAlertStatus::Unmatched,
            'processed_at' => now(),
            'failure_reason' => $reason,
        ]);

        return $alert->fresh();
    }
}
