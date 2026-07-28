<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryBoyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'mobile' => $this->user?->mobile,
            'status' => $this->status,
            'verification_status' => $this->verification_status,
            'is_blocked' => (bool) $this->is_blocked,
            'blocked_reason' => $this->blocked_reason,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_number' => $this->vehicle_number,
            'license_number' => $this->license_number,
            'delivery_zone' => $this->deliveryZone ? [
                'id' => $this->deliveryZone->id,
                'name' => $this->deliveryZone->name,
                'slug' => $this->deliveryZone->slug,
            ] : null,
            'location' => $this->location ? [
                'latitude' => (float) $this->location->latitude,
                'longitude' => (float) $this->location->longitude,
                'heading' => $this->location->heading === null
                    ? null
                    : (float) $this->location->heading,
                'speed' => $this->location->speed === null
                    ? null
                    : (float) $this->location->speed,
                'accuracy' => $this->location->accuracy === null
                    ? null
                    : (float) $this->location->accuracy,
                'recorded_at' => $this->location->recorded_at
                    ?->toIso8601String(),
            ] : null,
        ];
    }
}
