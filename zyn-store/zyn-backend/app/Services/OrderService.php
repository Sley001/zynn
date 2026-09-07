<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\TelegramPaymentAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private readonly CartPricingService $pricing) {}

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $quote = $this->pricing->quote($data['items'], lockProducts: true);
            $paymentMethod = PaymentMethod::from($data['payment_method']);

            $order = Order::query()->create([
                'order_number' => $this->makeOrderNumber(),
                'customer_name' => $data['customer_name'],
                'phone' => $data['phone'],
                'province' => $data['province'],
                'district' => $data['district'],
                'address_note' => $data['address_note'] ?? null,
                'payment_method' => $paymentMethod,
                'status' => OrderStatus::Pending,
                'subtotal_cents' => $quote['subtotal_cents'],
                'delivery_fee_cents' => $quote['delivery_fee_cents'],
                'total_cents' => $quote['total_cents'],
                'age_confirmed_at' => now(),
                'source' => 'website',
                'telegram_username' => $data['telegram_username'] ?? null,
            ]);

            $order->items()->createMany($quote['items']);
            $order->payment()->create([
                'method' => $paymentMethod,
                'status' => PaymentStatus::Pending,
                'amount_cents' => $quote['total_cents'],
            ]);

            foreach ($quote['stock_decrements'] as $stockDecrement) {
                Product::query()
                    ->whereKey($stockDecrement['product_id'])
                    ->decrement('stock_quantity', $stockDecrement['quantity']);
            }

            return $order->load(['items', 'payment']);
        }, 3);
    }

    public function createTelegramVerifiedFromCheckoutSession(CheckoutSession $session, TelegramPaymentAlert $alert): array
    {
        $customer = $session->customer_snapshot;
        $cart = $session->cart_snapshot;
        $items = $cart['items'] ?? [];
        $stockReviewMessages = [];

        $paymentMethod = $session->payment_method instanceof PaymentMethod
            ? $session->payment_method
            : PaymentMethod::from($session->payment_method);

        $order = Order::query()->create([
            'order_number' => $this->makeOrderNumber(),
            'customer_name' => $customer['name'],
            'phone' => $customer['phone'],
            'province' => $customer['province'],
            'district' => $customer['district'],
            'address_note' => $customer['address_note'] ?? null,
            'payment_method' => $paymentMethod,
            'status' => OrderStatus::Pending,
            'subtotal_cents' => $session->subtotal_cents,
            'delivery_fee_cents' => $session->delivery_fee_cents,
            'total_cents' => $session->total_cents,
            'age_confirmed_at' => $session->created_at ?? now(),
            'source' => 'website',
            'telegram_username' => $customer['telegram_username'] ?? null,
            'admin_note' => 'Payment verified by exact transaction ID, amount, and currency from the authenticated Telegram alert. Check delivery details before shipping.',
        ]);

        $order->items()->createMany($items);

        foreach ($items as $item) {
            $product = Product::query()
                ->whereKey($item['product_id'])
                ->lockForUpdate()
                ->first();

            if (! $product) {
                $stockReviewMessages[] = "{$item['product_name']}: product no longer exists.";

                continue;
            }

            if (! $product->is_active) {
                $stockReviewMessages[] = "{$item['product_name']}: product is no longer active.";
            }

            if ($product->track_stock) {
                $available = max(0, $product->stock_quantity);
                $requested = (int) $item['quantity'];
                $decrement = min($available, $requested);

                if ($decrement > 0) {
                    Product::query()
                        ->whereKey($product->id)
                        ->decrement('stock_quantity', $decrement);
                }

                if ($available < $requested) {
                    $stockReviewMessages[] = "{$item['product_name']}: paid for {$requested}, only {$available} in stock.";
                }
            }
        }

        if ($stockReviewMessages !== []) {
            $order->update([
                'admin_note' => "Telegram payment verified. Urgent stock review before shipping:\n"
                    .implode("\n", $stockReviewMessages),
            ]);
        }

        $payment = $order->payment()->create([
            'method' => $paymentMethod,
            'status' => PaymentStatus::Paid,
            'amount_cents' => $alert->amount_cents,
            'transaction_reference' => $alert->transaction_id,
            'paid_at' => $alert->displayed_paid_at ?? now(),
            'metadata' => [
                'detected_by' => 'telegram_aba_alert',
                'telegram_payment_alert_id' => $alert->id,
                'telegram_chat_id' => $alert->telegram_chat_id,
                'telegram_message_id' => $alert->telegram_message_id,
                'telegram_from_id' => $alert->telegram_from_id,
                'telegram_sender_chat_id' => $alert->telegram_sender_chat_id,
                'aba_transaction_id' => $alert->transaction_id,
                'aba_apv' => $alert->apv,
                'aba_payer_name' => $alert->payer_name,
                'aba_masked_account_digits' => $alert->masked_account_digits,
                'aba_payment_method' => $alert->payment_method,
                'aba_merchant_name' => $alert->merchant_name,
                'currency' => $session->currency,
                'stock_review_required' => $stockReviewMessages !== [],
            ],
        ]);

        return [
            'order' => $order->fresh()->load(['items', 'payment']),
            'payment' => $payment,
            'needs_review' => $stockReviewMessages !== [],
        ];
    }

    private function makeOrderNumber(): string
    {
        do {
            $number = 'ZR-'.now()->format('ymd').'-'.Str::upper(Str::random(7));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
