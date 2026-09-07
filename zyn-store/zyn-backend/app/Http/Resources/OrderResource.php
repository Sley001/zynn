<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->phone,
                'telegram_username' => $this->telegram_username,
            ],
            'delivery' => [
                'province' => $this->province,
                'district' => $this->district,
                'address_note' => $this->address_note,
            ],
            'payment' => [
                'method' => $this->payment_method->value,
                'status' => $this->whenLoaded('payment', fn () => $this->payment?->status->value),
                'transaction_reference' => $this->whenLoaded('payment', fn () => $this->payment?->transaction_reference),
                'paid_at' => $this->whenLoaded('payment', fn () => $this->payment?->paid_at?->toISOString()),
                'source' => $this->whenLoaded(
                    'telegramPaymentAlert',
                    fn () => $this->telegramPaymentAlert ? 'telegram_aba_alert' : null,
                ),
                'detected_at' => $this->whenLoaded(
                    'telegramPaymentAlert',
                    fn () => $this->telegramPaymentAlert?->processed_at?->toISOString(),
                ),
            ],
            'telegram_payment_alert' => $this->when(
                $request->user()?->is_admin
                    && $this->relationLoaded('telegramPaymentAlert')
                    && $this->telegramPaymentAlert,
                fn (): array => [
                    'status' => $this->telegramPaymentAlert->status->value,
                    'payer_name' => $this->telegramPaymentAlert->payer_name,
                    'masked_account_digits' => $this->telegramPaymentAlert->masked_account_digits,
                    'transaction_id' => $this->telegramPaymentAlert->transaction_id,
                    'apv' => $this->telegramPaymentAlert->apv,
                    'displayed_paid_at' => $this->telegramPaymentAlert->displayed_paid_at?->toISOString(),
                    'processed_at' => $this->telegramPaymentAlert->processed_at?->toISOString(),
                    'merchant_name' => $this->telegramPaymentAlert->merchant_name,
                ],
            ),
            'subtotal' => $this->subtotal_cents / 100,
            'subtotal_cents' => $this->subtotal_cents,
            'delivery_fee' => $this->delivery_fee_cents / 100,
            'delivery_fee_cents' => $this->delivery_fee_cents,
            'total' => $this->total_cents / 100,
            'total_cents' => $this->total_cents,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'admin_note' => $this->when($request->user()?->is_admin, $this->admin_note),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
