<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attachments' => [],
            'orderItem' => [
                'id' => $this->id,
                'status' => $this->status,
                'status_formatted' => Str::headline($this->status),
                'status_label' => Str::headline($this->status),
                'cancellation_reason' => $this->cancellation_reason,
            ],
            'product' => $this->product ? [
                'id' => $this->product->id,
                'title' => $this->product_title,
                'slug' => $this->product->slug,
                'image' => $this->product->mainImageUrl(),
            ] : [
                'id' => null,
                'title' => $this->product_title,
            ],
            'variant' => $this->variant ? [
                'id' => $this->variant->id,
                'title' => $this->variant_title,
                'slug' => $this->variant->slug,
            ] : [
                'id' => null,
                'title' => $this->variant_title,
            ],
            'store' => $this->store ? [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'contact_email' => $this->store->contact_email,
                'contact_number' => $this->store->contact_number,
            ] : null,
            'price' => $this->price,
            'special_price' => $this->special_price,
            'tax_amount' => $this->tax_amount,
            'sub_total' => $this->subtotal,
            'quantity' => $this->quantity,
            'subtotal' => (float) $this->subtotal,
            'is_returnable' => (bool) $this->is_returnable,
            'returnable_until' => $this->returnable_until
                ?->format('Y-m-d H:i:s'),
            'return_request' => $this->returnRequest
                ? new OrderItemReturnResource($this->returnRequest)
                : null,
            'addons' => [],
            'addons_total' => 0,
        ];
    }
}
