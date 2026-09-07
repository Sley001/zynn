<?php

namespace App\Enums;

enum TelegramPaymentAlertStatus: string
{
    case Received = 'received';
    case Matched = 'matched';
    case Unmatched = 'unmatched';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
    case NeedsReview = 'needs_review';
}
