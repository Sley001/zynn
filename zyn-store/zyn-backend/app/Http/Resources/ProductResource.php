<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'strength_mg' => $this->strength_mg,
            'price' => $this->price_cents / 100,
            'price_cents' => $this->price_cents,
            'color' => $this->color,
            'accent' => $this->accent,
            'origin' => $this->origin,
            'release_duration' => $this->release_duration,
            'notes' => $this->notes ?? [],
            'image_url' => $this->image_path
                ? Storage::disk('public')->url($this->image_path)
                : null,
            'status' => $this->status->value,
            'is_orderable' => $this->status->value === 'available'
                && $this->is_active
                && (! $this->track_stock || $this->stock_quantity > 0),
            'stock_quantity' => $this->when($request->user()?->is_admin, $this->stock_quantity),
            'track_stock' => $this->when($request->user()?->is_admin, $this->track_stock),
            'is_active' => $this->when($request->user()?->is_admin, $this->is_active),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
