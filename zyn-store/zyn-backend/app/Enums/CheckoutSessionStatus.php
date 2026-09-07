<?php

namespace App\Enums;

enum CheckoutSessionStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PaymentClaimed = 'payment_claimed';
    case Matched = 'matched';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
