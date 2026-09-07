<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelegramPaymentAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'amount' => $this->amount_cents !== null ? Money::centsToDecimal($this->amount_cents) : null,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'payer_name' => $this->payer_name,
            'masked_account_digits' => $this->masked_account_digits,
            'displayed_paid_at' => $this->displayed_paid_at?->toISOString(),
            'payment_method' => $this->payment_method,
            'merchant_name' => $this->merchant_name,
            'transaction_id' => $this->transaction_id ?? data_get($this->metadata, 'duplicate_transaction_id'),
            'apv' => $this->apv,
            'telegram_chat_id' => $this->telegram_chat_id,
            'telegram_message_id' => $this->telegram_message_id,
            'failure_reason' => $this->failure_reason,
            'matched_checkout_session_id' => $this->matched_checkout_session_id,
            'order' => $this->whenLoaded('order', fn (): ?array => $this->order ? [
                'order_number' => $this->order->order_number,
                'status' => $this->order->status->value,
            ] : null),
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
