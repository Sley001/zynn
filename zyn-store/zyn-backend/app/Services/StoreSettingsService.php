<?php

namespace App\Services;

use App\Models\StoreSetting;
use App\Support\Money;

class StoreSettingsService
{
    private const DELIVERY_FEE_CENTS_KEY = 'delivery_fee_cents';

    private const CHECKOUT_SESSION_LIFETIME_MINUTES_KEY = 'checkout_session_lifetime_minutes';

    public function currency(): string
    {
        return Money::normalizeCurrency((string) config('store.currency', 'USD'));
    }

    public function deliveryFeeCents(): int
    {
        $setting = $this->settingValue(self::DELIVERY_FEE_CENTS_KEY);

        return $setting !== null ? (int) $setting : (int) config('store.delivery_fee_cents', 150);
    }

    public function updateDeliveryFee(string $amount): int
    {
        $cents = Money::decimalToCents($amount);

        StoreSetting::query()->updateOrCreate(
            ['key' => self::DELIVERY_FEE_CENTS_KEY],
            ['value' => (string) $cents],
        );

        return $cents;
    }

    public function checkoutSessionLifetimeMinutes(): int
    {
        $setting = $this->settingValue(self::CHECKOUT_SESSION_LIFETIME_MINUTES_KEY);
        $minutes = $setting !== null ? (int) $setting : (int) config('store.checkout_session_lifetime_minutes', 15);

        return max(1, $minutes);
    }

    public function updateCheckoutSessionLifetimeMinutes(int $minutes): int
    {
        $minutes = max(1, min(60, $minutes));

        StoreSetting::query()->updateOrCreate(
            ['key' => self::CHECKOUT_SESSION_LIFETIME_MINUTES_KEY],
            ['value' => (string) $minutes],
        );

        return $minutes;
    }

    public function checkoutSessionPruneAfterMinutes(): int
    {
        return max(30, (int) config('store.checkout_session_prune_after_minutes', 1440));
    }

    public function publicPayload(): array
    {
        $deliveryFeeCents = $this->deliveryFeeCents();

        return [
            'currency' => $this->currency(),
            'delivery_fee' => Money::centsToDecimal($deliveryFeeCents),
            'delivery_fee_cents' => $deliveryFeeCents,
            'checkout_session_lifetime_minutes' => $this->checkoutSessionLifetimeMinutes(),
        ];
    }

    private function settingValue(string $key): ?string
    {
        return StoreSetting::query()
            ->where('key', $key)
            ->value('value');
    }
}
