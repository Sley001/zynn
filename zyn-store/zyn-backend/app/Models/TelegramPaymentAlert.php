<?php

namespace App\Models;

use App\Enums\TelegramPaymentAlertStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramPaymentAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_update_id',
        'telegram_chat_id',
        'telegram_message_id',
        'telegram_from_id',
        'telegram_sender_chat_id',
        'telegram_message_sent_at',
        'telegram_update_received_at',
        'raw_message',
        'amount',
        'amount_cents',
        'currency',
        'payer_name',
        'masked_account_digits',
        'displayed_paid_at',
        'payment_method',
        'merchant_name',
        'transaction_id',
        'apv',
        'status',
        'matched_checkout_session_id',
        'order_id',
        'payment_id',
        'duplicate_of_alert_id',
        'processed_at',
        'failure_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'telegram_update_id' => 'integer',
            'telegram_chat_id' => 'integer',
            'telegram_message_id' => 'integer',
            'telegram_from_id' => 'integer',
            'telegram_sender_chat_id' => 'integer',
            'telegram_message_sent_at' => 'datetime',
            'telegram_update_received_at' => 'datetime',
            'amount_cents' => 'integer',
            'displayed_paid_at' => 'datetime',
            'status' => TelegramPaymentAlertStatus::class,
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'matched_checkout_session_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_alert_id');
    }
}
