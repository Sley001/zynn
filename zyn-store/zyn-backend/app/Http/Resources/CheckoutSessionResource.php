<?php

namespace App\Http\Resources;

use App\Enums\CheckoutSessionStatus;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CheckoutSessionResource extends JsonResource
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->header('Cache-Control', 'private, no-store');
    }

    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'status' => $this->status->value,
            'subtotal' => Money::centsToDecimal($this->subtotal_cents),
            'subtotal_cents' => $this->subtotal_cents,
            'delivery_fee' => Money::centsToDecimal($this->delivery_fee_cents),
            'delivery_fee_cents' => $this->delivery_fee_cents,
            'total' => Money::centsToDecimal($this->total_cents),
            'total_cents' => $this->total_cents,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method->value,
            'expires_at' => $this->expires_at?->toISOString(),
            'payment_claimed_at' => $this->payment_claimed_at?->toISOString(),
            'payment_link' => $this->currency === 'USD' ? config('store.payment_link') : null,
            'consumed_at' => $this->consumed_at?->toISOString(),
            'items' => collect($this->cart_snapshot['items'] ?? [])->map(fn (array $item): array => [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'strength_mg' => $item['strength_mg'],
                'unit_price' => Money::centsToDecimal((int) $item['unit_price_cents']),
                'unit_price_cents' => $item['unit_price_cents'],
                'quantity' => $item['quantity'],
                'line_total' => Money::centsToDecimal((int) $item['line_total_cents']),
                'line_total_cents' => $item['line_total_cents'],
            ])->values()->all(),
            'qr' => $this->qr_image_path
                ? [
                    'payment_qr_id' => $this->payment_qr_id,
                    'amount' => Money::centsToDecimal($this->total_cents),
                    'amount_cents' => $this->total_cents,
                    'currency' => $this->currency,
                    'image_url' => Storage::disk('public')->url($this->qr_image_path),
                ]
                : null,
            'order' => $this->when(
                $this->status === CheckoutSessionStatus::Matched
                    && $this->relationLoaded('resultingOrder')
                    && $this->resultingOrder,
                fn (): array => [
                    'order_number' => $this->resultingOrder->order_number,
                    'status' => $this->resultingOrder->status->value,
                    'customer' => [
                        'name' => $this->resultingOrder->customer_name,
                        'phone' => $this->resultingOrder->phone,
                    ],
                    'delivery' => [
                        'province' => $this->resultingOrder->province,
                        'district' => $this->resultingOrder->district,
                        'address_note' => $this->resultingOrder->address_note,
                    ],
                    'payment' => [
                        'status' => $this->resultingOrder->payment?->status->value,
                        'paid_at' => $this->resultingOrder->payment?->paid_at?->toISOString(),
                        'detected_at' => $this->telegramPaymentAlert?->processed_at?->toISOString(),
                    ],
                    'total' => Money::centsToDecimal($this->resultingOrder->total_cents),
                    'total_cents' => $this->resultingOrder->total_cents,
                    'currency' => $this->currency,
                    'items' => $this->resultingOrder->items->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'strength_mg' => $item->strength_mg,
                        'unit_price' => Money::centsToDecimal($item->unit_price_cents),
                        'unit_price_cents' => $item->unit_price_cents,
                        'quantity' => $item->quantity,
                        'line_total' => Money::centsToDecimal($item->line_total_cents),
                        'line_total_cents' => $item->line_total_cents,
                    ])->values()->all(),
                ],
            ),
        ];
    }
}
