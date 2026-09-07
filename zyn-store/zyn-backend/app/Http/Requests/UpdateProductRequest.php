<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:170',
                'alpha_dash',
                Rule::unique('products', 'slug')->ignore($product?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'strength_mg' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'price_cents' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
            'color' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'origin' => ['sometimes', 'nullable', 'string', 'max:100'],
            'release_duration' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'array', 'max:10'],
            'notes.*' => ['string', 'max:100'],
            'image' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'required', Rule::enum(ProductStatus::class)],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'track_stock' => ['sometimes', 'required', 'boolean'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
