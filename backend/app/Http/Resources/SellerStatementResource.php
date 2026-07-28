<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SellerStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'seller_order_id' => $this->seller_order_id,
            'order_id' => $this->order_id,
            'order_slug' => $this->order?->slug,
            'entry_type' => $this->entry_type,
            'entry_type_label' => Str::headline($this->entry_type),
            'direction' => $this->direction,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'description' => $this->description,
            'settlement_status' => $this->settlement_status,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'settlement_reference' => $this->settlement_reference,
        ];
    }
}
