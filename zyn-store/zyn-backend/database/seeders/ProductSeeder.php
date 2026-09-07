<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Cool Mint',
                'slug' => 'cool-mint',
                'description' => 'Crisp mint with a steady release.',
                'strength_mg' => 6,
                'price_cents' => 400,
                'color' => '#1c4a3e',
                'accent' => '#c9a227',
                'origin' => 'Sweden',
                'release_duration' => '30–45 min',
                'notes' => ['Peppermint', 'Eucalyptus', 'Cooling menthol'],
                'status' => ProductStatus::Available,
                'stock_quantity' => 50,
                'track_stock' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Citrus Noir',
                'slug' => 'citrus-noir',
                'description' => 'Sample slot — replace with a real product before sale.',
                'strength_mg' => 3,
                'price_cents' => 450,
                'color' => '#7a4a16',
                'accent' => '#c9a227',
                'origin' => 'Sweden',
                'release_duration' => '25–40 min',
                'notes' => ['Bergamot', 'Orange peel', 'Light spice'],
                'status' => ProductStatus::Sample,
                'stock_quantity' => 0,
                'track_stock' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Cinnamon',
                'slug' => 'cinnamon',
                'description' => 'Sample slot — replace with a real product before sale.',
                'strength_mg' => 6,
                'price_cents' => 450,
                'color' => '#5c2a1e',
                'accent' => '#c9a227',
                'origin' => 'Sweden',
                'release_duration' => '30–45 min',
                'notes' => ['Cinnamon', 'Clove', 'Warm spice'],
                'status' => ProductStatus::Sample,
                'stock_quantity' => 0,
                'track_stock' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Wintergreen',
                'slug' => 'wintergreen',
                'description' => 'Sample slot — replace with a real product before sale.',
                'strength_mg' => 9,
                'price_cents' => 475,
                'color' => '#0f3b2e',
                'accent' => '#c9a227',
                'origin' => 'Sweden',
                'release_duration' => '35–50 min',
                'notes' => ['Wintergreen', 'Dark mint', 'Menthol'],
                'status' => ProductStatus::Sample,
                'stock_quantity' => 0,
                'track_stock' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Coffee Royale',
                'slug' => 'coffee-royale',
                'description' => 'Sample slot — replace with a real product before sale.',
                'strength_mg' => 6,
                'price_cents' => 475,
                'color' => '#2e1d12',
                'accent' => '#c9a227',
                'origin' => 'Sweden',
                'release_duration' => '30–45 min',
                'notes' => ['Espresso', 'Roasted cacao', 'Vanilla'],
                'status' => ProductStatus::Sample,
                'stock_quantity' => 0,
                'track_stock' => true,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Spearmint',
                'slug' => 'spearmint',
                'description' => 'Sample slot — replace with a real product before sale.',
                'strength_mg' => 3,
                'price_cents' => 450,
                'color' => '#173d2e',
                'accent' => '#c9a227',
                'origin' => 'Sweden',
                'release_duration' => '25–40 min',
                'notes' => ['Spearmint', 'Soft herb', 'Light cool'],
                'status' => ProductStatus::Sample,
                'stock_quantity' => 0,
                'track_stock' => true,
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(['slug' => $product['slug']], $product);
        }
    }
}
