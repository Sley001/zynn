<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge([
                'currency' => strtoupper(trim((string) $this->input('currency'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'currency' => ['sometimes', 'required', Rule::in(['USD', 'KHR'])],
            'image' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
