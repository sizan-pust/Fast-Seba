<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerManagedStoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'city' => $this->city,
            'landmark' => $this->landmark,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'contact_email' => $this->contact_email,
            'contact_number' => $this->contact_number,
            'description' => $this->description,
            'timing' => $this->timing,
            'status' => $this->status,
            'verification_status' => $this->verification_status,
            'visibility_status' => $this->visibility_status,
            'is_recommended' => (bool) $this->is_recommended,
            'allows_pickup' => (bool) $this->allows_pickup,
            'pickup_instructions' => $this->pickup_instructions,
            'order_preparation_time' => $this->order_preparation_time,
            'zones' => $this->relationLoaded('zones')
                ? $this->zones->map(fn ($zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'slug' => $zone->slug,
                ])->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
