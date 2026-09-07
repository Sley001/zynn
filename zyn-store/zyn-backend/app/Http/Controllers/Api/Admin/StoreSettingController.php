<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStoreSettingsRequest;
use App\Services\StoreSettingsService;

class StoreSettingController extends Controller
{
    public function index(StoreSettingsService $settings)
    {
        return response()->json([
            'data' => $settings->publicPayload(),
        ]);
    }

    public function update(UpdateStoreSettingsRequest $request, StoreSettingsService $settings)
    {
        $validated = $request->validated();

        if (array_key_exists('delivery_fee', $validated)) {
            $settings->updateDeliveryFee((string) $validated['delivery_fee']);
        }

        if (array_key_exists('checkout_session_lifetime_minutes', $validated)) {
            $settings->updateCheckoutSessionLifetimeMinutes((int) $validated['checkout_session_lifetime_minutes']);
        }

        return response()->json([
            'data' => $settings->publicPayload(),
        ]);
    }
}
