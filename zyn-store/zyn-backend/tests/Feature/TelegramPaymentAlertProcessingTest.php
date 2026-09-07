<?php

namespace Tests\Feature;

use App\Enums\CheckoutSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TelegramPaymentAlertStatus;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\TelegramPaymentAlert;
use App\Models\User;
use App\Services\TelegramPaymentAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelegramPaymentAlertProcessingTest extends TestCase
{
    use RefreshDatabase;

    private const GROUP_ID = -1001234567890;

    private const ABA_SENDER_ID = 99887766;

    private const ADMIN_USER_ID = 11223344;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.bot_token' => 'test-token',
            'telegram.payment_group_id' => self::GROUP_ID,
            'telegram.aba_sender_id' => self::ABA_SENDER_ID,
            'telegram.admin_user_ids' => [self::ADMIN_USER_ID],
            'telegram.webhook_secret' => 'test-webhook-secret',
            'telegram.mode' => 'polling',
            'telegram.payment_merchant_name' => 'LIV SEANGLY',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    public function test_rejects_wrong_group_id(): void
    {
        $this->processUpdate($this->telegramUpdate(chatId: -1009999999999));

        $this->assertDatabaseCount('telegram_payment_alerts', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_rejects_wrong_aba_sender_id(): void
    {
        $this->processUpdate($this->telegramUpdate(senderId: 12345));

        $this->assertDatabaseCount('telegram_payment_alerts', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_malformed_alert_is_rejected_without_order(): void
    {
        $this->processUpdate($this->telegramUpdate(text: 'Paid successfully, trust me.'));

        $this->assertDatabaseHas('telegram_payment_alerts', [
            'status' => TelegramPaymentAlertStatus::Rejected->value,
            'failure_reason' => 'Telegram payment alert did not match the expected ABA format.',
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_alert_does_not_match_a_checkout_without_a_scanned_receipt_claim(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 400, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 550, customerName: 'First Customer');

        $this->processUpdate($this->telegramUpdate(transactionId: '123456789012345', amountCents: 550));

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(CheckoutSessionStatus::AwaitingPayment, $session->fresh()->status);
        $this->assertDatabaseHas('telegram_payment_alerts', ['status' => TelegramPaymentAlertStatus::Unmatched->value]);
    }

    public function test_authenticated_alert_matches_one_scanned_claim_by_exact_transaction_amount_and_currency(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 400, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 550, customerName: 'Exact Customer');
        $this->claimSession($session, '123456789012345');

        $this->processUpdate($this->telegramUpdate(transactionId: '123456789012345', amountCents: 550));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $session->id,
            'status' => CheckoutSessionStatus::Matched->value,
        ]);
        $this->assertDatabaseHas('payments', [
            'status' => PaymentStatus::Paid->value,
            'transaction_reference' => '123456789012345',
            'amount_cents' => 550,
        ]);
        $this->assertDatabaseHas('telegram_payment_alerts', [
            'transaction_id' => '123456789012345',
            'status' => TelegramPaymentAlertStatus::Matched->value,
            'matched_checkout_session_id' => $session->id,
        ]);
    }

    public function test_uploaded_receipt_claim_matches_an_alert_that_arrived_first(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 400, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 550, customerName: 'Alert First Customer');
        $this->processUpdate($this->telegramUpdate(
            messageId: 8,
            transactionId: '987654321012345',
            amountCents: 550,
        ));

        $this->post("/api/checkout/sessions/{$session->token}/claim-paid", [
            'transaction_reference' => '987654321012345',
            'receipt' => UploadedFile::fake()->image('receipt.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutSessionStatus::Matched->value)
            ->assertJsonPath('data.order.customer.name', 'Alert First Customer')
            ->assertJsonMissingPath('data.order.payment.transaction_reference')
            ->assertJsonMissingPath('data.order.payment.apv');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('telegram_payment_alerts', [
            'transaction_id' => '987654321012345',
            'matched_checkout_session_id' => $session->id,
        ]);
    }

    public function test_equal_amount_with_a_different_transaction_id_does_not_match(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 550);
        $this->claimSession($session, 'CLAIM-123456');

        $this->processUpdate($this->telegramUpdate(transactionId: '999999999999998', amountCents: 550));

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
    }

    public function test_old_telegram_alert_cannot_verify_a_new_checkout(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 550);
        $this->claimSession($session, '101010101010101');

        $this->processUpdate($this->telegramUpdate(
            messageId: 9,
            transactionId: '101010101010101',
            amountCents: 550,
            messageSentAt: now('Asia/Phnom_Penh')->subHour()->toImmutable(),
        ));

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
        $this->assertDatabaseHas('telegram_payment_alerts', [
            'transaction_id' => '101010101010101',
            'failure_reason' => 'The Telegram payment alert is outside this checkout payment window.',
        ]);
    }

    public function test_customer_two_cent_alert_text_is_recorded_without_matching_a_checkout_session(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');

        $this->processUpdate($this->telegramUpdate(
            messageId: 71,
            transactionId: '178842274633231',
            amountCents: 2,
            text: '$0.02 paid by LIV SEANGLY (*770) on Sep 03, 03:05 PM via ABA PAY at LIV SEANGLY. Trx. ID: 178842274633231, APV: 413135.',
        ));

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(CheckoutSessionStatus::AwaitingPayment, $session->fresh()->status);
        $this->assertDatabaseHas('telegram_payment_alerts', ['status' => TelegramPaymentAlertStatus::Unmatched->value]);
    }

    public function test_channel_post_alert_is_recorded_without_matching_a_checkout_session(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $update = $this->telegramUpdate(
            messageId: 72,
            transactionId: '178842274633231',
            amountCents: 2,
            text: '$0.02 paid by LIV SEANGLY (*770) on Sep 03, 03:05 PM via ABA PAY at LIV SEANGLY. Trx. ID: 178842274633231, APV: 413135.',
        );
        $update['channel_post'] = $update['message'];
        unset($update['message']);

        $this->processUpdate($update);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(CheckoutSessionStatus::AwaitingPayment, $session->fresh()->status);
        $this->assertDatabaseHas('telegram_payment_alerts', ['status' => TelegramPaymentAlertStatus::Unmatched->value]);
    }

    public function test_authorized_admin_exact_paid_reply_to_aba_bot_alert_is_authenticated_and_matched(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $this->claimSession($session, '178842592036164');
        $abaUpdate = $this->telegramUpdate(
            messageId: 73,
            transactionId: '178842592036164',
            amountCents: 2,
        );

        $adminReply = [
            'update_id' => 9073,
            'message' => [
                'message_id' => 170,
                'date' => now('Asia/Phnom_Penh')->timestamp,
                'chat' => ['id' => self::GROUP_ID],
                'from' => ['id' => self::ADMIN_USER_ID],
                'text' => '/paid',
                'reply_to_message' => $abaUpdate['message'],
            ],
        ];
        $this->processUpdate($adminReply);
        $adminReply['update_id'] = 9074;
        $adminReply['message']['message_id'] = 171;
        $this->processUpdate($adminReply);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('payments', [
            'status' => PaymentStatus::Paid->value,
            'transaction_reference' => '178842592036164',
            'amount_cents' => 2,
        ]);
        $this->assertDatabaseHas('telegram_payment_alerts', [
            'telegram_message_id' => 73,
            'telegram_from_id' => self::ABA_SENDER_ID,
            'transaction_id' => '178842592036164',
            'status' => TelegramPaymentAlertStatus::Matched->value,
        ]);
        $this->assertSame(CheckoutSessionStatus::Matched, $session->fresh()->status);
        $this->assertSame(
            'authorized_admin_reply_to_authenticated_aba_message',
            data_get(TelegramPaymentAlert::query()->sole()->metadata, 'ingestion_source'),
        );
        $this->assertSame(
            self::ADMIN_USER_ID,
            data_get(TelegramPaymentAlert::query()->sole()->metadata, 'admin_user_id'),
        );
        $this->assertSame(
            170,
            data_get(TelegramPaymentAlert::query()->sole()->metadata, 'command_message_id'),
        );
    }

    public function test_authorized_admin_paid_reply_rejects_a_forwarded_or_wrong_sender_message(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $this->claimSession($session, '178842592036165');

        foreach ([
            ['senderId' => self::ABA_SENDER_ID, 'forward_origin' => ['type' => 'hidden_user', 'date' => now()->timestamp, 'sender_user_name' => 'PayWay by ABA']],
            ['senderId' => 44556677],
            ['senderId' => self::ABA_SENDER_ID, 'chatId' => -1009999999999],
            ['senderId' => self::ABA_SENDER_ID, 'removeChat' => true],
        ] as $index => $case) {
            $abaUpdate = $this->telegramUpdate(
                messageId: 176 + $index,
                chatId: $case['chatId'] ?? self::GROUP_ID,
                senderId: $case['senderId'],
                transactionId: '178842592036165',
                amountCents: 2,
            );
            if (isset($case['forward_origin'])) {
                $abaUpdate['message']['forward_origin'] = $case['forward_origin'];
            }
            if ($case['removeChat'] ?? false) {
                unset($abaUpdate['message']['chat']);
            }

            $this->processUpdate([
                'update_id' => 9276 + $index,
                'message' => [
                    'message_id' => 276 + $index,
                    'date' => now('Asia/Phnom_Penh')->timestamp,
                    'chat' => ['id' => self::GROUP_ID],
                    'from' => ['id' => self::ADMIN_USER_ID],
                    'text' => '/paid',
                    'reply_to_message' => $abaUpdate['message'],
                ],
            ]);
        }

        $this->assertDatabaseCount('telegram_payment_alerts', 1);
        $this->assertDatabaseHas('telegram_payment_alerts', [
            'status' => TelegramPaymentAlertStatus::Rejected->value,
            'failure_reason' => 'Forwarded payment alerts are rejected.',
        ]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
    }

    public function test_paid_reply_uses_the_original_aba_message_time_not_the_fresh_command_time(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $this->claimSession($session, '178842592036167');
        $abaUpdate = $this->telegramUpdate(
            messageId: 180,
            transactionId: '178842592036167',
            amountCents: 2,
            messageSentAt: now('Asia/Phnom_Penh')->subHour()->toImmutable(),
        );

        $this->processUpdate([
            'update_id' => 9280,
            'message' => [
                'message_id' => 280,
                'date' => now('Asia/Phnom_Penh')->timestamp,
                'chat' => ['id' => self::GROUP_ID],
                'from' => ['id' => self::ADMIN_USER_ID],
                'text' => '/paid',
                'reply_to_message' => $abaUpdate['message'],
            ],
        ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('telegram_payment_alerts', [
            'transaction_id' => '178842592036167',
            'status' => TelegramPaymentAlertStatus::Unmatched->value,
            'failure_reason' => 'The Telegram payment alert is outside this checkout payment window.',
        ]);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
    }

    public function test_authorized_admin_reply_requires_the_exact_paid_command(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $this->claimSession($session, '178842592036166');
        $abaUpdate = $this->telegramUpdate(
            messageId: 179,
            transactionId: '178842592036166',
            amountCents: 2,
        );

        foreach (['/Paid', '/paid now', '/paid$0.02', '/paid@rawwSl_zyn_order_bot', 'paid'] as $index => $command) {
            $this->processUpdate([
                'update_id' => 9279 + $index,
                'message' => [
                    'message_id' => 279 + $index,
                    'date' => now('Asia/Phnom_Penh')->timestamp,
                    'chat' => ['id' => self::GROUP_ID],
                    'from' => ['id' => self::ADMIN_USER_ID],
                    'text' => $command,
                    'reply_to_message' => $abaUpdate['message'],
                ],
            ]);
        }

        $this->assertDatabaseCount('telegram_payment_alerts', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
    }

    public function test_authorized_admin_cannot_paste_aba_alert_after_paid_command(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $this->claimSession($session, '178842592036164');

        $this->processUpdate([
            'update_id' => 9173,
            'message' => [
                'message_id' => 173,
                'date' => CarbonImmutable::create(2026, 9, 3, 16, 0, 0, 'Asia/Phnom_Penh')->timestamp,
                'chat' => ['id' => self::GROUP_ID],
                'from' => ['id' => self::ADMIN_USER_ID],
                'text' => '/paid $0.02 paid by LIV SEANGLY (*770) on Sep 03, 03:58 PM via ABA PAY at LIV SEANGLY. Trx. ID: 178842592036164, APV: 950340.',
            ],
        ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
        $this->assertDatabaseCount('telegram_payment_alerts', 0);
    }

    public function test_authorized_admin_cannot_paste_aba_alert_without_space_after_paid_command(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $this->claimSession($session, '178842922072013');

        $this->processUpdate([
            'update_id' => 9175,
            'message' => [
                'message_id' => 175,
                'date' => CarbonImmutable::create(2026, 9, 3, 16, 55, 0, 'Asia/Phnom_Penh')->timestamp,
                'chat' => ['id' => self::GROUP_ID],
                'from' => ['id' => self::ADMIN_USER_ID],
                'text' => '/paid$0.02 paid by LIV SEANGLY (*770) on Sep 03, 04:53 PM via ABA PAY at LIV SEANGLY. Trx. ID: 178842922072013, APV: 802018.',
            ],
        ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
        $this->assertDatabaseCount('telegram_payment_alerts', 0);
    }

    public function test_unauthorized_admin_cannot_paste_aba_alert_after_paid_command(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $this->claimSession($session, '178842592036164');

        $this->processUpdate([
            'update_id' => 9174,
            'message' => [
                'message_id' => 174,
                'date' => CarbonImmutable::create(2026, 9, 3, 16, 0, 0, 'Asia/Phnom_Penh')->timestamp,
                'chat' => ['id' => self::GROUP_ID],
                'from' => ['id' => 44556677],
                'text' => '/paid $0.02 paid by LIV SEANGLY (*770) on Sep 03, 03:58 PM via ABA PAY at LIV SEANGLY. Trx. ID: 178842592036164, APV: 950340.',
            ],
        ]);

        $this->assertDatabaseCount('telegram_payment_alerts', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
    }

    public function test_latest_claim_cannot_take_another_customers_equal_amount_payment(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $stale = $this->checkoutSession(
            $product,
            totalCents: 2,
            customerName: 'Stale Customer',
            createdAt: now()->subMinutes(20),
        );
        $current = $this->checkoutSession(
            $product,
            totalCents: 2,
            customerName: 'Current Customer',
            createdAt: now()->subMinutes(5),
        );

        $stale->update([
            'status' => CheckoutSessionStatus::PaymentClaimed,
            'payment_claimed_at' => now()->subMinutes(19),
        ]);
        $current->update([
            'status' => CheckoutSessionStatus::PaymentClaimed,
            'payment_claimed_at' => now()->subMinute(),
        ]);

        $this->processUpdate($this->telegramUpdate(
            messageId: 75,
            transactionId: '178842690731859',
            amountCents: 2,
            text: '$0.02 paid by LIV SEANGLY (*770) on Sep 03, 04:15 PM via ABA PAY at LIV SEANGLY. Trx. ID: 178842690731859, APV: 469536.',
        ));

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $current->id,
            'status' => CheckoutSessionStatus::PaymentClaimed->value,
        ]);
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $stale->id,
            'status' => CheckoutSessionStatus::PaymentClaimed->value,
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unauthorized_admin_reply_to_aba_bot_alert_is_ignored(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 1, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 2, customerName: 'Liv Test Customer');
        $this->claimSession($session, '178842690731859');
        $abaUpdate = $this->telegramUpdate(
            messageId: 74,
            transactionId: '178842690731859',
            amountCents: 2,
            text: '$0.02 paid by LIV SEANGLY (*770) on Sep 03, 04:15 PM via ABA PAY at LIV SEANGLY. Trx. ID: 178842690731859, APV: 469536.',
        );

        $this->processUpdate([
            'update_id' => 9074,
            'message' => [
                'message_id' => 171,
                'date' => CarbonImmutable::create(2026, 9, 3, 16, 16, 0, 'Asia/Phnom_Penh')->timestamp,
                'chat' => ['id' => self::GROUP_ID],
                'from' => ['id' => 44556677],
                'text' => '/paid',
                'reply_to_message' => $abaUpdate['message'],
            ],
        ]);

        $this->assertDatabaseCount('telegram_payment_alerts', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(CheckoutSessionStatus::PaymentClaimed, $session->fresh()->status);
    }

    public function test_matched_checkout_status_exposes_safe_paid_order_details(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 400, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 550, customerName: 'First Customer');

        $this->processUpdate($this->telegramUpdate(transactionId: '999999999999999', amountCents: 550));
        $this->claimOnlyCheckoutForExistingAlert();

        $this->getJson("/api/checkout/sessions/{$session->token}/status")
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutSessionStatus::Matched->value)
            ->assertJsonPath('data.order.customer.name', 'First Customer')
            ->assertJsonPath('data.order.payment.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.order.items.0.product_name', 'Cool Mint')
            ->assertJsonMissingPath('data.order.telegram_payment_alert')
            ->assertJsonMissingPath('data.customer_snapshot')
            ->assertJsonMissingPath('data.cart_snapshot');
    }

    public function test_equal_amount_alerts_never_choose_a_customer_by_fifo(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 5]);
        $first = $this->checkoutSession($product, totalCents: 550, customerName: 'First Customer', createdAt: now()->subMinutes(10));
        $second = $this->checkoutSession($product, totalCents: 550, customerName: 'Second Customer', createdAt: now()->subMinutes(5));

        $this->processUpdate($this->telegramUpdate(messageId: 10, transactionId: '111111111111111', amountCents: 550));
        $this->processUpdate($this->telegramUpdate(messageId: 11, transactionId: '222222222222222', amountCents: 550));

        $firstAlert = TelegramPaymentAlert::query()->where('transaction_id', '111111111111111')->firstOrFail();
        $secondAlert = TelegramPaymentAlert::query()->where('transaction_id', '222222222222222')->firstOrFail();

        $this->assertNull($firstAlert->matched_checkout_session_id);
        $this->assertNull($secondAlert->matched_checkout_session_id);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_no_alert_creates_no_order(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 5]);
        $this->checkoutSession($product, totalCents: 550);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unmatched_alert_creates_no_order(): void
    {
        $this->processUpdate($this->telegramUpdate(transactionId: '333333333333333', amountCents: 550));

        $this->assertDatabaseHas('telegram_payment_alerts', [
            'transaction_id' => '333333333333333',
            'status' => TelegramPaymentAlertStatus::Unmatched->value,
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_amount_and_currency_must_match_exactly(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 5]);
        $session = $this->checkoutSession($product, totalCents: 550, currency: 'KHR');
        $this->claimSession($session, '444444444444444');

        $this->processUpdate($this->telegramUpdate(transactionId: '444444444444444', amountCents: 550));

        $this->assertDatabaseHas('telegram_payment_alerts', [
            'transaction_id' => '444444444444444',
            'status' => TelegramPaymentAlertStatus::Unmatched->value,
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_telegram_message_does_not_duplicate_order_or_stock_decrement(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 2]);
        $session = $this->checkoutSession($product, totalCents: 550);
        $this->claimSession($session, '555555555555555');
        $update = $this->telegramUpdate(messageId: 20, transactionId: '555555555555555', amountCents: 550);

        $this->processUpdate($update);
        $this->processUpdate($update);

        $this->assertDatabaseCount('telegram_payment_alerts', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 1,
        ]);
    }

    public function test_duplicate_aba_transaction_id_does_not_consume_second_session(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 5]);
        $first = $this->checkoutSession($product, totalCents: 550, createdAt: now()->subMinutes(10));
        $second = $this->checkoutSession($product, totalCents: 550, createdAt: now()->subMinutes(5));
        $this->claimSession($first, '666666666666666');
        $this->claimSession($second, 'OTHER-666666');

        $this->processUpdate($this->telegramUpdate(messageId: 30, transactionId: '666666666666666', amountCents: 550));
        $this->processUpdate($this->telegramUpdate(messageId: 31, transactionId: '666666666666666', amountCents: 550));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('telegram_payment_alerts', [
            'status' => TelegramPaymentAlertStatus::Duplicate->value,
            'duplicate_of_alert_id' => TelegramPaymentAlert::query()->where('transaction_id', '666666666666666')->firstOrFail()->id,
        ]);
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $first->id,
            'status' => CheckoutSessionStatus::Matched->value,
        ]);
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $second->id,
            'status' => CheckoutSessionStatus::PaymentClaimed->value,
        ]);
    }

    public function test_paid_order_is_retained_when_stock_requires_review(): void
    {
        $product = Product::factory()->create(['name' => 'Cool Mint', 'price_cents' => 400, 'stock_quantity' => 0]);
        $this->checkoutSession($product, totalCents: 550);

        $this->processUpdate($this->telegramUpdate(messageId: 40, transactionId: '777777777777777', amountCents: 550));
        $this->claimOnlyCheckoutForExistingAlert();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('payments', [
            'status' => PaymentStatus::Paid->value,
            'transaction_reference' => '777777777777777',
        ]);
        $this->assertDatabaseHas('telegram_payment_alerts', [
            'transaction_id' => '777777777777777',
            'status' => TelegramPaymentAlertStatus::NeedsReview->value,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 0,
        ]);
        $this->assertStringContainsString('Urgent stock review', Order::query()->firstOrFail()->admin_note);
    }

    public function test_unauthorized_telegram_admin_callback_is_rejected(): void
    {
        $order = Order::query()->create([
            'order_number' => 'ZR-260901-TESTING',
            'customer_name' => 'Test Customer',
            'phone' => '012345678',
            'province' => 'Phnom Penh',
            'district' => 'Tuol Kouk',
            'payment_method' => PaymentMethod::PayWay,
            'status' => OrderStatus::Pending,
            'subtotal_cents' => 400,
            'delivery_fee_cents' => 150,
            'total_cents' => 550,
            'age_confirmed_at' => now(),
            'source' => 'website',
        ]);

        $handled = app(TelegramPaymentAlertService::class)->handleAdminCallback([
            'id' => 'callback-id',
            'from' => ['id' => 44556677],
            'data' => 'zyn_order:verify:'.$order->id,
        ]);

        $this->assertFalse($handled);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_verify_shipping_callback_only_sends_public_confirmation_once(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 0]);
        $this->checkoutSession($product, totalCents: 550);
        $this->processUpdate($this->telegramUpdate(messageId: 51, transactionId: '131313131313131', amountCents: 550));
        $this->claimOnlyCheckoutForExistingAlert();

        $order = Order::query()->firstOrFail();
        $service = app(TelegramPaymentAlertService::class);
        $callback = $this->telegramOrderCallback('verify-callback-1', 'verify', $order->id);

        $this->assertTrue($service->handleAdminCallback($callback));
        $this->assertFalse($service->handleAdminCallback(array_replace($callback, ['id' => 'verify-callback-2'])));

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertSame(1, substr_count((string) $order->fresh()->admin_note, 'Verified for shipping from Telegram by user'));
        $this->assertSame(TelegramPaymentAlertStatus::Matched, $order->fresh('telegramPaymentAlert')->telegramPaymentAlert->status);
        $this->assertSame('verify', data_get($order->fresh('telegramPaymentAlert')->telegramPaymentAlert->metadata, 'telegram_admin_callback.action'));
        $this->assertCount(1, $this->sentTelegramMessagesContaining('marked verified for shipping.'));
    }

    public function test_needs_review_callback_only_sends_public_confirmation_once(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 5]);
        $this->checkoutSession($product, totalCents: 550);
        $this->processUpdate($this->telegramUpdate(messageId: 52, transactionId: '141414141414141', amountCents: 550));
        $this->claimOnlyCheckoutForExistingAlert();

        $order = Order::query()->firstOrFail();
        $service = app(TelegramPaymentAlertService::class);
        $callback = $this->telegramOrderCallback('review-callback-1', 'review', $order->id);

        $this->assertTrue($service->handleAdminCallback($callback));
        $this->assertFalse($service->handleAdminCallback(array_replace($callback, ['id' => 'review-callback-2'])));

        $this->assertSame(1, substr_count((string) $order->fresh()->admin_note, 'Marked needs review from Telegram by user'));
        $this->assertSame(TelegramPaymentAlertStatus::NeedsReview, $order->fresh('telegramPaymentAlert')->telegramPaymentAlert->status);
        $this->assertSame('review', data_get($order->fresh('telegramPaymentAlert')->telegramPaymentAlert->metadata, 'telegram_admin_callback.action'));
        $this->assertCount(1, $this->sentTelegramMessagesContaining('marked needs review.'));
    }

    public function test_admin_must_confirm_shipping_for_needs_review_telegram_payment(): void
    {
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 0]);
        $this->checkoutSession($product, totalCents: 550);
        $this->processUpdate($this->telegramUpdate(messageId: 50, transactionId: '121212121212121', amountCents: 550));
        $this->claimOnlyCheckoutForExistingAlert();

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, ['admin']);
        $order = Order::query()->firstOrFail();

        $this->patchJson("/api/admin/orders/{$order->order_number}", [
            'status' => OrderStatus::Shipped->value,
            'admin_note' => $order->admin_note,
        ])->assertUnprocessable();

        $this->patchJson("/api/admin/orders/{$order->order_number}", [
            'status' => OrderStatus::Shipped->value,
            'admin_note' => $order->admin_note,
            'force_ship_without_verified_payment' => true,
        ])->assertOk();
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        config(['telegram.mode' => 'webhook']);

        $this->postJson('/api/telegram/payments/webhook', $this->telegramUpdate())
            ->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_webhook_accepts_valid_secret_and_processes_alert(): void
    {
        config(['telegram.mode' => 'webhook']);
        $product = Product::factory()->create(['price_cents' => 400, 'stock_quantity' => 5]);
        $this->checkoutSession($product, totalCents: 550);

        $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret')
            ->postJson('/api/telegram/payments/webhook', $this->telegramUpdate(transactionId: '888888888888888', amountCents: 550))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('telegram_payment_alerts', 1);
    }

    private function claimOnlyCheckoutForExistingAlert(): void
    {
        $session = CheckoutSession::query()->sole();
        $alert = TelegramPaymentAlert::query()->whereNotNull('transaction_id')->sole();
        $this->claimSession($session, $alert->transaction_id);
        app(TelegramPaymentAlertService::class)->matchExistingAlert($alert);
    }

    private function claimSession(CheckoutSession $session, string $reference): void
    {
        $session->update([
            'status' => CheckoutSessionStatus::PaymentClaimed,
            'payment_claimed_at' => now(),
            'claimed_transaction_reference' => strtoupper($reference),
            'payment_receipt_path' => 'payment-receipts/test-receipt.png',
        ]);
    }

    private function processUpdate(array $update): void
    {
        app(TelegramPaymentAlertService::class)->processUpdate($update);
    }

    private function telegramOrderCallback(string $callbackId, string $action, int $orderId): array
    {
        return [
            'id' => $callbackId,
            'from' => ['id' => self::ADMIN_USER_ID],
            'data' => "zyn_order:{$action}:{$orderId}",
            'message' => [
                'message_id' => 700,
                'chat' => ['id' => self::GROUP_ID],
            ],
        ];
    }

    private function sentTelegramMessagesContaining(string $text): array
    {
        return Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage'))
            ->filter(fn (array $record): bool => str_contains((string) $record[0]['text'], $text))
            ->values()
            ->all();
    }

    private function checkoutSession(
        Product $product,
        int $totalCents,
        int $quantity = 1,
        string $currency = 'USD',
        string $customerName = 'Test Customer',
        mixed $createdAt = null,
    ): CheckoutSession {
        $subtotalCents = max(0, $totalCents - 150);
        $createdAt ??= now();

        return CheckoutSession::factory()->create([
            'customer_snapshot' => [
                'name' => $customerName,
                'phone' => '012345678',
                'province' => 'Phnom Penh',
                'district' => 'Tuol Kouk',
                'address_note' => 'Near market',
            ],
            'cart_snapshot' => [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'strength_mg' => $product->strength_mg,
                        'unit_price_cents' => $subtotalCents,
                        'quantity' => $quantity,
                        'line_total_cents' => $subtotalCents * $quantity,
                    ],
                ],
            ],
            'subtotal_cents' => $subtotalCents * $quantity,
            'delivery_fee_cents' => 150,
            'total_cents' => $totalCents,
            'currency' => $currency,
            'payment_method' => PaymentMethod::PayWay,
            'status' => CheckoutSessionStatus::AwaitingPayment,
            'expires_at' => now()->addMinutes(15),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function telegramUpdate(
        int $messageId = 7,
        int $chatId = self::GROUP_ID,
        int $senderId = self::ABA_SENDER_ID,
        string $transactionId = '123456789012345',
        int $amountCents = 550,
        ?string $text = null,
        ?CarbonImmutable $messageSentAt = null,
    ): array {
        $messageSentAt ??= now('Asia/Phnom_Penh')->toImmutable();
        $displayedPaidAt = $messageSentAt->subMinute()->format('M d, h:i A');
        $text ??= '$'.$this->decimalAmount($amountCents).' paid by TEST CUSTOMER (*770) on '.$displayedPaidAt.' via ABA PAY at LIV SEANGLY. Trx. ID: '.$transactionId.', APV: 123456.';

        return [
            'update_id' => 7000 + $messageId,
            'message' => [
                'message_id' => $messageId,
                'date' => $messageSentAt->timestamp,
                'chat' => ['id' => $chatId],
                'from' => ['id' => $senderId],
                'text' => $text,
            ],
        ];
    }

    private function decimalAmount(int $amountCents): string
    {
        return intdiv($amountCents, 100).'.'.str_pad((string) ($amountCents % 100), 2, '0', STR_PAD_LEFT);
    }
}
