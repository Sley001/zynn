<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'payment_status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'transaction_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'force_ship_without_verified_payment' => ['sometimes', 'boolean'],
        ];
    }
}
