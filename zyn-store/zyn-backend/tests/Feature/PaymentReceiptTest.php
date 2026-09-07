<?php

namespace Tests\Feature;

use App\Enums\CheckoutSessionStatus;
use App\Models\CheckoutSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function claim(CheckoutSession $session, ?UploadedFile $receipt, string $reference = 'aba-123456789'): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->post("/api/checkout/sessions/{$session->token}/claim-paid", [
                'transaction_reference' => $reference,
                'receipt' => $receipt,
            ]);
    }

    public function test_new_claim_requires_a_receipt_and_reference_without_changing_state(): void
    {
        $session = CheckoutSession::factory()->create();
        $this->claim($session, null)->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->claim($session, UploadedFile::fake()->image('receipt.png'), '')
            ->assertUnprocessable()->assertJsonValidationErrors('transaction_reference');

        $this->assertSame(CheckoutSessionStatus::AwaitingPayment, $session->fresh()->status);
        $this->assertNull($session->fresh()->payment_receipt_path);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_supported_receipt_photos_are_private_and_never_mark_a_payment_paid(): void
    {
        foreach (['jpg', 'png', 'webp'] as $index => $extension) {
            $session = CheckoutSession::factory()->create();
            $reference = 'ABA-12345678'.$index;
            $this->claim($session, UploadedFile::fake()->image('receipt.'.$extension), $reference)
                ->assertOk()->assertJsonPath('data.status', 'payment_claimed')
                ->assertJsonMissingPath('data.claimed_transaction_reference')
                ->assertJsonMissingPath('data.payment_receipt_path')
                ->assertJsonMissingPath('data.receipt_url');

            $session->refresh();
            $this->assertStringStartsWith('payment-receipts/', $session->payment_receipt_path);
            Storage::disk('local')->assertExists($session->payment_receipt_path);
            Storage::disk('public')->assertMissing($session->payment_receipt_path);
            $this->assertSame('private', config('filesystems.disks.local.visibility'));
            $this->assertArrayNotHasKey('payment_receipt_path', $session->toArray());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('checkout_payment_verifications', 0);
    }

    public function test_receipt_validation_rejects_spoofed_unsupported_corrupt_and_oversized_images(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jTt8AAAAASUVORK5CYII=');
        $invalid = [
            UploadedFile::fake()->createWithContent('receipt.jpg', '<?php echo "not a receipt";'),
            UploadedFile::fake()->createWithContent('receipt.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('receipt.gif'),
            UploadedFile::fake()->createWithContent('truncated.png', substr($png, 0, 33)),
            UploadedFile::fake()->image('large.png')->size(5121),
            UploadedFile::fake()->image('wide.png', 6001, 1),
            UploadedFile::fake()->createWithContent('many-pixels.png', substr_replace($png, pack('NN', 4001, 4001), 16, 8)),
        ];

        foreach ($invalid as $receipt) {
            $session = CheckoutSession::factory()->create();
            $this->claim($session, $receipt)->assertUnprocessable()->assertJsonValidationErrors('receipt');
            $this->assertSame(CheckoutSessionStatus::AwaitingPayment, $session->fresh()->status);
        }

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_retry_cannot_overwrite_the_original_photo_reference_or_claim_time(): void
    {
        $session = CheckoutSession::factory()->create();
        $this->claim($session, UploadedFile::fake()->image('original.png'))->assertOk();
        $original = $session->fresh();
        $bytes = Storage::disk('local')->get($original->payment_receipt_path);

        $this->travel(1)->minute();
        $this->claim($session, UploadedFile::fake()->image('replacement.jpg'), 'CHANGED-123456')->assertOk();
        $this->claim($session, null, 'CHANGED-654321')->assertOk();

        $session->refresh();
        $this->assertSame($original->payment_receipt_path, $session->payment_receipt_path);
        $this->assertSame($original->claimed_transaction_reference, $session->claimed_transaction_reference);
        $this->assertEquals($original->payment_claimed_at, $session->payment_claimed_at);
        $this->assertSame($bytes, Storage::disk('local')->get($session->payment_receipt_path));
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_transaction_reference_cannot_be_claimed_by_a_second_checkout(): void
    {
        $first = CheckoutSession::factory()->create();
        $second = CheckoutSession::factory()->create();

        $this->claim($first, UploadedFile::fake()->image('first.png'), 'DUPLICATE-123456')->assertOk();
        $this->claim($second, UploadedFile::fake()->image('second.png'), 'duplicate-123456')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transaction_reference');

        $this->assertSame(CheckoutSessionStatus::AwaitingPayment, $second->fresh()->status);
        $this->assertNull($second->fresh()->payment_receipt_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_admin_manual_payment_verification_route_does_not_exist(): void
    {
        $session = CheckoutSession::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);

        $this->postJson("/api/admin/checkout/sessions/{$session->token}/verify", [
            'transaction_reference' => 'MANUAL-123456',
            'amount_cents' => $session->total_cents,
            'currency' => $session->currency,
            'bank_verified' => true,
        ])->assertNotFound();
    }

    public function test_existing_legacy_claim_can_be_retried_without_receipt(): void
    {
        $session = CheckoutSession::factory()->create([
            'status' => CheckoutSessionStatus::PaymentClaimed,
            'payment_claimed_at' => now()->subMinute(),
            'claimed_transaction_reference' => 'LEGACY-123456',
        ]);

        $this->claim($session, null, 'CHANGED-123456')->assertOk()
            ->assertJsonMissingPath('data.claimed_transaction_reference');
        $this->assertNull($session->fresh()->payment_receipt_path);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_inactive_sessions_do_not_accept_or_store_new_evidence(): void
    {
        foreach ([CheckoutSessionStatus::Cancelled, CheckoutSessionStatus::Expired, CheckoutSessionStatus::Matched] as $status) {
            $session = CheckoutSession::factory()->create(['status' => $status]);
            $this->claim($session, UploadedFile::fake()->image('receipt.png'))->assertOk()
                ->assertJsonPath('data.status', $status->value);
            $this->assertNull($session->fresh()->payment_receipt_path);
        }

        $session = CheckoutSession::factory()->create(['expires_at' => now()->subMinute()]);
        $this->claim($session, UploadedFile::fake()->image('receipt.png'))->assertOk()
            ->assertJsonPath('data.status', 'expired');
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_database_failure_removes_uploaded_file_and_rolls_back_claim(): void
    {
        $session = CheckoutSession::factory()->create();
        Event::listen('eloquent.updating: '.CheckoutSession::class, function (CheckoutSession $updating): void {
            if ($updating->isDirty('payment_receipt_path')) {
                throw new RuntimeException('Simulated database failure after file upload.');
            }
        });

        $this->claim($session, UploadedFile::fake()->image('receipt.png'))->assertStatus(500);

        $this->assertSame(CheckoutSessionStatus::AwaitingPayment, $session->fresh()->status);
        $this->assertNull($session->fresh()->payment_receipt_path);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_receipt_requires_an_admin_account_even_with_an_admin_token_ability(): void
    {
        $session = CheckoutSession::factory()->create();
        $this->claim($session, UploadedFile::fake()->image('receipt.png'))->assertOk();
        $url = "/api/admin/checkout/sessions/{$session->token}/receipt";

        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]), ['admin']);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs(User::factory()->admin()->create(), []);
        $this->getJson($url)->assertForbidden();
    }

    public function test_admin_can_view_private_receipt_without_caching_or_path_disclosure(): void
    {
        $session = CheckoutSession::factory()->create();
        $this->claim($session, UploadedFile::fake()->image('receipt.png'))->assertOk();
        $session->refresh();
        $this->getJson("/api/checkout/sessions/{$session->token}/status")->assertOk()
            ->assertJsonMissingPath('data.payment_receipt_path')->assertJsonMissingPath('data.receipt_url');

        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);
        $this->getJson('/api/admin/payment-reviews')->assertOk()
            ->assertJsonPath('data.0.has_receipt', true)
            ->assertJsonMissingPath('data.0.payment_receipt_path');
        $response = $this->getJson("/api/admin/checkout/sessions/{$session->token}/receipt")
            ->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename=payment-receipt.png');
        $this->assertSame(Storage::disk('local')->get($session->payment_receipt_path), $response->streamedContent());
    }

    public function test_legacy_or_missing_receipt_returns_not_found_to_admin(): void
    {
        $session = CheckoutSession::factory()->create(['status' => CheckoutSessionStatus::PaymentClaimed]);
        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);

        $this->getJson('/api/admin/payment-reviews')->assertOk()->assertJsonPath('data.0.has_receipt', false);
        $url = "/api/admin/checkout/sessions/{$session->token}/receipt";
        $this->getJson($url)->assertNotFound();
        $session->update(['payment_receipt_path' => 'payment-receipts/missing.png']);
        $this->getJson($url)->assertNotFound();
    }

    public function test_receipts_have_no_public_or_signed_storage_route(): void
    {
        $session = CheckoutSession::factory()->create();
        $this->claim($session, UploadedFile::fake()->image('receipt.png'))->assertOk();
        $path = $session->fresh()->payment_receipt_path;

        $this->getJson('/storage/'.$path)->assertNotFound();
        $this->getJson("/api/checkout/sessions/{$session->token}/receipt")->assertNotFound();
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }
}
