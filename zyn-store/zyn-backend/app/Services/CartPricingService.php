<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class CartPricingService
{
    public function __construct(private readonly StoreSettingsService $settings)
    {
    }

    public function quote(array $items, bool $lockProducts = false): array
    {
        $requestedItems = collect($items)
            ->mapWithKeys(fn (array $item) => [
                (int) $item['product_id'] => [
                    'quantity' => (int) $item['quantity'],
                ],
            ]);

        if ($requestedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Your cart is empty.',
            ]);
        }

        $query = Product::query()->whereIn('id', $requestedItems->keys());

        if ($lockProducts) {
            $query->lockForUpdate();
        }

        $products = $query->get()->keyBy('id');

        if ($products->count() !== $requestedItems->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more products no longer exist.',
            ]);
        }

        $subtotalCents = 0;
        $lineItems = [];
        $stockDecrements = [];

        foreach ($requestedItems as $productId => $requestedItem) {
            /** @var Product $product */
            $product = $products->get($productId);
            $quantity = (int) $requestedItem['quantity'];

            if (! $product->is_active || $product->status !== ProductStatus::Available) {
                throw ValidationException::withMessages([
                    'items' => "{$product->name} is not available for ordering.",
                ]);
            }

            if ($product->track_stock && $product->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'items' => "Only {$product->stock_quantity} unit(s) of {$product->name} remain.",
                ]);
            }

            $lineTotalCents = $product->price_cents * $quantity;
            $subtotalCents += $lineTotalCents;
            $lineItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'strength_mg' => $product->strength_mg,
                'unit_price_cents' => $product->price_cents,
                'quantity' => $quantity,
                'line_total_cents' => $lineTotalCents,
            ];

            if ($product->track_stock) {
                $stockDecrements[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ];
            }
        }

        $deliveryFeeCents = $this->settings->deliveryFeeCents();

        return [
            'items' => $lineItems,
            'stock_decrements' => $stockDecrements,
            'subtotal_cents' => $subtotalCents,
            'delivery_fee_cents' => $deliveryFeeCents,
            'total_cents' => $subtotalCents + $deliveryFeeCents,
            'currency' => $this->settings->currency(),
        ];
    }
}
