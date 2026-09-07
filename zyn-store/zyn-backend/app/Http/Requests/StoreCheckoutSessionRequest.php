<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'phone' => trim((string) $this->input('phone')),
            'province' => trim((string) $this->input('province')),
            'district' => trim((string) $this->input('district')),
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^[0-9+()\s-]{8,20}$/'],
            'province' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'address_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => [
                'required',
                Rule::in([PaymentMethod::Khqr->value, PaymentMethod::PayWay->value]),
            ],
            'telegram_username' => ['nullable', 'string', 'max:100'],
            'age_confirmed' => ['accepted'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'age_confirmed.accepted' => 'You must confirm that you are 21+ and currently use nicotine.',
            'phone.regex' => 'Enter a valid phone number.',
        ];
    }
}
