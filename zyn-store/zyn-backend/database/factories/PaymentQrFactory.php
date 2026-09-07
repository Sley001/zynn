<?php

namespace Database\Factories;

use App\Models\PaymentQr;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentQr> */
class PaymentQrFactory extends Factory
{
    protected $model = PaymentQr::class;

    public function definition(): array
    {
        $amountCents = fake()->numberBetween(100, 10000);

        return [
            'amount' => Money::centsToDecimal($amountCents),
            'amount_cents' => $amountCents,
            'currency' => 'USD',
            'image_path' => 'payment-qrs/test-qr.png',
            'is_active' => true,
            'admin_note' => null,
        ];
    }
}
