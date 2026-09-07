<?php

namespace Tests\Feature;

use App\Enums\CheckoutSessionStatus;
use App\Enums\PaymentMethod;
use App\Models\CheckoutSession;
use App\Models\PaymentQr;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_session_uses_server_total_and_ignores_client_prices(): void
    {
        $product = Product::factory()->create([
            'name' => 'Cool Mint',
            'price_cents' => 400,
            'stock_quantity' => 10,
        ]);
        $qr = PaymentQr::factory()->create([
            'amount' => '9.50',
            'amount_cents' => 950,
            'currency' => 'USD',
        ]);

        $response = $this->postJson('/api/checkout/sessions', [
            'customer_name' => 'Test Customer',
            'phone' => '012 345 678',
            'province' => 'Phnom Penh',
            'district' => 'Tuol Kouk',
            'address_note' => 'Near the market',
            'payment_method' => PaymentMethod::PayWay->value,
            'age_confirmed' => true,
            'subtotal' => '0.01',
            'delivery_fee' => '0.01',
            'total' => '0.01',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 0.01],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.subtotal_cents', 800)
            ->assertJsonPath('data.delivery_fee_cents', 150)
            ->assertJsonPath('data.total_cents', 950)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.qr.payment_qr_id', $qr->id);

        $this->assertDatabaseCount('checkout_sessions', 1);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_checkout_qr_matching_is_exact_decimal_amount_and_currency(): void
    {
        $product = Product::factory()->create([
            'price_cents' => 333,
            'stock_quantity' => 10,
        ]);
        $qr = PaymentQr::factory()->create([
            'amount' => '8.16',
            'amount_cents' => 816,
            'currency' => 'USD',
        ]);
        PaymentQr::factory()->create([
            'amount' => '8.17',
            'amount_cents' => 817,
            'currency' => 'USD',
        ]);
        PaymentQr::factory()->create([
            'amount' => '8.16',
            'amount_cents' => 816,
            'currency' => 'KHR',
        ]);

        $response = $this->postJson('/api/checkout/sessions', $this->payloadFor($product->id, 2));

        $response
            ->assertCreated()
            ->assertJsonPath('data.total_cents', 816)
            ->assertJsonPath('data.qr.payment_qr_id', $qr->id);
    }

    public function test_missing_matching_qr_returns_machine_readable_error_when_no_link_is_configured(): void
    {
        config(['store.payment_link' => null]);
        $product = Product::factory()->create(['price_cents' => 400]);

        $this->postJson('/api/checkout/sessions', $this->payloadFor($product->id, 1))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_qr_unavailable')
            ->assertJsonPath('total_cents', 550)
            ->assertJsonPath('currency', 'USD');

        $this->assertDatabaseCount('checkout_sessions', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_session_status_exposes_only_safe_payment_data(): void
    {
        $session = CheckoutSession::factory()->create();

        $response = $this->getJson("/api/checkout/sessions/{$session->token}/status");

        $response
            ->assertOk()
            ->assertJsonPath('data.token', $session->token)
            ->assertJsonPath('data.status', CheckoutSessionStatus::AwaitingPayment->value)
            ->assertJsonMissingPath('data.customer_snapshot')
            ->assertJsonMissingPath('data.cart_snapshot')
            ->assertJsonMissing(['Test Customer'])
            ->assertJsonMissing(['012345678']);
    }

    public function test_claim_paid_only_records_customer_claim(): void
    {
        Storage::fake('local');
        $session = CheckoutSession::factory()->create();

        $this->withHeaders(['Accept' => 'application/json'])->post("/api/checkout/sessions/{$session->token}/claim-paid", [
            'transaction_reference' => '123456789012345',
            'receipt' => UploadedFile::fake()->image('receipt.png'),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutSessionStatus::PaymentClaimed->value);

        $this->assertNotNull($session->fresh()->payment_claimed_at);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_can_cancel_unmatched_checkout_session_without_order(): void
    {
        $session = CheckoutSession::factory()->create();

        $this->postJson("/api/checkout/sessions/{$session->token}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutSessionStatus::Cancelled->value);

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $session->id,
            'status' => CheckoutSessionStatus::Cancelled->value,
        ]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_expiration_command_marks_and_prunes_abandoned_sessions(): void
    {
        $expired = CheckoutSession::factory()->create([
            'status' => CheckoutSessionStatus::AwaitingPayment,
            'expires_at' => now()->subMinute(),
        ]);
        $prunable = CheckoutSession::factory()->create([
            'status' => CheckoutSessionStatus::Expired,
            'expires_at' => now()->subHours(3),
            'updated_at' => now()->subDays(2),
            'resulting_order_id' => null,
        ]);

        $this->artisan('checkout-sessions:prune')->assertExitCode(0);

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $expired->id,
            'status' => CheckoutSessionStatus::Expired->value,
        ]);
        $this->assertDatabaseMissing('checkout_sessions', [
            'id' => $prunable->id,
        ]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_admin_can_upload_and_replace_payment_qr_and_update_delivery_fee(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);

        $createResponse = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/admin/payment-qrs', [
                'amount' => '5.50',
                'currency' => 'usd',
                'is_active' => '1',
                'admin_note' => 'One tin total',
                'image' => UploadedFile::fake()->image('qr.png'),
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.amount_cents', 550)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.is_active', true);

        $paymentQr = PaymentQr::query()->firstOrFail();
        Storage::disk('public')->assertExists($paymentQr->image_path);

        $updateResponse = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/admin/payment-qrs/{$paymentQr->id}", [
                '_method' => 'PATCH',
                'amount' => '6.50',
                'currency' => 'USD',
                'is_active' => '0',
                'image' => UploadedFile::fake()->image('replacement.webp'),
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.amount_cents', 650)
            ->assertJsonPath('data.is_active', false);

        $this
            ->patchJson('/api/admin/store-settings', ['delivery_fee' => '2.25'])
            ->assertOk()
            ->assertJsonPath('data.delivery_fee_cents', 225);

        $this->assertDatabaseHas('store_settings', [
            'key' => 'delivery_fee_cents',
            'value' => '225',
        ]);
    }

    public function test_admin_qr_requires_auth_and_valid_image_upload(): void
    {
        Storage::fake('public');

        $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/admin/payment-qrs', [
                'amount' => '5.50',
                'currency' => 'USD',
                'image' => UploadedFile::fake()->image('qr.png'),
            ])
            ->assertUnauthorized();

        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);

        $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/admin/payment-qrs', [
                'amount' => '5.50',
                'currency' => 'USD',
                'image' => UploadedFile::fake()->create('qr.txt', 1, 'text/plain'),
            ])
            ->assertUnprocessable();
    }

    public function test_delivery_setting_changes_server_calculated_checkout_total(): void
    {
        StoreSetting::query()->create([
            'key' => 'delivery_fee_cents',
            'value' => '225',
        ]);
        $product = Product::factory()->create(['price_cents' => 400]);
        PaymentQr::factory()->create([
            'amount' => '6.25',
            'amount_cents' => 625,
            'currency' => 'USD',
        ]);

        $this->postJson('/api/checkout/sessions', $this->payloadFor($product->id, 1))
            ->assertCreated()
            ->assertJsonPath('data.delivery_fee_cents', 225)
            ->assertJsonPath('data.total_cents', 625);
    }

    private function payloadFor(int $productId, int $quantity): array
    {
        return [
            'customer_name' => 'Test Customer',
            'phone' => '012 345 678',
            'province' => 'Phnom Penh',
            'district' => 'Tuol Kouk',
            'address_note' => 'Near the market',
            'payment_method' => PaymentMethod::PayWay->value,
            'age_confirmed' => true,
            'items' => [
                ['product_id' => $productId, 'quantity' => $quantity],
            ],
        ];
    }
}
