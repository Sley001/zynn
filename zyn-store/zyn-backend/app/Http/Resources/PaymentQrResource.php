<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PaymentQrResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => Money::centsToDecimal($this->amount_cents),
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'image_url' => Storage::disk('public')->url($this->image_path),
            'is_active' => $this->is_active,
            'admin_note' => $this->admin_note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
