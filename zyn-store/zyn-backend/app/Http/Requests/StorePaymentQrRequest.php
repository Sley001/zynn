<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper(trim((string) $this->input('currency', 'USD'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'currency' => ['required', Rule::in(['USD', 'KHR'])],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
