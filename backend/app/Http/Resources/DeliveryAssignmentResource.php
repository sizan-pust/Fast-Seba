<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DeliveryAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => Str::headline($this->status),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'out_for_delivery_at' => $this->out_for_delivery_at
                ?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'total_earnings' => $this->total_earnings,
            'cash_collected' => $this->cash_collected,
            'payment_status' => $this->payment_status,
            'delivery_boy' => new DeliveryBoyResource(
                $this->whenLoaded('deliveryBoy')
            ),
        ];
    }
}
