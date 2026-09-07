<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'strength_mg',
        'price_cents',
        'color',
        'accent',
        'origin',
        'release_duration',
        'notes',
        'image_path',
        'status',
        'stock_quantity',
        'track_stock',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'strength_mg' => 'integer',
            'price_cents' => 'integer',
            'notes' => 'array',
            'status' => ProductStatus::class,
            'stock_quantity' => 'integer',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
