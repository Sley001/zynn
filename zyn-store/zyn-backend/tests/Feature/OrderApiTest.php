<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_an_order_and_stock_is_reduced(): void
    {
        $product = Product::factory()->create([
            'name' => 'Cool Mint',
            'price_cents' => 400,
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Test Customer',
            'phone' => '012 345 678',
            'province' => 'Phnom Penh',
            'district' => 'Tuol Kouk',
            'address_note' => 'Near the market',
            'payment_method' => 'cod',
            'age_confirmed' => true,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 0.01],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 800)
            ->assertJsonPath('data.delivery_fee_cents', 150)
            ->assertJsonPath('data.total_cents', 950)
            ->assertJsonPath('data.items.0.unit_price_cents', 400)
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Test Customer',
            'subtotal_cents' => 800,
            'delivery_fee_cents' => 150,
            'total_cents' => 950,
        ]);
        $this->assertDatabaseHas('payments', [
            'method' => 'cod',
            'amount_cents' => 950,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_electronic_payment_cannot_bypass_the_receipt_and_telegram_checkout_flow(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->postJson('/api/orders', [
            'customer_name' => 'Test Customer',
            'phone' => '012345678',
            'province' => 'Phnom Penh',
            'district' => 'Tuol Kouk',
            'payment_method' => 'payway',
            'age_confirmed' => true,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_admin_cannot_manually_mark_an_electronic_order_paid(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $this->postJson('/api/orders', [
            'customer_name' => 'Test Customer',
            'phone' => '012345678',
            'province' => 'Phnom Penh',
            'district' => 'Tuol Kouk',
            'payment_method' => 'cod',
            'age_confirmed' => true,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = Order::query()->firstOrFail();
        $order->update(['payment_method' => PaymentMethod::PayWay]);
        $order->payment()->update(['method' => PaymentMethod::PayWay]);
        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);

        $this->patchJson("/api/admin/orders/{$order->order_number}", [
            'payment_status' => 'paid',
            'transaction_reference' => 'MANUAL-123456',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'pending',
            'transaction_reference' => null,
        ]);
    }

    public function test_sample_product_cannot_be_ordered(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::Sample,
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Test Customer',
            'phone' => '012345678',
            'province' => 'Phnom Penh',
            'district' => 'Tuol Kouk',
            'payment_method' => 'cod',
            'age_confirmed' => true,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }
}
