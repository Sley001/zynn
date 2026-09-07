<?php

namespace App\Services;

use App\Exceptions\MalformedTelegramPaymentAlertException;
use App\Support\Money;
use Carbon\CarbonImmutable;

class TelegramPaymentAlertParser
{
    private const ABA_ALERT_PATTERN = '/^\s*\$(?<amount>[0-9][0-9,]*(?:\.[0-9]{2})?)\s+paid\s+by\s+(?<payer>.+?)\s+\(\*(?<masked>[0-9]{2,8})\)\s+on\s+(?<paid_at>[A-Z][a-z]{2}\s+[0-9]{1,2},\s+[0-9]{1,2}:[0-9]{2}\s+[AP]M)\s+via\s+(?<method>.+?)\s+at\s+(?<merchant>.+?)\.\s+Trx\.\s+ID:\s+(?<transaction_id>[A-Za-z0-9-]+),\s+APV:\s+(?<apv>[A-Za-z0-9-]+)\.?\s*$/';

    /**
     * @param  array{
     *     telegram_update_id?: int|null,
     *     telegram_chat_id: int,
     *     telegram_message_id: int,
     *     telegram_from_id?: int|null,
     *     telegram_sender_chat_id?: int|null,
     *     telegram_message_date?: int|null,
     *     telegram_update_received_at?: \DateTimeInterface|string|null
     * }  $context
     */
    public function parse(string $text, array $context): array
    {
        if (! preg_match(self::ABA_ALERT_PATTERN, $text, $matches)) {
            throw new MalformedTelegramPaymentAlertException('Telegram payment alert does not match the expected ABA format.');
        }

        $messageSentAt = isset($context['telegram_message_date'])
            ? CarbonImmutable::createFromTimestamp((int) $context['telegram_message_date'], 'Asia/Phnom_Penh')
            : now('Asia/Phnom_Penh')->toImmutable();

        $amount = str_replace(',', '', $matches['amount']);

        return [
            'telegram_update_id' => $context['telegram_update_id'] ?? null,
            'telegram_chat_id' => $context['telegram_chat_id'],
            'telegram_message_id' => $context['telegram_message_id'],
            'telegram_from_id' => $context['telegram_from_id'] ?? null,
            'telegram_sender_chat_id' => $context['telegram_sender_chat_id'] ?? null,
            'telegram_message_sent_at' => $messageSentAt,
            'telegram_update_received_at' => isset($context['telegram_update_received_at'])
                ? CarbonImmutable::parse($context['telegram_update_received_at'], 'Asia/Phnom_Penh')
                : now('Asia/Phnom_Penh')->toImmutable(),
            'raw_message' => $text,
            'amount' => Money::centsToDecimal(Money::decimalToCents($amount)),
            'amount_cents' => Money::decimalToCents($amount),
            'currency' => 'USD',
            'payer_name' => trim($matches['payer']),
            'masked_account_digits' => $matches['masked'],
            'displayed_paid_at' => $this->resolveDisplayedPaidAt($matches['paid_at'], $messageSentAt),
            'payment_method' => trim($matches['method']),
            'merchant_name' => trim($matches['merchant']),
            'transaction_id' => $matches['transaction_id'],
            'apv' => $matches['apv'],
        ];
    }

    private function resolveDisplayedPaidAt(string $dateTime, CarbonImmutable $messageSentAt): CarbonImmutable
    {
        $timezone = 'Asia/Phnom_Penh';
        $messageLocal = $messageSentAt->setTimezone($timezone);

        $candidates = collect([
            $messageLocal->year - 1,
            $messageLocal->year,
            $messageLocal->year + 1,
        ])->map(fn (int $year): CarbonImmutable => CarbonImmutable::createFromFormat(
            'Y M d, h:i A',
            $year.' '.$dateTime,
            $timezone
        ));

        return $candidates
            ->sortBy(fn (CarbonImmutable $candidate): int => abs($candidate->getTimestamp() - $messageLocal->getTimestamp()))
            ->first();
    }
}
