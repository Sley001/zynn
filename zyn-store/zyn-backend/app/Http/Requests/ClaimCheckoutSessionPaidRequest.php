<?php

namespace App\Http\Requests;

use App\Rules\PaymentReceiptImage;
use Illuminate\Foundation\Http\FormRequest;

class ClaimCheckoutSessionPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_reference' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{6,100}$/'],
            // Required for a new claim under the session lock in the service.
            // Legacy claims remain safe to retry without uploading new evidence.
            'receipt' => [
                'bail', 'nullable', 'file', 'max:5120', 'image', 'mimes:jpg,jpeg,png,webp',
                'dimensions:max_width=6000,max_height=6000', new PaymentReceiptImage,
            ],
        ];
    }
}
