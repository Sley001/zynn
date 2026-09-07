<?php

namespace Tests\Unit;

use App\Exceptions\MalformedTelegramPaymentAlertException;
use App\Services\TelegramPaymentAlertParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class TelegramPaymentAlertParserTest extends TestCase
{
    public function test_parses_valid_synthetic_aba_alert(): void
    {
        $messageSentAt = CarbonImmutable::create(2026, 9, 1, 14, 12, 0, 'Asia/Phnom_Penh');
        $parser = new TelegramPaymentAlertParser;

        $parsed = $parser->parse(
            '$4.00 paid by TEST CUSTOMER (*770) on Sep 01, 02:11 PM via ABA PAY at LIV SEANGLY. Trx. ID: 123456789012345, APV: 123456.',
            [
                'telegram_update_id' => 7001,
                'telegram_chat_id' => -1001234567890,
                'telegram_message_id' => 44,
                'telegram_from_id' => 99887766,
                'telegram_sender_chat_id' => null,
                'telegram_message_date' => $messageSentAt->timestamp,
                'telegram_update_received_at' => $messageSentAt,
            ],
        );

        $this->assertSame(400, $parsed['amount_cents']);
        $this->assertSame('USD', $parsed['currency']);
        $this->assertSame('TEST CUSTOMER', $parsed['payer_name']);
        $this->assertSame('770', $parsed['masked_account_digits']);
        $this->assertSame('ABA PAY', $parsed['payment_method']);
        $this->assertSame('LIV SEANGLY', $parsed['merchant_name']);
        $this->assertSame('123456789012345', $parsed['transaction_id']);
        $this->assertSame('123456', $parsed['apv']);
        $this->assertSame('2026-09-01 14:11:00', $parsed['displayed_paid_at']->format('Y-m-d H:i:s'));
    }

    public function test_parses_liv_seangly_two_cent_alert(): void
    {
        $messageSentAt = CarbonImmutable::create(2026, 9, 3, 15, 5, 30, 'Asia/Phnom_Penh');
        $parser = new TelegramPaymentAlertParser;

        $parsed = $parser->parse(
            '$0.02 paid by LIV SEANGLY (*770) on Sep 03, 03:05 PM via ABA PAY at LIV SEANGLY. Trx. ID: 178842274633231, APV: 413135.',
            [
                'telegram_update_id' => 9001,
                'telegram_chat_id' => -1001234567890,
                'telegram_message_id' => 77,
                'telegram_from_id' => 99887766,
                'telegram_sender_chat_id' => null,
                'telegram_message_date' => $messageSentAt->timestamp,
                'telegram_update_received_at' => $messageSentAt,
            ],
        );

        $this->assertSame(2, $parsed['amount_cents']);
        $this->assertSame('USD', $parsed['currency']);
        $this->assertSame('LIV SEANGLY', $parsed['payer_name']);
        $this->assertSame('770', $parsed['masked_account_digits']);
        $this->assertSame('ABA PAY', $parsed['payment_method']);
        $this->assertSame('LIV SEANGLY', $parsed['merchant_name']);
        $this->assertSame('178842274633231', $parsed['transaction_id']);
        $this->assertSame('413135', $parsed['apv']);
        $this->assertSame('2026-09-03 15:05:00', $parsed['displayed_paid_at']->format('Y-m-d H:i:s'));
    }

    public function test_rejects_malformed_alerts(): void
    {
        $this->expectException(MalformedTelegramPaymentAlertException::class);

        (new TelegramPaymentAlertParser)->parse('Paid successfully, trust me.', [
            'telegram_chat_id' => -1001234567890,
            'telegram_message_id' => 44,
            'telegram_message_date' => CarbonImmutable::create(2026, 9, 1, 14, 12, 0, 'Asia/Phnom_Penh')->timestamp,
        ]);
    }
}
