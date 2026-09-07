<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentQr extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'amount_cents',
        'currency',
        'image_path',
        'is_active',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }
}
