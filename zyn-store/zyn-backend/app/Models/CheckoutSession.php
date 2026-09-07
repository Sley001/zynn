<?php

namespace App\Models;

use App\Enums\CheckoutSessionStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CheckoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'customer_snapshot',
        'cart_snapshot',
        'subtotal_cents',
        'delivery_fee_cents',
        'total_cents',
        'currency',
        'payment_method',
        'status',
        'payment_qr_id',
        'qr_image_path',
        'payment_claimed_at',
        'claimed_transaction_reference',
        'payment_receipt_path',
        'expires_at',
        'consumed_at',
        'resulting_order_id',
    ];

    protected $hidden = ['payment_receipt_path'];

    protected function casts(): array
    {
        return [
            'customer_snapshot' => 'array',
            'cart_snapshot' => 'array',
            'subtotal_cents' => 'integer',
            'delivery_fee_cents' => 'integer',
            'total_cents' => 'integer',
            'payment_method' => PaymentMethod::class,
            'status' => CheckoutSessionStatus::class,
            'payment_claimed_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function paymentQr(): BelongsTo
    {
        return $this->belongsTo(PaymentQr::class);
    }

    public function resultingOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'resulting_order_id');
    }

    public function telegramPaymentAlert(): HasOne
    {
        return $this->hasOne(TelegramPaymentAlert::class, 'matched_checkout_session_id');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }
}
