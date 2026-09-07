<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'delivery_fee' => ['sometimes', 'required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'checkout_session_lifetime_minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
        ];
    }
}
