<?php

namespace Database\Factories;

use App\Enums\CheckoutSessionStatus;
use App\Enums\PaymentMethod;
use App\Models\CheckoutSession;
use App\Models\PaymentQr;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CheckoutSession> */
class CheckoutSessionFactory extends Factory
{
    protected $model = CheckoutSession::class;

    public function definition(): array
    {
        $paymentQr = PaymentQr::factory()->create([
            'amount_cents' => 550,
            'amount' => '5.50',
            'is_active' => false,
        ]);

        return [
            'token' => (string) Str::uuid(),
            'customer_snapshot' => [
                'name' => 'Test Customer',
                'phone' => '012345678',
                'province' => 'Phnom Penh',
                'district' => 'Tuol Kouk',
                'address_note' => 'Near market',
            ],
            'cart_snapshot' => [
                'items' => [
                    [
                        'product_id' => 1,
                        'product_name' => 'Cool Mint',
                        'strength_mg' => 6,
                        'unit_price_cents' => 400,
                        'quantity' => 1,
                        'line_total_cents' => 400,
                    ],
                ],
            ],
            'subtotal_cents' => 400,
            'delivery_fee_cents' => 150,
            'total_cents' => 550,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::PayWay,
            'status' => CheckoutSessionStatus::AwaitingPayment,
            'payment_qr_id' => $paymentQr->id,
            'qr_image_path' => $paymentQr->image_path,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
