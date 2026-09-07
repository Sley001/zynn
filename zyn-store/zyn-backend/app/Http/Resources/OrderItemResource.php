<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'strength_mg' => $this->strength_mg,
            'unit_price' => $this->unit_price_cents / 100,
            'unit_price_cents' => $this->unit_price_cents,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total_cents / 100,
            'line_total_cents' => $this->line_total_cents,
        ];
    }
}
