<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_returns_active_non_draft_products(): void
    {
        $available = Product::factory()->create(['slug' => 'cool-mint']);
        Product::factory()->create([
            'slug' => 'hidden-draft',
            'status' => ProductStatus::Draft,
        ]);
        Product::factory()->create([
            'slug' => 'inactive-product',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/products');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id)
            ->assertJsonPath('data.0.price', 4);
    }
}
