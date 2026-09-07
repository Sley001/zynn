<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_name',
        'phone',
        'province',
        'district',
        'address_note',
        'payment_method',
        'status',
        'subtotal_cents',
        'delivery_fee_cents',
        'total_cents',
        'age_confirmed_at',
        'source',
        'telegram_username',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
            'subtotal_cents' => 'integer',
            'delivery_fee_cents' => 'integer',
            'total_cents' => 'integer',
            'age_confirmed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function telegramPaymentAlert(): HasOne
    {
        return $this->hasOne(TelegramPaymentAlert::class);
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }
}
