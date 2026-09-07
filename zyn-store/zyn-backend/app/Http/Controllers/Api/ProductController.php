<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);

        $products = Product::query()
            ->where('is_active', true)
            ->where('status', '!=', ProductStatus::Draft->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        abort_unless(
            $product->is_active && $product->status !== ProductStatus::Draft,
            404,
        );

        return new ProductResource($product);
    }
}
