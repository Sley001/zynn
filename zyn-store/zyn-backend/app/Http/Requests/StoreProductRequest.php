<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:170', 'alpha_dash', 'unique:products,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'strength_mg' => ['required', 'integer', 'min:1', 'max:100'],
            'price_cents' => ['required', 'integer', 'min:1', 'max:1000000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'origin' => ['nullable', 'string', 'max:100'],
            'release_duration' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'array', 'max:10'],
            'notes.*' => ['string', 'max:100'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'track_stock' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
