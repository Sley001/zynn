<?php

namespace Database\Seeders;

use App\Models\PaymentQr;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PaymentQrSeeder extends Seeder
{
    private const CURRENCY = 'USD';

    private const QR_CODES = [
        ['amount_cents' => 400, 'file' => 'aba-pay-usd-004-00.jpg'],
        ['amount_cents' => 650, 'file' => 'aba-pay-usd-006-50.jpg'],
        ['amount_cents' => 900, 'file' => 'aba-pay-usd-009-00.jpg'],
        ['amount_cents' => 1150, 'file' => 'aba-pay-usd-011-50.jpg'],
        ['amount_cents' => 1400, 'file' => 'aba-pay-usd-014-00.jpg'],
        ['amount_cents' => 1650, 'file' => 'aba-pay-usd-016-50.jpg'],
        ['amount_cents' => 1900, 'file' => 'aba-pay-usd-019-00.jpg'],
        ['amount_cents' => 2150, 'file' => 'aba-pay-usd-021-50.jpg'],
        ['amount_cents' => 2400, 'file' => 'aba-pay-usd-024-00.jpg'],
        ['amount_cents' => 2650, 'file' => 'aba-pay-usd-026-50.jpg'],
    ];

    public function run(): void
    {
        foreach (self::QR_CODES as $qrCode) {
            $this->seedQr($qrCode['amount_cents'], $qrCode['file']);
        }
    }

    private function seedQr(int $amountCents, string $fileName): void
    {
        $sourcePath = database_path("seeders/assets/payment-qrs/{$fileName}");
        $targetPath = "payment-qrs/{$fileName}";

        if (! is_file($sourcePath)) {
            throw new RuntimeException("Payment QR seed asset is missing: {$sourcePath}");
        }

        $contents = file_get_contents($sourcePath);

        if ($contents === false || ! Storage::disk('public')->put($targetPath, $contents)) {
            throw new RuntimeException("Unable to store payment QR seed asset: {$fileName}");
        }

        DB::transaction(function () use ($amountCents, $targetPath): void {
            PaymentQr::query()
                ->where('amount_cents', $amountCents)
                ->where('currency', self::CURRENCY)
                ->where('image_path', '!=', $targetPath)
                ->update(['is_active' => false]);

            PaymentQr::query()->updateOrCreate(
                [
                    'amount_cents' => $amountCents,
                    'currency' => self::CURRENCY,
                    'image_path' => $targetPath,
                ],
                [
                    'amount' => Money::centsToDecimal($amountCents),
                    'is_active' => true,
                    'admin_note' => 'ABA PAY KHQR fixed QR for '.self::CURRENCY.' '.Money::centsToDecimal($amountCents).'.',
                ],
            );
        }, 3);

        $this->command?->info('Seeded payment QR '.self::CURRENCY.' '.Money::centsToDecimal($amountCents).'.');
    }
}
