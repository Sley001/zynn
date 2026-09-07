<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentQrUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $totalCents,
        public readonly string $currency,
    ) {
        parent::__construct($message);
    }
}
