<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function decimalToCents(string|int $amount): int
    {
        $normalized = trim((string) $amount);

        if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('Amount must be a positive decimal with up to two places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public static function centsToDecimal(int $cents): string
    {
        $whole = intdiv($cents, 100);
        $fraction = str_pad((string) abs($cents % 100), 2, '0', STR_PAD_LEFT);

        return "{$whole}.{$fraction}";
    }

    public static function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        if (! in_array($normalized, ['USD', 'KHR'], true)) {
            throw new InvalidArgumentException('Unsupported currency.');
        }

        return $normalized;
    }
}
