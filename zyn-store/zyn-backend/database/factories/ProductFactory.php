<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'strength_mg' => 6,
            'price_cents' => 400,
            'color' => '#1c4a3e',
            'accent' => '#c9a227',
            'origin' => 'Sweden',
            'release_duration' => '30–45 min',
            'notes' => ['Mint', 'Cooling'],
            'status' => ProductStatus::Available,
            'stock_quantity' => 20,
            'track_stock' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
